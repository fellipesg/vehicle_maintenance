<?php

use App\Http\Middleware\CheckApiAbility;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SetSecurityHeaders::class);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckApiAbility::class,
            'user.type' => \App\Http\Middleware\EnsureUserType::class,
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
            'etag.vehicle_list' => \App\Http\Middleware\EtagForVehicleList::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        $apiRenderer = function (\Throwable $e, Request $request): ?\Illuminate\Http\JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::validation($e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', 401);
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error($e->getMessage() ?: 'Forbidden.', 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return ApiResponse::error('Resource not found.', 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return ApiResponse::error('Resource not found.', 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return ApiResponse::error('Method not allowed.', 405);
            }

            if ($e instanceof TooManyRequestsHttpException) {
                return ApiResponse::error('Too many requests.', 429);
            }

            if ($e instanceof HttpExceptionInterface) {
                $message = app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : match ($e->getStatusCode()) {
                        401 => 'Unauthenticated.',
                        403 => 'Forbidden.',
                        404 => 'Resource not found.',
                        405 => 'Method not allowed.',
                        429 => 'Too many requests.',
                        default => 'An error occurred.',
                    };

                return ApiResponse::error($message ?: 'An error occurred.', $e->getStatusCode());
            }

            if (app()->hasDebugModeEnabled()) {
                return null;
            }

            return ApiResponse::error('An error occurred.', 500);
        };

        $exceptions->render($apiRenderer);
    })->create();
