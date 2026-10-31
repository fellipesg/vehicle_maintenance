<?php

namespace App\Enums;

/**
 * Situação do cadastro da oficina (workshops.status). A oficina escreve no histórico permanente de
 * um chassi **com o Selo da oficina** e manda convite por e-mail e WhatsApp para terceiros: o
 * mesmo motivo que fez a loja ter análise vale mais para ela, não menos (ver GarageStatus e
 * App\Http\Middleware\EnsureWorkshopApproved).
 */
enum WorkshopStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Em análise',
            self::Approved => 'Aprovada',
            self::Rejected => 'Recusada',
        };
    }

    /**
     * Variante do <x-ui.badge>.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
