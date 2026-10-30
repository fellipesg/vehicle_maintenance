<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceDisputedNotification;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Decisão do proprietário atual sobre uma OS que a oficina fez antes de ele chegar:
 *
 * - link: a OS passa ao tenant dele (entra no histórico como qualquer registro); sem link, fica
 *   no chassi na forma mínima (declined);
 * - attach_files: notas e fotos só viram anexos normais com propriedade verificada pelo CRLV-e;
 *   sem aceite elas são apagadas na hora;
 * Ocultar **saiu daqui**: o proprietário não tira mais o registro da consulta pública. O histórico
 * pertence ao chassi e é o ativo do produto, e o ocultar silencioso apagava justamente o que o
 * comprador precisa ver — sem deixar sinal de que algo foi suprimido. No lugar dele entrou
 * `dispute`, que marca o registro como contestado e não esconde nada. Esconder de verdade ficou com
 * a equipe, depois de analisar (App\Services\Maintenance\MaintenancePublicVisibilityService).
 *
 * Pode ser chamada de novo para revogar o consentimento dos anexos.
 *
 * Vincular e aceitar anexos exigem propriedade verificada pelo CRLV-e. `dispute` não: é a ação que
 * o dono atual tem sem o documento. Ver o docblock de dispute().
 */
class MaintenanceOwnerDecisionService
{
    public const UNVERIFIED_MESSAGE = 'Para aceitar as notas e fotos da oficina, confirme que o veículo é seu enviando o CRLV-e.';

    /**
     * Recusar apaga as notas pendentes e ocultar tira o registro de todos os próximos donos: quem só
     * informou o chassi (cadastro manual, sem CRLV-e) não pode decidir sobre o registro de outra pessoa.
     */
    public const UNVERIFIED_DECISION_MESSAGE = 'Para decidir sobre os registros da oficina, confirme que o veículo é seu enviando o CRLV-e.';

    /**
     * Registro que não é contestável: ou já tem o Selo da oficina (histórico confirmado por quem
     * prestou o serviço, que só sai pelo caminho do CRLV-e), ou não espera decisão nenhuma.
     */
    public const NOT_DISPUTABLE_MESSAGE = 'Este registro não pode ser contestado por aqui. Se ele tem o Selo da oficina, confirme que o veículo é seu enviando o CRLV-e para decidir sobre ele.';

    /**
     * Resultado de isCurrentOwner por conta e veículo, dentro da mesma requisição.
     *
     * @var array<string, bool>
     */
    private array $currentOwnerCache = [];

    public function __construct(private readonly MaintenanceAttachmentPurger $purger) {}

    /**
     * Registros de oficina dos veículos que a conta possui hoje.
     *
     * @return Builder<Maintenance>
     */
    public function recordsFor(User $owner, bool $onlyPending = true): Builder
    {
        return Maintenance::query()
            ->whereNotNull('owner_status')
            ->whereIn('vehicle_id', $owner->currentVehicles()->select('vehicles.id'))
            ->when($onlyPending, fn (Builder $query) => $query->where('owner_status', Maintenance::OWNER_PENDING))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id');
    }

    public function pendingCountFor(User $owner): int
    {
        return $this->recordsFor($owner)->count();
    }

    public function canDecide(User $owner, Maintenance $maintenance): bool
    {
        return $this->isCurrentOwner($owner, $maintenance) && $this->ownershipVerified($owner, $maintenance);
    }

    /**
     * Contestar não pede CRLV-e: basta ser o dono atual, e o registro ser de uma oficina
     * (Maintenance::isDisputable), com selo ou sem.
     */
    public function canDispute(User $owner, Maintenance $maintenance): bool
    {
        // isUser() antes de isCurrentOwner: contestar é do proprietário, e perguntar pela oficina ou
        // pelo lojista custaria uma consulta por veículo na listagem só para responder false.
        return $owner->isUser()
            && $maintenance->isDisputable()
            && $this->isCurrentOwner($owner, $maintenance);
    }

    public function canAcceptAttachments(User $owner, Maintenance $maintenance): bool
    {
        return $maintenance->attachments_status === Maintenance::ATTACHMENTS_PENDING
            && $this->ownershipVerified($owner, $maintenance);
    }

    public function ownershipVerified(User $owner, Maintenance $maintenance): bool
    {
        $link = $maintenance->vehicle?->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->where('users.id', $owner->id)
            ->first();

        return $link?->pivot?->ownership_verified_at !== null;
    }

    public function isCurrentOwner(User $owner, Maintenance $maintenance): bool
    {
        if ($maintenance->vehicle === null) {
            return false;
        }

        // Memo por (conta, veículo): numa listagem o resource pergunta por registro, e várias OS do
        // mesmo veículo repetiriam a mesma consulta de policy.
        $key = $owner->id.':'.$maintenance->vehicle_id;

        return $this->currentOwnerCache[$key] ??= $owner->can('update', $maintenance->vehicle);
    }

    /**
     * O dono atual diz que este serviço não aconteceu no carro dele.
     *
     * É a única ação que não exige o CRLV-e, e de propósito: ela não apaga nem esconde nada. O
     * registro continua em todas as superfícies, agora com o aviso de que o proprietário contesta —
     * o comprador fica com MAIS informação, não menos, e é isso que separa a contestação do ocultar
     * que existia antes. A oficina autora é avisada, porque é ela quem pode corrigir ou excluir, e
     * o caso entra na fila da equipe, que é quem pode esconder de fato se confirmar a fraude.
     *
     * Vale para qualquer OS de oficina, com selo ou sem: é a resposta do dono que chega depois e
     * encontra no chassi dele um serviço que não reconhece.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function dispute(Maintenance $maintenance, User $owner, ?string $reason = null): Maintenance
    {
        if (! $this->isCurrentOwner($owner, $maintenance)) {
            throw new AuthorizationException('Você não é o proprietário atual deste veículo.');
        }

        if (! $maintenance->isDisputable()) {
            throw ValidationException::withMessages(['dispute' => self::NOT_DISPUTABLE_MESSAGE]);
        }

        $maintenance->forceFill([
            'owner_disputed_at' => $maintenance->owner_disputed_at ?? now(),
            'owner_disputed_by_user_id' => $owner->id,
            'owner_dispute_reason' => $reason,
        ])->save();

        $maintenance = $maintenance->fresh();

        // Contestado sai da conta do hodômetro (Maintenance::scopeCountsTowardOdometer).
        $this->refreshOdometer($maintenance);

        $maintenance->loadMissing('workshop.user');
        $maintenance->getRelationValue('workshop')?->user
            ?->notify(new MaintenanceDisputedNotification($maintenance, $reason));

        return $maintenance;
    }

    /**
     * O dono volta atrás na contestação (era engano dele). O registro reaparece onde aparecia.
     *
     * @throws AuthorizationException
     */
    public function withdrawDispute(Maintenance $maintenance, User $owner): Maintenance
    {
        if (! $this->isCurrentOwner($owner, $maintenance)) {
            throw new AuthorizationException('Você não é o proprietário atual deste veículo.');
        }

        $maintenance->forceFill([
            'owner_disputed_at' => null,
            'owner_disputed_by_user_id' => null,
            'owner_dispute_reason' => null,
        ])->save();

        $maintenance = $maintenance->fresh();
        $this->refreshOdometer($maintenance);

        return $maintenance;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function decide(Maintenance $maintenance, User $owner, bool $link, bool $attachFiles): Maintenance
    {
        if (! $this->isCurrentOwner($owner, $maintenance)) {
            throw new AuthorizationException('Você não é o proprietário atual deste veículo.');
        }

        if (! $this->ownershipVerified($owner, $maintenance)) {
            throw ValidationException::withMessages(['link' => self::UNVERIFIED_DECISION_MESSAGE]);
        }

        if (! $maintenance->isOwnerlessRecord()) {
            throw ValidationException::withMessages(['link' => 'Este registro não está pendente de decisão.']);
        }

        if ($attachFiles && ! $link) {
            throw ValidationException::withMessages(['attach_files' => 'Para aceitar as notas e fotos, vincule o registro ao seu histórico.']);
        }

        if ($attachFiles && $maintenance->attachments_status !== Maintenance::ATTACHMENTS_ACCEPTED) {
            if ($maintenance->attachments_status !== Maintenance::ATTACHMENTS_PENDING) {
                throw ValidationException::withMessages(['attach_files' => 'Este registro não tem notas ou fotos pendentes.']);
            }

            if (! $this->ownershipVerified($owner, $maintenance)) {
                throw ValidationException::withMessages(['attach_files' => self::UNVERIFIED_MESSAGE]);
            }
        }

        $decided = DB::transaction(function () use ($maintenance, $owner, $link, $attachFiles): Maintenance {
            $updates = [
                'owner_status' => $link ? Maintenance::OWNER_LINKED : Maintenance::OWNER_DECLINED,
                'tenant_id' => $link ? $owner->tenant_id : null,
                'owner_decided_by_user_id' => $owner->id,
                'owner_decided_at' => now(),
            ];

            if ($link) {
                // Vincular é aceitar o registro: a contestação deixa de fazer sentido.
                $updates['owner_disputed_at'] = null;
                $updates['owner_disputed_by_user_id'] = null;
                $updates['owner_dispute_reason'] = null;
            }

            if ($attachFiles) {
                $updates['attachments_status'] = Maintenance::ATTACHMENTS_ACCEPTED;
            } elseif ($maintenance->attachments_status === Maintenance::ATTACHMENTS_PENDING) {
                $this->purger->purge($maintenance);
                $updates['attachments_status'] = Maintenance::ATTACHMENTS_DECLINED;
            } elseif ($maintenance->attachments_status === Maintenance::ATTACHMENTS_ACCEPTED) {
                $this->purger->purge($maintenance);
                $updates['attachments_status'] = Maintenance::ATTACHMENTS_REVOKED;
            }

            $maintenance->forceFill($updates)->save();

            if ($link && ! $maintenance->isVerified() && $maintenance->workshop_id !== null) {
                app(MaintenanceVerificationStamper::class)
                    ->sealAfterOwnerLink($maintenance, (int) $maintenance->workshop_id);
            }

            return $maintenance->fresh();
        });

        $this->refreshOdometer($maintenance);

        return $decided;
    }

    /**
     * Toda vez que muda se um registro conta para o hodômetro
     * (Maintenance::scopeCountsTowardOdometer), o current_kilometers do veículo tem de ser
     * recalculado na hora — senão fica parado no valor antigo até a próxima gravação qualquer.
     */
    private function refreshOdometer(Maintenance $maintenance): void
    {
        $maintenance->loadMissing('vehicle');
        $vehicle = $maintenance->getRelationValue('vehicle');

        if ($vehicle instanceof Vehicle) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle);
        }
    }
}
