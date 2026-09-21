<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenancePhotoPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WorkshopPolicy;
use App\Support\StorageEndpointResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const AUTH_PER_IP_PER_MINUTE = 30;

    public function register(): void
    {
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

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

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(
            (string) ($request->user()?->id ?: $request->ip()),
        ));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(20)->by(
            'search|'.$request->ip(),
        ));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by(
            'uploads|'.($request->user()?->id ?: $request->ip()),
        ));

        // The challenge payload carries challenge_token (not email): 5 guesses per
        // challenge, plus a per-IP cap across challenges.
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa|'.hash('sha256', (string) $request->input('challenge_token', '')).'|'.$request->ip()),
            Limit::perMinute(10)->by('2fa-ip|'.$request->ip()),
        ]);

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by(
            'contact|'.$request->ip(),
        ));

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(MaintenancePhoto::class, MaintenancePhotoPolicy::class);
        Gate::policy(Workshop::class, WorkshopPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        Storage::extend('s3', fn ($app, array $config) => $app['filesystem']->createS3Driver(
            StorageEndpointResolver::apply($config)
        ));
    }

    /**
     * Login is keyed on email+IP, so it also gets a per-IP cap to stop one IP
     * spraying many emails. Register and OAuth buckets are already per-IP.
     *
     * @return list<Limit>
     */
    private function buildAuthRateLimit(Request $request, callable $response): array
    {
        $limits = [
            Limit::perMinute(5)
                ->by($this->authRateLimitKey($request))
                ->response($response),
        ];

        if ($this->isLoginRequest($request)) {
            $limits[] = Limit::perMinute(self::AUTH_PER_IP_PER_MINUTE)
                ->by('login-ip|'.$request->ip())
                ->response($response);
        }

        return $limits;
    }

    private function isLoginRequest(Request $request): bool
    {
        return ! $request->is('api/v1/register', 'register', 'api/v1/auth/*/callback');
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
