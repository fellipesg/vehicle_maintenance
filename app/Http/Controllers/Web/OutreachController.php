<?php

namespace App\Http\Controllers\Web;

use App\Enums\WorkshopProspectStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Links do e-mail de prospecção: clique (leva ao contato de parceria com a origem) e descadastro.
 */
class OutreachController extends Controller
{
    public function click(string $token): RedirectResponse
    {
        $prospect = WorkshopProspect::query()->where('token', $token)->first();

        if ($prospect === null) {
            return redirect()->route('contact.show', ['assunto' => 'partnership']);
        }

        $prospect->clicked_at ??= now();

        if (in_array($prospect->status, WorkshopProspectStatus::awaitingReaction(), true)) {
            $prospect->status = WorkshopProspectStatus::Clicked;
        }

        $prospect->save();

        return redirect()->route('contact.show', ['assunto' => 'partnership', 'ref' => $prospect->token]);
    }

    public function showUnsubscribe(string $token): View
    {
        return view('outreach.unsubscribe', ['token' => $token, 'done' => false]);
    }

    /**
     * Idempotente e sem CSRF (RFC 8058: o provedor de e-mail faz o POST sozinho). A URL assinada é a proteção.
     */
    public function unsubscribe(string $token): View
    {
        $prospect = WorkshopProspect::query()->where('token', $token)->first();

        if ($prospect !== null) {
            EmailSuppression::suppress($prospect->email, EmailSuppression::REASON_UNSUBSCRIBED);

            if ($prospect->status !== WorkshopProspectStatus::Unsubscribed) {
                $prospect->update([
                    'status' => WorkshopProspectStatus::Unsubscribed,
                    'unsubscribed_at' => $prospect->unsubscribed_at ?? now(),
                ]);
            }
        }

        return view('outreach.unsubscribe', ['token' => $token, 'done' => true]);
    }
}
