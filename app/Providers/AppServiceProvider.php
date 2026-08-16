<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WorkshopPolicy;
use App\Support\StorageEndpointResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && $this->requestIsHttps()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('auth', fn (Request $request) => $this->buildAuthRateLimit(
            $request,
            fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again later.',
            ], 429, $headers),
        ));

        RateLimiter::for('auth-web', fn (Request $request) => $this->buildAuthRateLimit(
            $request,
            fn (Request $request, array $headers) => redirect()
                ->back()
                ->withInput($request->only('email', 'name', 'phone', 'document'))
                ->withErrors([
                    'email' => 'Too many attempts. Please try again later.',
                ]),
        ));

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(Workshop::class, WorkshopPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        Storage::extend('s3', fn ($app, array $config) => $app['filesystem']->createS3Driver(
            StorageEndpointResolver::apply($config)
        ));
    }

    private function buildAuthRateLimit(Request $request, callable $response): Limit
    {
        return Limit::perMinute(5)
            ->by($this->authRateLimitKey($request))
            ->response($response);
    }

    private function authRateLimitKey(Request $request): string
    {
        if ($request->is('api/v1/register', 'register')) {
            return 'register|'.$request->ip();
        }

        if ($request->is('api/v1/auth/*/callback')) {
            $provider = (string) $request->route('provider', 'unknown');

            return 'oauth:'.$provider.'|'.$request->ip();
        }

        $email = strtolower((string) $request->input('email', ''));

        return 'login:'.$email.'|'.$request->ip();
    }

    private function requestIsHttps(): bool
    {
        $request = request();

        if ($request->isSecure()) {
            return true;
        }

        $forwardedProto = $request->header('X-Forwarded-Proto', '');

        return str_contains(strtolower((string) $forwardedProto), 'https');
    }
}
