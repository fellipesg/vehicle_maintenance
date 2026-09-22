<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.document', [
            'title' => 'Termos de uso',
            'version' => config('legal.terms_version'),
            'content' => config('legal.terms_of_use'),
        ]);
    }

    public function privacy(): View
    {
        return view('legal.document', [
            'title' => 'Política de privacidade',
            'version' => config('legal.privacy_version'),
            'content' => config('legal.privacy_policy'),
        ]);
    }
}
