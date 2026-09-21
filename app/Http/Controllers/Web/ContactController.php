<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreContactRequest;
use App\Mail\ContactMessageMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('legal.contact', [
            'supportEmail' => config('legal.support_email'),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()
                ->route('contact.show')
                ->with('success', 'Mensagem enviada. Responderemos em breve.');
        }

        $validated = $request->safe()->only(['name', 'email', 'message']);

        Mail::to((string) config('legal.support_email'))
            ->send(new ContactMessageMail(
                name: $validated['name'],
                email: $validated['email'],
                body: $validated['message'],
            ));

        return redirect()
            ->route('contact.show')
            ->with('success', 'Mensagem enviada. Responderemos em breve.');
    }
}
