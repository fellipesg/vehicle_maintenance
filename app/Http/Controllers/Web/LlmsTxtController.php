<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Seo\LlmsTxt;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        return response(LlmsTxt::body(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
