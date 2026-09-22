<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ContactMessageRequest;
use App\Mail\ContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('contact', [
            'subjects' => ContactMessageRequest::SUBJECTS,
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    public function store(ContactMessageRequest $request): RedirectResponse
    {
        // Honeypot preenchido: finge sucesso para não ensinar o bot.
        if ($request->filled('website')) {
            return redirect()->route('contact.show')->with('success', 'Mensagem enviada. Responderemos em breve.');
        }

        $subject = $request->validated('subject');
        $recipient = $subject === 'privacy'
            ? config('legal.contact.privacy')
            : config('legal.contact.general');

        Mail::to($recipient)->send(new ContactMessageMail(
            senderName: $request->validated('name'),
            senderEmail: $request->validated('email'),
            subjectLabel: ContactMessageRequest::SUBJECTS[$subject],
            body: $request->validated('message'),
            userId: $request->user()?->id,
        ));

        return redirect()->route('contact.show')->with('success', 'Mensagem enviada. Responderemos em breve.');
    }
}
