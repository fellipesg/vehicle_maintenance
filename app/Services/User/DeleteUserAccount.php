<?php

namespace App\Services\User;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleConsignment;
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
     */
    public function handle(User $user): void
    {
        $consignments = $this->activeConsignmentsOwnedBy($user);
        $originalEmail = $user->email;

        DB::transaction(function () use ($user, $originalEmail): void {
            $this->deleteStoredAvatar($user->avatar);

            $user->fcmTokens()->delete();
            $user->tokens()->delete();
            $user->vehicles()->detach();

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

    private function deleteStoredAvatar(?string $avatar): void
    {
        if ($avatar === null || $avatar === '') {
            return;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return;
        }

        if (AppStorage::disk()->exists($avatar)) {
            AppStorage::disk()->delete($avatar);
        }
    }
}
