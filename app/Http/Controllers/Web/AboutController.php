<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('about.show', [
            'authorBio' => trim((string) __('about.author_bio')),
        ]);
    }
}
