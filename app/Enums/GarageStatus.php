<?php

namespace App\Enums;

/**
 * Situação do cadastro da loja (garages.status). A loja só escreve no histórico de veículos — e só
 * manda e-mail para proprietários — depois de aprovada: é o que separa uma garagem real de uma
 * conta recém-criada.
 */
enum GarageStatus: string
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
