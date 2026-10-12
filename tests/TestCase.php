<?php

namespace Tests;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roda depois de a aplicação subir e **antes** do RefreshDatabase, que é quem faz o
     * migrate:fresh. É o último ponto em que ainda dá para impedir que a suíte apague um banco que
     * não é dela.
     */
    protected function setUpTraits()
    {
        $this->guardAgainstTestingOnARealDatabase();

        return parent::setUpTraits();
    }

    /**
     * A suíte só pode rodar no sqlite em memória (phpunit.xml) ou num banco terminado em "_test"
     * (phpunit.pgsql.xml usa vehicle_maintenance_test). Qualquer outro destino para o teste aqui,
     * antes de o RefreshDatabase dropar as tabelas.
     *
     * Existe porque o compose exporta DB_* para $_SERVER, que o Laravel lê antes de $_ENV: já
     * aconteceu de uma configuração do PHPUnit ser ignorada e a suíte rodar — e dar migrate:fresh —
     * no banco de desenvolvimento. Detalhes em .ai/rules/locale.md.
     */
    private function guardAgainstTestingOnARealDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $url = (string) config("database.connections.{$connection}.url");

        if ($connection === 'sqlite' && ($database === ':memory:' || $database === '')) {
            return;
        }

        if ($url === '' && str_ends_with($database, '_test')) {
            return;
        }

        throw new RuntimeException(
            'Os testes iam rodar na conexão "'.$connection.'" apontando para "'.($url !== '' ? $url : $database).'", '
            .'que não é um banco de teste — o RefreshDatabase apagaria os dados. '
            .'Use sqlite :memory: (phpunit.xml) ou um banco terminado em "_test" '
            .'(php artisan test --configuration=phpunit.pgsql.xml). '
            .'Dentro do Docker, confira se os DB_* estão repetidos em <server> na configuração do PHPUnit.',
        );
    }

    protected function fakeCoversDisk(string $disk = 'covers'): void
    {
        Config::set('filesystems.covers_disk', $disk);
        Storage::fake($disk);
        Config::set("filesystems.disks.{$disk}.driver", 'local');
    }

    protected function configurePublicCoversDisk(string $publicBase = 'https://cdn.example.test/vehicle-maintenance'): void
    {
        Config::set('filesystems.covers_disk', 'r2');
        Config::set('filesystems.disks.r2', [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://fake-account.r2.cloudflarestorage.com',
            'url' => $publicBase,
            'visibility' => 'public',
            'use_path_style_endpoint' => true,
            'throw' => false,
        ]);
    }

    protected function actingAsApiUser(?User $user = null): User
    {
        $user = $user ?? User::factory()->asUser()->create();
        $user->refresh();
        Sanctum::actingAs($user, SanctumMobileToken::ABILITIES);

        return $user;
    }

    protected function attachVehicleToUser(User $user, Vehicle $vehicle): void
    {
        $user->refresh();

        $user->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    protected function countQueries(callable $callback): int
    {
        $queryCount = 0;

        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $callback();

        return $queryCount;
    }
}
