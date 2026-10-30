<?php

namespace Tests;

use App\Features\MaintenancePhotos;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Maintenance\MaintenancePhotoImageProcessor;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fixa o GD como driver de imagem da suíte.
     *
     * Sem isto o container escolhe o Imagick sempre que a extensão existe. Desde que o CI passou a
     * instalá-la, o pipeline de fotos resolvido pelo container rodaria num driver que a imagem de
     * produção não tem (o Dockerfile fica só com gd de propósito, .ai/rules/ci.md) — e o GD, que é
     * o que roda em produção, deixaria de ser exercitado pela camada de serviço. Os dois caminhos
     * seguem cobertos em Tests\Unit\MaintenancePhotoImageProcessorTest, que pede cada driver pelo
     * nome. Fixar aqui também faz a suíte rodar o mesmo código em toda máquina, com ou sem a
     * extensão instalada.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            MaintenancePhotoImageProcessor::class,
            fn (): MaintenancePhotoImageProcessor => new MaintenancePhotoImageProcessor(MaintenancePhotoImageProcessor::DRIVER_GD),
        );
    }

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
     * no banco de desenvolvimento. Detalhes em .ai/rules/ci.md.
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
            .'(vendor/bin/phpunit --configuration=phpunit.pgsql.xml; `artisan test` recusa a opção). '
            .'Dentro do Docker, confira se os DB_* estão repetidos em <server> na configuração do PHPUnit.',
        );
    }

    /**
     * Liga as fotos das manutenções para todas as contas do teste. As fotos são uma funcionalidade
     * desligada por padrão (App\Features\MaintenancePhotos); os testes que exercitam a foto em si,
     * e não a liberação por conta, chamam isto no setUp. Quem testa a liberação usa
     * Feature::for($conta)->activate(MaintenancePhotos::class).
     */
    protected function enableMaintenancePhotosForEveryone(): void
    {
        Feature::define((new MaintenancePhotos)->name, fn (): bool => true);
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
