<?php

namespace App\Services\Garage;

use App\Enums\GarageStatus;
use App\Models\Garage;
use App\Models\User;
use App\Notifications\GarageReviewDecidedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Decisões sobre o cadastro de uma loja. Fica fora do controller porque a volta para análise também
 * acontece sozinha, quando o lojista troca o CNPJ em "Minha loja": aprovar o CNPJ A e deixar trocar
 * para B no dia seguinte transformaria a aprovação em teatro.
 */
class GarageReview
{
    public function approve(Garage $garage, User $reviewer): void
    {
        $this->decide($garage, GarageStatus::Approved, $reviewer, null);
    }

    public function reject(Garage $garage, User $reviewer, ?string $notes = null): void
    {
        $this->decide($garage, GarageStatus::Rejected, $reviewer, $notes);
    }

    /**
     * CNPJ novo, análise nova. Só mexe quando o documento realmente mudou e a loja já tinha sido
     * analisada; loja que ainda está em análise continua como está, sem avisar ninguém à toa.
     */
    public function resetForDocumentChange(Garage $garage): bool
    {
        if ($garage->status === GarageStatus::Pending) {
            return false;
        }

        $garage->forceFill([
            'status' => GarageStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ])->save();

        return true;
    }

    private function decide(Garage $garage, GarageStatus $status, User $reviewer, ?string $notes): void
    {
        DB::transaction(function () use ($garage, $status, $reviewer, $notes): void {
            $garage->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();
        });

        $garage->user?->notify(new GarageReviewDecidedNotification($garage->name, $status, $notes));
    }
}
