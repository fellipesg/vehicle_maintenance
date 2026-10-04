<?php

namespace App\Enums;

/**
 * Situação de uma oficina prospectada (workshop_prospects.status).
 */
enum WorkshopProspectStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case FollowedUp = 'followed_up';
    case Clicked = 'clicked';
    case Replied = 'replied';
    case Converted = 'converted';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Sending => 'Enviando',
            self::Sent => 'Enviado',
            self::FollowedUp => 'Follow-up enviado',
            self::Clicked => 'Clicou',
            self::Replied => 'Respondeu',
            self::Converted => 'Convertida',
            self::Unsubscribed => 'Descadastrada',
            self::Bounced => 'Devolvido',
            self::Failed => 'Falhou',
            self::Skipped => 'Ignorada',
        };
    }

    /**
     * Cor do x-ui.badge.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Converted => 'success',
            self::Replied, self::Clicked => 'info',
            self::Unsubscribed, self::Bounced, self::Failed => 'danger',
            self::Sending, self::Skipped => 'warning',
            default => 'neutral',
        };
    }

    /**
     * Estados em que o convite já saiu e a pessoa ainda não reagiu.
     *
     * @return list<self>
     */
    public static function awaitingReaction(): array
    {
        return [self::Sent, self::FollowedUp];
    }
}
