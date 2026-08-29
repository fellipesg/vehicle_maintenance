<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;

class CheckApiAbility
{
    public function __construct(private readonly CheckForAnyAbility $checkForAnyAbility) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user !== null && $user->currentAccessToken() === null) {
            return $next($request);
        }

        return $this->checkForAnyAbility->handle($request, $next, ...$abilities);
    }
}
