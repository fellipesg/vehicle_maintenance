<?php

namespace App\Services\Maintenance;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\WorkshopReviewDecidedNotification;
use App\Notifications\WorkshopReviewRequestedNotification;
use App\Services\FcmService;
use Illuminate\Support\Facades\DB;

/**
 * Validação, pela oficina citada, de uma manutenção que o cliente declarou:
 *
 * - requestReview: a declarada cita uma oficina da rede → pending, e a conta da oficina é avisada;
 * - confirm: a oficina confirma → Selo da oficina (verification_method = confirmed); a OS passa a
 *   ser da oficina (MaintenancePolicy) e o cliente é avisado;
 * - reject: a oficina não reconhece → o vínculo sai (workshop_id nulo, o nome digitado fica) e o
 *   cliente é avisado. A mesma oficina não pode ser escolhida de novo para essa manutenção.
 */
class WorkshopReviewService
{
    public function __construct(
        private readonly MaintenanceVerificationStamper $stamper,
    ) {}

    /**
     * Chamado depois de salvar uma declarada (criação ou edição). Só avisa a oficina quando o
     * pedido é novo: outra oficina escolhida, ou a primeira.
     */
    public function syncAfterDeclaration(Maintenance $maintenance, ?int $previousWorkshopId = null): void
    {
        if ($maintenance->isVerified()) {
            return;
        }

        if ($maintenance->workshop_id === null) {
            if ($maintenance->workshop_review_status === WorkshopReviewStatus::Pending) {
                $maintenance->forceFill(['workshop_review_status' => null])->save();
            }

            return;
        }

        $isNewRequest = $previousWorkshopId === null
            || (int) $previousWorkshopId !== (int) $maintenance->workshop_id
            || $maintenance->workshop_review_status !== WorkshopReviewStatus::Pending;

        if ($isNewRequest) {
            $this->requestReview($maintenance);
        }
    }

    public function requestReview(Maintenance $maintenance): void
    {
        $maintenance->forceFill([
            'workshop_review_status' => WorkshopReviewStatus::Pending,
            'workshop_review_requested_at' => now(),
            'workshop_review_reminded_at' => null,
            'workshop_reviewed_at' => null,
            'workshop_reviewed_by' => null,
            'workshop_review_note' => null,
        ])->save();

        $workshopUser = Workshop::query()->with('user')->find($maintenance->workshop_id)?->user;

        if ($workshopUser === null) {
            return;
        }

        $notification = new WorkshopReviewRequestedNotification($maintenance->loadMissing('vehicle'));
        $workshopUser->notify($notification);

        $this->push($workshopUser, $notification->title(), $notification->body(), [
            'type' => 'workshop-review-requested',
            'maintenance_id' => (string) $maintenance->id,
        ]);
    }

    public function confirm(Maintenance $maintenance, User $workshopUser): Maintenance
    {
        $workshop = $workshopUser->workshop;

        $maintenance = DB::transaction(function () use ($maintenance, $workshopUser, $workshop): Maintenance {
            $sealed = $this->stamper->confirmDeclared($maintenance, (int) $workshop->id);

            $sealed->forceFill([
                'workshop_review_status' => WorkshopReviewStatus::Confirmed,
                'workshop_reviewed_at' => now(),
                'workshop_reviewed_by' => $workshopUser->id,
                'workshop_review_note' => null,
            ])->save();

            return $sealed;
        });

        $this->notifyDeclarant($maintenance, WorkshopReviewStatus::Confirmed, $workshop->name);

        return $maintenance;
    }

    public function reject(Maintenance $maintenance, User $workshopUser, ?string $note = null): Maintenance
    {
        $workshop = $workshopUser->workshop;
        $note = filled($note) ? trim((string) $note) : null;

        $maintenance->forceFill([
            'workshop_review_status' => WorkshopReviewStatus::Rejected,
            'workshop_reviewed_at' => now(),
            'workshop_reviewed_by' => $workshopUser->id,
            'workshop_review_note' => $note,
            'rejected_workshop_id' => $workshop->id,
            'workshop_id' => null,
        ])->save();

        $this->notifyDeclarant($maintenance, WorkshopReviewStatus::Rejected, $workshop->name, $note);

        return $maintenance->fresh();
    }

    private function notifyDeclarant(Maintenance $maintenance, WorkshopReviewStatus $status, string $workshopName, ?string $note = null): void
    {
        $declarant = $maintenance->user;

        if ($declarant === null) {
            return;
        }

        $notification = new WorkshopReviewDecidedNotification($maintenance, $status, $workshopName, $note);
        $declarant->notify($notification);

        $this->push($declarant, $notification->title(), $notification->body(), [
            'type' => 'workshop-review-decided',
            'status' => $status->value,
            'maintenance_id' => (string) $maintenance->id,
            'vehicle_id' => (string) $maintenance->vehicle_id,
        ]);
    }

    /**
     * Push no app (FCM). Sem token cadastrado não envia nada; falha do Firebase não derruba a
     * validação.
     *
     * @param  array<string, string>  $data
     */
    private function push(User $user, string $title, string $body, array $data): void
    {
        try {
            app(FcmService::class)->sendToUser($user->id, $title, $body, $data);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
