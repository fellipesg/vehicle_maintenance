<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Notifications\ConsignmentHistoryAccessRequestedNotification;
use App\Notifications\ConsignmentMaintenanceRegisteredNotification;
use App\Notifications\VehicleConsignmentStartedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles the lifecycle of a consigned vehicle: a car a garage holds for sale on
 * behalf of its owner.
 *
 * Declaring the consignment is enough to register maintenances, because the garage
 * is reporting a service it paid for and the owner is notified about it. Reading the
 * history the vehicle already had stays locked behind an approval.
 */
class VehicleConsignmentService
{
    /**
     * @param  array{
     *     owner_name: string,
     *     owner_email?: string|null,
     *     owner_phone?: string|null,
     *     owner_document?: string|null,
     *     declaration_ip?: string|null,
     *     declaration_user_agent?: string|null,
     * }  $data
     */
    public function start(
        User $garage,
        Vehicle $vehicle,
        array $data,
        ?string $powerOfAttorneyPath = null,
    ): VehicleConsignment {
        if (! $garage->isGarage()) {
            throw new RuntimeException('Apenas garagens podem registrar veículos em consignação.');
        }

        if ($this->activeFor($garage, $vehicle) !== null) {
            throw new RuntimeException('Este veículo já está em consignação nesta garagem.');
        }

        $consignment = DB::transaction(function () use ($garage, $vehicle, $data, $powerOfAttorneyPath) {
            return VehicleConsignment::create([
                'vehicle_id' => $vehicle->id,
                'garage_user_id' => $garage->id,
                'tenant_id' => $garage->tenant_id,
                'owner_user_id' => $this->resolveOwnerUser($vehicle, $data['owner_email'] ?? null)?->id,
                'owner_name' => $data['owner_name'],
                'owner_email' => $data['owner_email'] ?? null,
                'owner_phone' => $data['owner_phone'] ?? null,
                'owner_document' => $data['owner_document'] ?? null,
                'declaration_accepted_at' => now(),
                'declaration_ip' => $data['declaration_ip'] ?? null,
                'declaration_user_agent' => $data['declaration_user_agent'] ?? null,
                'power_of_attorney_path' => $powerOfAttorneyPath,
                'history_access_status' => $powerOfAttorneyPath !== null
                    ? VehicleConsignment::HISTORY_PENDING
                    : VehicleConsignment::HISTORY_NONE,
                'status' => VehicleConsignment::STATUS_ACTIVE,
                'history_requested_at' => $powerOfAttorneyPath !== null ? now() : null,
                'started_at' => now(),
                'owner_action_token' => Str::random(48),
            ]);
        });

        $this->notifyOwner($consignment, new VehicleConsignmentStartedNotification($consignment));

        return $consignment;
    }

    public function announceMaintenance(VehicleConsignment $consignment, Maintenance $maintenance): void
    {
        $this->notifyOwner(
            $consignment,
            new ConsignmentMaintenanceRegisteredNotification($consignment, $maintenance),
        );
    }

    public function requestHistoryAccess(VehicleConsignment $consignment): VehicleConsignment
    {
        if ($consignment->grantsHistoryAccess()) {
            throw new RuntimeException('O histórico deste veículo já está liberado.');
        }

        if (! $consignment->allowsMaintenance()) {
            throw new RuntimeException('Esta consignação não está ativa.');
        }

        $consignment->update([
            'history_access_status' => VehicleConsignment::HISTORY_PENDING,
            'history_requested_at' => now(),
        ]);

        $this->notifyOwner($consignment, new ConsignmentHistoryAccessRequestedNotification($consignment));

        return $consignment->fresh();
    }

    /**
     * The owner approving from the e-mail beats a power of attorney a human has to read:
     * it is faster and it is consent from the person the history belongs to.
     */
    public function approveHistoryAccess(VehicleConsignment $consignment, string $via, ?User $reviewer = null): VehicleConsignment
    {
        if (! in_array($via, ['owner', 'staff'], true)) {
            throw new RuntimeException('Origem de aprovação inválida.');
        }

        $consignment->update([
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'history_approved_via' => $via,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        return $consignment->fresh();
    }

    public function rejectHistoryAccess(VehicleConsignment $consignment, ?User $reviewer = null, ?string $notes = null): VehicleConsignment
    {
        $consignment->update([
            'history_access_status' => VehicleConsignment::HISTORY_REJECTED,
            'history_approved_via' => null,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $consignment->fresh();
    }

    /**
     * The owner saying "I did not authorise this" freezes the garage immediately; staff
     * decide afterwards whether to revoke the consignment for good.
     */
    public function dispute(VehicleConsignment $consignment, ?string $note = null): VehicleConsignment
    {
        $consignment->update([
            'owner_disputed_at' => now(),
            'owner_dispute_note' => $note,
        ]);

        return $consignment->fresh();
    }

    public function clearDispute(VehicleConsignment $consignment): VehicleConsignment
    {
        $consignment->update([
            'owner_disputed_at' => null,
            'owner_dispute_note' => null,
        ]);

        return $consignment->fresh();
    }

    /**
     * Reaches the owner through their account when they have one, and by e-mail otherwise —
     * a consigned vehicle very often belongs to someone who never heard of us.
     */
    private function notifyOwner(VehicleConsignment $consignment, object $notification): void
    {
        $consignment->loadMissing(['ownerUser', 'garageUser', 'vehicle']);

        if ($consignment->ownerUser !== null) {
            $consignment->ownerUser->notify($notification);
            $consignment->forceFill(['owner_notified_at' => now()])->save();

            return;
        }

        $email = $consignment->owner_email;

        if ($email === null || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
        $consignment->forceFill(['owner_notified_at' => now()])->save();
    }

    /**
     * Ending a consignment revokes the garage's access but keeps every maintenance it
     * registered: that history is what makes the vehicle worth more.
     */
    public function end(VehicleConsignment $consignment, string $reason): VehicleConsignment
    {
        if (! in_array($reason, ['sold', 'owner_withdrew', 'revoked'], true)) {
            throw new RuntimeException('Motivo de encerramento inválido.');
        }

        if (! $consignment->isActive()) {
            return $consignment;
        }

        return DB::transaction(function () use ($consignment, $reason) {
            DB::table('user_vehicles')
                ->where('user_id', $consignment->garage_user_id)
                ->where('vehicle_id', $consignment->vehicle_id)
                ->where('ownership_type', 'consignment')
                ->delete();

            $consignment->update([
                'status' => VehicleConsignment::STATUS_ENDED,
                'ended_at' => now(),
                'end_reason' => $reason,
            ]);

            return $consignment->fresh();
        });
    }

    public function activeFor(User $garage, Vehicle $vehicle): ?VehicleConsignment
    {
        return VehicleConsignment::query()
            ->active()
            ->where('garage_user_id', $garage->id)
            ->where('vehicle_id', $vehicle->id)
            ->first();
    }

    /**
     * The owner may already have an account — either because the vehicle is registered to
     * them or because the e-mail the garage typed matches one. Linking it lets us notify
     * them in-app instead of only by e-mail.
     */
    private function resolveOwnerUser(Vehicle $vehicle, ?string $email): ?User
    {
        $currentOwner = $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->first();

        if ($currentOwner !== null && ! $currentOwner->isGarage()) {
            return $currentOwner;
        }

        if ($email === null || $email === '') {
            return null;
        }

        return User::where('email', $email)->first();
    }
}
