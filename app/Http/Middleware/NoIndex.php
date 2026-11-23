<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tira login, cadastro e a área logada do índice. O crawler precisa ler a
 * página, então a rota não entra no robots.txt: o sinal é a meta e o header.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('seoNoIndex', true);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        return $response;
    }
}
