<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Models\MaintenanceInvite;
use App\Support\Maintenance\CustomerInviteMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Páginas públicas do convite que a oficina manda ao cliente (WhatsApp ou e-mail): /convite/{token}
 * e o descadastro do e-mail. Mínimas e noindex: mostram só oficina, marca/modelo/ano e data.
 */
class MaintenanceInviteController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invite = MaintenanceInvite::query()->where('token', $token)->first();
        abort_if($invite === null, 404);

        $maintenance = $invite->maintenance()->with(['vehicle', 'workshop', 'verifiedWorkshop'])->firstOrFail();
        $message = new CustomerInviteMessage($maintenance, $invite);

        if ($request->user() === null) {
            $request->session()->put('url.intended', route('user.vehicles.create'));
        }
        $request->session()->put('invite_notice', true);

        return view('invites.show', [
            'workshopName' => $message->workshopName(),
            'vehicleLabel' => $message->vehicleLabel(),
            'serviceDate' => $message->serviceDate(),
        ]);
    }

    public function showUnsubscribe(): View
    {
        return view('invites.unsubscribe', ['done' => false]);
    }

    /**
     * Idempotente e sem CSRF (um clique do provedor de e-mail); a URL assinada protege.
     */
    public function unsubscribe(Request $request): View
    {
        try {
            $email = Crypt::decryptString((string) $request->query('payload'));
        } catch (DecryptException) {
            abort(404);
        }

        EmailSuppression::suppress($email, EmailSuppression::REASON_UNSUBSCRIBED);

        return view('invites.unsubscribe', ['done' => true]);
    }
}
