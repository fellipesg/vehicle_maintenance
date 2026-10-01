<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LegalDocument;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.document', [
            'title' => 'Termos de uso',
            'version' => config('legal.terms_version'),
            'document' => LegalDocument::fromText(config('legal.terms_of_use')),
            'relatedDocument' => ['label' => 'Política de privacidade', 'url' => route('legal.privacy')],
            'contactUrl' => route('contact.show'),
        ]);
    }

    public function privacy(): View
    {
        return view('legal.document', [
            'title' => 'Política de privacidade',
            'version' => config('legal.privacy_version'),
            'document' => LegalDocument::fromText(config('legal.privacy_policy')),
            'relatedDocument' => ['label' => 'Termos de uso', 'url' => route('legal.terms')],
            'contactUrl' => route('contact.show', ['assunto' => 'privacy']),
        ]);
    }
}
