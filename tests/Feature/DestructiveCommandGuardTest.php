<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Produção roda no Neon, e um migrate:fresh lá apagaria o histórico dos veículos. O
 * AppServiceProvider proíbe os comandos destrutivos quando o ambiente é produção.
 */
class DestructiveCommandGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A proibição é estado estático do framework: sem este reset, o migrate:fresh do
     * RefreshDatabase falharia em todos os testes seguintes do mesmo processo.
     */
    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    /**
     * @return list<array{string}>
     */
    public static function destructiveCommands(): array
    {
        return [
            ['db:wipe'],
            ['migrate:fresh'],
            ['migrate:refresh'],
            ['migrate:reset'],
            ['migrate:rollback'],
        ];
    }

    #[DataProvider('destructiveCommands')]
    public function test_production_refuses_destructive_commands(string $command): void
    {
        $this->bootProviderAsProduction();

        $this->artisan($command)->assertFailed();

        // O schema continua de pé: o comando parou antes de tocar no banco.
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('vehicles'));
    }

    public function test_production_marks_the_commands_as_prohibited(): void
    {
        $this->assertFalse($this->commandsAreProhibited());

        $this->bootProviderAsProduction();

        $this->assertTrue($this->commandsAreProhibited());
    }

    public function test_other_environments_keep_the_commands_available(): void
    {
        // O ambiente dos testes é "testing", e a suíte inteira depende disso: o RefreshDatabase
        // roda migrate:fresh antes de cada teste. Por isso só produção pode ser proibida.
        $this->assertFalse($this->app->isProduction());

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse($this->commandsAreProhibited());
    }

    private function bootProviderAsProduction(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        (new AppServiceProvider($this->app))->boot();
    }

    private function commandsAreProhibited(): bool
    {
        return (bool) (new ReflectionProperty(FreshCommand::class, 'prohibitedFromRunning'))->getValue();
    }
}
