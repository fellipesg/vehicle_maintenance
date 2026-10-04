<?php

namespace App\Enums;

/**
 * Validação, pela oficina citada, de uma manutenção declarada pelo cliente
 * (maintenances.workshop_review_status). Sem oficina citada, o status é nulo.
 */
enum WorkshopReviewStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando validação da oficina',
            self::Confirmed => 'Confirmada pela oficina',
            self::Rejected => 'Não reconhecida pela oficina',
        };
    }
}
