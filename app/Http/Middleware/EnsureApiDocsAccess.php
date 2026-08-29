<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api-docs.enabled')) {
            abort(404);
        }

        $username = config('api-docs.username');
        $password = config('api-docs.password');

        if ($username === null || $username === '' || $password === null || $password === '') {
            return $next($request);
        }

        $providedUsername = $request->getUser();
        $providedPassword = $request->getPassword();

        if ($providedUsername !== $username || $providedPassword !== $password) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Basic realm="API Documentation"',
            ]);
        }

        return $next($request);
    }
}
