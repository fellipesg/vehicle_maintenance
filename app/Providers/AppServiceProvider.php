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
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(Workshop::class, WorkshopPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        Storage::extend('s3', fn ($app, array $config) => $app['filesystem']->createS3Driver(
            StorageEndpointResolver::apply($config)
        ));
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
