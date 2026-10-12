<?php

namespace App\Services\Maintenance;

use App\Mail\CustomerInviteMail;
use App\Models\EmailSuppression;
use App\Models\Maintenance;
use App\Models\MaintenanceInvite;
use App\Models\Workshop;
use App\Support\Maintenance\CustomerInviteMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Convite ao cliente pela OS de um carro sem proprietário. WhatsApp só deixa a data (a oficina
 * envia do próprio celular e o telefone não é guardado). O e-mail sai pelo mailer padrão, na fila,
 * sob três limites: um por OS, o teto diário da oficina (maintenance.invite_email_daily_limit) e a
 * lista email_suppressions. O endereço não é guardado: só o hash.
 */
class MaintenanceInviteService
{
    public const SENT = 'sent';

    public const ALREADY_INVITED = 'already_invited';

    public const DAILY_LIMIT = 'daily_limit';

    public const SUPPRESSED = 'suppressed';

    /**
     * Só a oficina que fez a OS convida, e só enquanto o veículo não tem proprietário nem decisão.
     */
    public function canInvite(Maintenance $maintenance, Workshop $workshop): bool
    {
        return (int) $maintenance->workshop_id === (int) $workshop->id
            && $maintenance->owner_status === Maintenance::OWNER_PENDING
            && $maintenance->vehicle !== null
            && ! $maintenance->vehicle->hasCurrentOwner();
    }

    public function inviteFor(Maintenance $maintenance, Workshop $workshop): MaintenanceInvite
    {
        return MaintenanceInvite::query()->firstOrCreate(
            ['maintenance_id' => $maintenance->id],
            ['workshop_id' => $workshop->id, 'token' => MaintenanceInvite::newToken()],
        );
    }

    /**
     * URL wa.me com o texto pronto, ou null quando o telefone não parece brasileiro.
     *
     * @return array{url: string, whatsapp_invited_at: \Illuminate\Support\Carbon}|null
     */
    public function whatsappUrl(Maintenance $maintenance, Workshop $workshop, string $phone): ?array
    {
        $number = CustomerInviteMessage::whatsappNumber($phone);

        if ($number === null) {
            return null;
        }

        $invite = $this->inviteFor($maintenance, $workshop);
        $invite->forceFill(['whatsapp_invited_at' => now()])->save();

        $maintenance->loadMissing(['vehicle', 'workshop', 'verifiedWorkshop']);

        return [
            'url' => (new CustomerInviteMessage($maintenance, $invite))->whatsappUrl($number),
            'whatsapp_invited_at' => $invite->whatsapp_invited_at,
        ];
    }

    /**
     * @return array{status: string, invite: ?MaintenanceInvite}
     */
    public function sendEmail(Maintenance $maintenance, Workshop $workshop, string $email): array
    {
        $email = mb_strtolower(trim($email));

        if (EmailSuppression::isSuppressed($email)) {
            return ['status' => self::SUPPRESSED, 'invite' => null];
        }

        $invite = $this->inviteFor($maintenance, $workshop);

        $status = DB::transaction(function () use ($invite, $workshop, $email): string {
            $locked = MaintenanceInvite::query()->lockForUpdate()->findOrFail($invite->id);

            if ($locked->email_invited_at !== null) {
                return self::ALREADY_INVITED;
            }

            $sentToday = MaintenanceInvite::query()
                ->where('workshop_id', $workshop->id)
                ->where('email_invited_at', '>=', now()->subDay())
                ->count();

            if ($sentToday >= (int) config('maintenance.invite_email_daily_limit', 30)) {
                return self::DAILY_LIMIT;
            }

            $locked->forceFill([
                'email_hash' => MaintenanceInvite::hashEmail($email),
                'email_invited_at' => now(),
            ])->save();

            return self::SENT;
        });

        $invite = $invite->fresh();

        if ($status === self::SENT) {
            $maintenance->loadMissing(['vehicle', 'workshop', 'verifiedWorkshop']);
            Mail::to($email)->send(new CustomerInviteMail($maintenance, $invite, $email));
        }

        return ['status' => $status, 'invite' => $invite];
    }
}
