<?php

namespace App\Services\Workshop;

use App\Enums\WorkshopStatus;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\WorkshopApprovalDecidedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Decisões sobre o cadastro de uma oficina, no mesmo desenho de App\Services\Garage\GarageReview.
 *
 * Sem o resetForDocumentChange que o lojista tem: o CNPJ da oficina vem do cadastro
 * (WorkshopSignupService) e "Minha oficina" não o edita, então não existe o "aprovar o CNPJ A e
 * trocar para B" que obrigaria a refazer a análise. Se o campo virar editável, copie o do lojista —
 * há um teste guardando essa suposição (WorkshopApprovalGateTest::test_the_form_cannot_change_the_cnpj).
 */
class WorkshopReview
{
    public function approve(Workshop $workshop, User $reviewer): void
    {
        $this->decide($workshop, WorkshopStatus::Approved, $reviewer, null);
    }

    public function reject(Workshop $workshop, User $reviewer, ?string $notes = null): void
    {
        $this->decide($workshop, WorkshopStatus::Rejected, $reviewer, $notes);
    }

    private function decide(Workshop $workshop, WorkshopStatus $status, User $reviewer, ?string $notes): void
    {
        DB::transaction(function () use ($workshop, $status, $reviewer, $notes): void {
            $workshop->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();
        });

        $workshop->user?->notify(new WorkshopApprovalDecidedNotification($workshop->name, $status, $notes));
    }
}
