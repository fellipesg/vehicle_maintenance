<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
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
 * - hide_from_public: tira a OS da consulta pública e do histórico de terceiros (direito de oposição).
 *
 * Pode ser chamada de novo para mudar o ocultar ou revogar o consentimento dos anexos.
 */
class MaintenanceOwnerDecisionService
{
    public const UNVERIFIED_MESSAGE = 'Para aceitar as notas e fotos da oficina, confirme que o veículo é seu enviando o CRLV-e.';

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
        return $maintenance->vehicle !== null && $owner->can('update', $maintenance->vehicle);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function decide(Maintenance $maintenance, User $owner, bool $link, bool $attachFiles, bool $hideFromPublic): Maintenance
    {
        if (! $this->isCurrentOwner($owner, $maintenance)) {
            throw new AuthorizationException('Você não é o proprietário atual deste veículo.');
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

        return DB::transaction(function () use ($maintenance, $owner, $link, $attachFiles, $hideFromPublic): Maintenance {
            $updates = [
                'owner_status' => $link ? Maintenance::OWNER_LINKED : Maintenance::OWNER_DECLINED,
                'tenant_id' => $link ? $owner->tenant_id : null,
                'hidden_from_public_at' => $hideFromPublic ? ($maintenance->hidden_from_public_at ?? now()) : null,
                'owner_decided_by_user_id' => $owner->id,
                'owner_decided_at' => now(),
            ];

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

            return $maintenance->fresh();
        });
    }
}
