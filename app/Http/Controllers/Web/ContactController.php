<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreContactRequest;
use App\Mail\ContactMessageMail;
use App\Models\WorkshopProspect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('legal.contact', [
            'supportEmail' => config('legal.support_email'),
            'subjects' => StoreContactRequest::SUBJECTS,
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'ref' => is_string(request()->query('ref')) ? mb_substr(request()->query('ref'), 0, 64) : null,
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()
                ->route('contact.show')
                ->with('success', 'Mensagem enviada. Responderemos em breve.');
        }

        $validated = $request->safe()->only(['name', 'email', 'subject', 'message']);

        $prospect = $this->prospectFromRef($request->input('ref'));
        $prospect?->markReplied();

        Mail::to((string) config('legal.support_email'))
            ->send(new ContactMessageMail(
                name: $validated['name'],
                email: $validated['email'],
                body: $validated['message'],
                topic: StoreContactRequest::SUBJECTS[$validated['subject']],
                origin: $prospect === null
                    ? null
                    : "convite por e-mail · {$prospect->displayName()} · CNPJ {$prospect->formattedCnpj()}",
            ));

        return redirect()
            ->route('contact.show')
            ->with('success', 'Mensagem enviada. Responderemos em breve.');
    }

    private function prospectFromRef(mixed $ref): ?WorkshopProspect
    {
        if (! is_string($ref) || $ref === '') {
            return null;
        }

        return WorkshopProspect::query()->where('token', $ref)->first();
    }
}
