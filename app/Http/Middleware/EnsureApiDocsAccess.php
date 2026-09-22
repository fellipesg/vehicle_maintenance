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

        $username = (string) config('api-docs.username', '');
        $password = (string) config('api-docs.password', '');

        if ($username === '' || $password === '') {
            // Never expose the docs publicly in production without credentials.
            abort_if(app()->isProduction(), 404);

            return $next($request);
        }

        $usernameMatches = hash_equals($username, (string) $request->getUser());
        $passwordMatches = hash_equals($password, (string) $request->getPassword());

        if (! $usernameMatches || ! $passwordMatches) {
            return response('Unauthorized.', 401, [
                'WWW-Authenticate' => 'Basic realm="API Documentation"',
            ]);
        }

        return $next($request);
    }
}
