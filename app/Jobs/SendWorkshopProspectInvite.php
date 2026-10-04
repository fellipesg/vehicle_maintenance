<?php

namespace App\Jobs;

use App\Enums\WorkshopProspectStatus;
use App\Mail\WorkshopProspectInviteMail;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia o convite (ou o follow-up) a uma oficina prospectada pelo mailer de outreach, nunca pelo padrão.
 * Sem nova tentativa automática: um e-mail frio duplicado é pior que um perdido.
 */
class SendWorkshopProspectInvite implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $prospectId)
    {
        $this->onConnection('database');
    }

    public function uniqueId(): string
    {
        return (string) $this->prospectId;
    }

    public function handle(): void
    {
        $variant = null;
        $prospect = null;

        DB::transaction(function () use (&$variant, &$prospect): void {
            $prospect = WorkshopProspect::query()->lockForUpdate()->find($this->prospectId);

            if ($prospect === null) {
                return;
            }

            if (EmailSuppression::isSuppressed($prospect->email)) {
                if ($prospect->status === WorkshopProspectStatus::Pending) {
                    $prospect->update(['status' => WorkshopProspectStatus::Skipped]);
                }

                return;
            }

            if ($prospect->status === WorkshopProspectStatus::Pending) {
                $variant = WorkshopProspectInviteMail::FIRST_TOUCH;
            } elseif ($prospect->isFollowUpDue()) {
                $variant = WorkshopProspectInviteMail::FOLLOW_UP;
            } else {
                return;
            }

            $prospect->update(['status' => WorkshopProspectStatus::Sending, 'last_error' => null]);
        });

        if ($variant === null) {
            return;
        }

        try {
            Mail::mailer((string) config('outreach.mailer'))
                ->to($prospect->email)
                ->send(new WorkshopProspectInviteMail($prospect, $variant));
        } catch (Throwable $exception) {
            $prospect->update([
                'status' => WorkshopProspectStatus::Failed,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            return;
        }

        if ($variant === WorkshopProspectInviteMail::FOLLOW_UP) {
            $prospect->update(['status' => WorkshopProspectStatus::FollowedUp, 'follow_up_sent_at' => now()]);
        } else {
            $prospect->update(['status' => WorkshopProspectStatus::Sent, 'first_sent_at' => now()]);
        }
    }
}
