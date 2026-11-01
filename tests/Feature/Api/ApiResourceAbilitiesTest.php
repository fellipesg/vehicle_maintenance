<?php

namespace Tests\Feature\Api;

use App\Enums\WarrantyScope;
use App\Enums\WorkshopMessageTrigger;
use App\Models\User;
use App\Models\WarrantyTemplate;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ability por método nos apiResource de oficina.
 *
 * `->middleware(['index' => ..., 'store' => ...])` ignora as chaves e aplica todas as entradas em
 * todas as rotas do recurso, então os dois recursos exigiam `workshops:read` *e* `workshops:write`
 * em cada rota. O token do app traz as duas (SanctumMobileToken::ABILITIES), então nenhum teste de
 * comportamento com token cheio enxergava isso: daí a leitura direta das rotas aqui, somada aos
 * casos de token com uma ability só.
 */
class ApiResourceAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function resourceRouteProvider(): array
    {
        $cases = [];

        foreach (['workshops.message-templates', 'workshops.warranty-templates'] as $resource) {
            foreach (['index' => 'read', 'show' => 'read', 'store' => 'write', 'update' => 'write', 'destroy' => 'write'] as $action => $ability) {
                $cases["{$resource}.{$action}"] = ["{$resource}.{$action}", "ability:workshops:{$ability}"];
            }
        }

        return $cases;
    }

    #[DataProvider('resourceRouteProvider')]
    public function test_each_resource_route_carries_only_its_own_ability(string $routeName, string $expected): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "A rota {$routeName} não existe.");

        $abilities = array_values(array_filter(
            $route->gatherMiddleware(),
            static fn ($middleware): bool => is_string($middleware) && str_starts_with($middleware, 'ability:'),
        ));

        $this->assertSame([$expected], $abilities);
    }

    public function test_a_read_only_token_lists_but_does_not_write_message_templates(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create();

        Sanctum::actingAs($user, ['workshops:read']);

        $this->getJson("/api/v1/workshops/{$user->workshop->id}/message-templates")->assertOk();
        $this->getJson("/api/v1/workshops/{$user->workshop->id}/message-templates/{$template->id}")->assertOk();

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/message-templates", [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão',
            'body' => 'Olá.',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/workshops/{$user->workshop->id}/message-templates/{$template->id}")->assertForbidden();

        $this->assertDatabaseHas('workshop_message_templates', ['id' => $template->id]);
    }

    public function test_a_write_only_token_writes_but_does_not_list_message_templates(): void
    {
        $user = User::factory()->asWorkshop()->create();

        Sanctum::actingAs($user, ['workshops:write']);

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/message-templates", [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão',
            'body' => 'Olá.',
        ])->assertCreated();

        $this->getJson("/api/v1/workshops/{$user->workshop->id}/message-templates")->assertForbidden();
    }

    public function test_a_read_only_token_lists_but_does_not_write_warranty_templates(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->orderScope()->create();

        Sanctum::actingAs($user, ['workshops:read']);

        $this->getJson("/api/v1/workshops/{$user->workshop->id}/warranty-templates")->assertOk();
        $this->getJson("/api/v1/workshops/{$user->workshop->id}/warranty-templates/{$template->id}")->assertOk();

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/warranty-templates", [
            'name' => 'Garantia 90 dias',
            'body' => 'Termo.',
            'duration_days' => 90,
            'scope' => WarrantyScope::Order->value,
        ])->assertForbidden();

        $this->assertSame(1, $user->workshop->warrantyTemplates()->count());
    }

    public function test_a_write_only_token_writes_but_does_not_list_warranty_templates(): void
    {
        $user = User::factory()->asWorkshop()->create();

        Sanctum::actingAs($user, ['workshops:write']);

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/warranty-templates", [
            'name' => 'Garantia 90 dias',
            'body' => 'Termo.',
            'duration_days' => 90,
            'scope' => WarrantyScope::Order->value,
        ])->assertCreated();

        $this->getJson("/api/v1/workshops/{$user->workshop->id}/warranty-templates")->assertForbidden();
    }

    public function test_the_tenant_scope_still_applies_to_a_read_only_token(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        WarrantyTemplate::factory()->forWorkshop($owner->workshop)->orderScope()->create();
        $intruder = User::factory()->asWorkshop()->create();

        Sanctum::actingAs($intruder, ['workshops:read']);

        $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates")->assertForbidden();
    }
}
