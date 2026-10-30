<?php

namespace App\Services\User;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleConsignment;
use App\Models\VehiclePdfExport;
use App\Notifications\ConsignmentOwnerAccountDeletedNotification;
use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeleteUserAccount
{
    public const DELETED_NAME = 'Conta excluída';

    /**
     * Anonymize the requesting user's personal data without deleting the row.
     *
     * Hard-deleting the user would cascade-delete maintenances they registered
     * (`maintenances.user_id`), wiping VIN history that other owners still need.
     * Vehicle records, plates, and every maintenance on the chassis stay put;
     * only this user's PII, tokens, FCM devices, and vehicle links are removed.
     *
     * A PII do dono também é copiada para fora de `users`: `vehicle_consignments` guarda nome,
     * e-mail, telefone e documento do proprietário, e o tenant individual nasce com o nome da
     * pessoa. Apagar só a linha de `users` deixaria essas cópias intactas.
     *
     * Saem na mesma transação as notificações do canal `database` (o texto cita nome, veículo e
     * placa) e os relatórios em PDF já gerados — linha de `vehicle_pdf_exports` e o arquivo —,
     * que de outro modo só sumiriam no `vehicle-pdf-exports:cleanup`, até 24 h depois.
     *
     * A sessão web morre aqui, para todos os caminhos de exclusão de uma vez: as linhas de
     * `sessions` da pessoa saem junto. Quem está excluindo pela própria sessão ainda precisa do
     * logout da requisição (App\Http\Controllers\Concerns\EndsDeletedAccountSession), senão o
     * framework regrava a linha ao fim da resposta.
     */
    public function handle(User $user): void
    {
        $consignments = $this->activeConsignmentsOwnedBy($user);
        $originalEmail = $user->email;

        DB::transaction(function () use ($user, $originalEmail): void {
            $this->deleteStoredFile($user->avatar);

            $user->fcmTokens()->delete();
            $user->tokens()->delete();
            $user->vehicles()->detach();
            $user->notifications()->delete();

            $this->deleteWebSessions($user);
            $this->deleteGeneratedPdfExports($user);

            $user->forceFill([
                'name' => self::DELETED_NAME,
                'email' => $this->deletedEmail($user),
                'password' => Str::password(32),
                'phone' => null,
                'document' => null,
                'postal_code' => null,
                'street' => null,
                'number' => null,
                'complement' => null,
                'city' => null,
                'state' => null,
                'country' => 'Brasil',
                'latitude' => null,
                'longitude' => null,
                'provider' => null,
                'provider_id' => null,
                'avatar' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'is_admin' => false,
                'subscription_active' => false,
            ])->save();

            $this->scrubConsignmentContact($user, $originalEmail);
            $this->scrubIndividualTenantName($user);
        });

        // Fora da transação: a loja fica com o carro, então precisa saber que não há mais
        // ninguém do outro lado para receber aviso de serviço ou liberar o histórico.
        foreach ($consignments as $consignment) {
            $consignment->garageUser?->notify(
                new ConsignmentOwnerAccountDeletedNotification($consignment->fresh())
            );
        }
    }

    /**
     * Derruba a sessão web de todos os aparelhos. O driver é `database` (config/session.php), então
     * a sessão é uma linha com `user_id`; em outro driver não há o que apagar aqui, e o logout da
     * própria requisição continua valendo.
     */
    private function deleteWebSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Relatório em PDF do histórico: a linha guarda quem pediu e o arquivo traz o veículo inteiro.
     * O `vehicle-pdf-exports:cleanup` só passa quando `expires_at` vence, até 24 h depois — tarde
     * demais para uma exclusão de conta.
     */
    private function deleteGeneratedPdfExports(User $user): void
    {
        VehiclePdfExport::query()
            ->where('user_id', $user->id)
            ->chunkById(100, function (Collection $exports): void {
                foreach ($exports as $export) {
                    $this->deleteStoredFile($export->file_path);
                    $export->delete();
                }
            });
    }

    /**
     * Consignações ativas do veículo deste dono, lidas antes da anonimização.
     *
     * @return Collection<int, VehicleConsignment>
     */
    private function activeConsignmentsOwnedBy(User $user): Collection
    {
        return VehicleConsignment::query()
            ->with(['garageUser', 'vehicle'])
            ->where('status', VehicleConsignment::STATUS_ACTIVE)
            ->where(fn ($query) => $query
                ->where('owner_user_id', $user->id)
                ->orWhere('owner_email', $user->email))
            ->get();
    }

    /**
     * A consignação continua ativa — some o contato, não o registro. O vínculo com a conta
     * (owner_user_id) fica: é por ele que a loja sabe que aquele dono era da plataforma.
     *
     * Também casa pelo e-mail: a loja pode ter declarado a consignação antes de o dono criar
     * conta, e aí a linha guarda o endereço sem o owner_user_id.
     */
    private function scrubConsignmentContact(User $user, string $originalEmail): void
    {
        VehicleConsignment::query()
            ->where(fn ($query) => $query
                ->where('owner_user_id', $user->id)
                ->orWhere('owner_email', $originalEmail))
            ->update([
                'owner_name' => self::DELETED_NAME,
                'owner_email' => null,
                'owner_phone' => null,
                'owner_document' => null,
            ]);
    }

    /**
     * O tenant individual nasce com o nome da pessoa (TenantService::createForUser). Tenant de
     * loja ou de oficina fica como está: ali o nome é da empresa, não de quem a cadastrou.
     */
    private function scrubIndividualTenantName(User $user): void
    {
        if ($user->tenant_id === null) {
            return;
        }

        Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('type', 'individual')
            ->update(['name' => self::DELETED_NAME]);
    }

    private function deletedEmail(User $user): string
    {
        return 'deleted.'.$user->id.'.'.Str::lower(Str::random(8)).'@deleted.revisalog.invalid';
    }

    /**
     * Caminho vazio ou URL de provedor social (o avatar do Google é um link, não um arquivo nosso)
     * não tem o que apagar no disco.
     */
    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (AppStorage::disk()->exists($path)) {
            AppStorage::disk()->delete($path);
        }
    }
}
