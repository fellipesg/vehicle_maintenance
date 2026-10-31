<?php

namespace App\Support\Workshop;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\WorkshopMessageOptOut;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Descadastro das mensagens que a oficina manda ao dono do veículo (WorkshopFollowUpNotification
 * e o push de WorkshopMessageTemplateDispatcher). Consultado antes de todo envio.
 *
 * A granularidade é a da tabela workshop_message_opt_outs: uma linha por destinatário e oficina,
 * e workshop_id nulo vale para todas as oficinas. Não há coluna de canal de propósito — uma linha
 * cala e-mail, aviso no app e push da mesma oficina, porque quem pede para parar de receber não
 * está pedindo para trocar de canal. A lista permanente email_suppressions continua valendo só
 * para o canal de e-mail, como no convite da OS (MaintenanceInviteService).
 */
class WorkshopMessageConsent
{
    public const SCOPE_WORKSHOP = 'workshop';

    public const SCOPE_ALL = 'all';

    /**
     * Esta oficina ainda pode mandar mensagem a este destinatário?
     */
    public function allows(int $userId, ?int $workshopId): bool
    {
        return ! WorkshopMessageOptOut::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($workshopId): void {
                $query->whereNull('workshop_id');

                if ($workshopId !== null) {
                    $query->orWhere('workshop_id', $workshopId);
                }
            })
            ->exists();
    }

    /**
     * Além de allows(): o canal de e-mail exige que o endereço não esteja na supressão permanente
     * (email_suppressions), a mesma lista que barra o convite da OS e a prospecção.
     */
    public function allowsEmailTo(User $user): bool
    {
        $email = is_string($user->email) ? trim($user->email) : '';

        return $email !== '' && ! EmailSuppression::isSuppressed($email);
    }

    /**
     * Idempotente: o clique de um clique pode chegar mais de uma vez do provedor de e-mail.
     */
    public function optOut(int $userId, ?int $workshopId): WorkshopMessageOptOut
    {
        return WorkshopMessageOptOut::query()->firstOrCreate([
            'user_id' => $userId,
            'workshop_id' => $workshopId,
        ]);
    }

    /**
     * URL assinada de descadastro, com destinatário e oficina criptografados na query.
     */
    public function unsubscribeUrl(int $userId, ?int $workshopId): string
    {
        return URL::signedRoute('workshops.messages.unsubscribe.show', [
            'payload' => $this->encodePayload($userId, $workshopId),
        ]);
    }

    public function encodePayload(int $userId, ?int $workshopId): string
    {
        return Crypt::encryptString((string) json_encode([
            'user_id' => $userId,
            'workshop_id' => $workshopId,
        ]));
    }

    /**
     * @return array{user_id: int, workshop_id: int|null}|null
     */
    public function decodePayload(?string $payload): ?array
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['user_id'])) {
            return null;
        }

        $workshopId = $decoded['workshop_id'] ?? null;

        return [
            'user_id' => (int) $decoded['user_id'],
            'workshop_id' => $workshopId === null ? null : (int) $workshopId,
        ];
    }
}
