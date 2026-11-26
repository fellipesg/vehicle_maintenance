<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateWebSession;
use App\Http\Middleware\SetSecurityHeaders;
use App\Models\Garage;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehicleInspection;
use App\Models\VehiclePdfExport;
use App\Models\VehiclePlate;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Support\SanctumMobileToken;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O isolamento entre contas é decidido por policy mais scope de visibilidade na consulta, e por
 * nada além disso: não existe tenant "ambiente" guardado pela requisição nem escopo global nos
 * models. Este teste tranca as duas metades dessa decisão (.ai/rules/policies.md, "O isolamento
 * não tem contexto ambiente").
 *
 * O `App\Support\TenantContext` e o middleware `tenant` que o alimentava foram removidos por serem
 * objeto morto — zero leitores — que anunciava uma garantia automática inexistente. As três listas
 * de rotas que carregavam esse middleware (`routes/web.php`, `routes/api.php` e
 * `routes/api-two-factor.php`) continuam negando a conta de outro tenant exatamente como antes,
 * porque quem negava sempre foi a policy. Os casos de travessia legítima — oficina autora, busca
 * pública, admin, job e comando sem requisição — são o motivo de o escopo global não ter entrado no
 * lugar: fora de uma requisição não há tenant nenhum, e escopo que devolve zero linha em silêncio é
 * pior que escopo nenhum.
 */
class TenantIsolationHasNoAmbientContextTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private User $owner;

    private User $stranger;

    private Maintenance $record;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->vehicle = Vehicle::factory()->create();

        $this->owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($this->owner, $this->vehicle);

        // Outro tenant de proprietário, sem relação nenhuma com o veículo.
        $this->stranger = User::factory()->asUser()->create()->refresh();

        $this->record = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);

        // O arquivo existe de verdade: sem ele o controller responde 404 depois de autorizar, e o
        // caso do dono passaria sem provar que a autorização passou.
        $path = 'invoices/'.$this->record->id.'-'.uniqid().'.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 teste');

        $this->invoice = Invoice::factory()->create([
            'maintenance_id' => $this->record->id,
            'invoice_type' => 'general',
            'file_path' => $path,
            'file_name' => 'nota_fiscal.pdf',
        ]);
    }

    /**
     * Nenhum model de tenant carrega escopo global: a consulta nasce sem filtro e quem limita é a
     * chamada (Vehicle::maintenancesVisibleTo, Vehicle::inspectionsVisibleTo). Um escopo global
     * aqui faria `findOrFail` responder 404 para a oficina autora lendo a OS que ela registrou em
     * veículo de outro tenant, e zerar as listagens do admin, da busca pública, dos jobs e dos
     * comandos de console, que rodam sem requisição.
     *
     * O escopo do SoftDeletes é tolerado de propósito: ele não tem nada a ver com tenant, e sem
     * esta exceção um `use SoftDeletes` legítimo em qualquer destes models falharia aqui com uma
     * mensagem sobre isolamento, mandando quem mexeu para a regra errada.
     */
    public function test_no_tenant_model_registers_a_global_scope(): void
    {
        $models = [
            Vehicle::class,
            Maintenance::class,
            VehicleInspection::class,
            Invoice::class,
            UserVehicle::class,
            VehiclePlate::class,
            VehicleConsignment::class,
            VehiclePdfExport::class,
            WarrantyTemplate::class,
            WorkshopMessageTemplate::class,
            User::class,
            Workshop::class,
            Garage::class,
        ];

        foreach ($models as $class) {
            /** @var Model $model */
            $model = new $class;

            $scopes = collect($model->getGlobalScopes())
                ->reject(fn (mixed $scope, string $key): bool => $key === SoftDeletingScope::class
                    || $scope instanceof SoftDeletingScope)
                ->keys()
                ->all();

            $this->assertSame(
                [],
                $scopes,
                $class.' ganhou escopo global. O isolamento deste projeto é por policy e por scope '
                .'de visibilidade na consulta; ver .ai/rules/policies.md antes de mudar isto.',
            );
        }
    }

    /**
     * Nada na pilha de middleware reconstrói um contexto de tenant.
     *
     * A sonda olha a pilha **resolvida** — global, grupos, aliases e o que cada rota declara —, e
     * não só `gatherMiddleware()`: este devolve o *nome* do grupo (`web`, `api`) sem expandir, então
     * middleware acrescentado com `$middleware->append()` ou `$middleware->web(append: [...])` não
     * apareceria ali. É justamente assim que `bootstrap/app.php` registra o `SetSecurityHeaders` e o
     * `AuthenticateWebSession`, ou seja, era o caminho mais provável de o contexto ambiente voltar
     * sem este teste notar. As duas asserções de alcance abaixo existem para a sonda não virar vácuo
     * em silêncio se esses getters mudarem de forma.
     */
    public function test_nothing_in_the_resolved_middleware_stack_rebuilds_a_tenant_context(): void
    {
        $kernel = app(HttpKernel::class);
        $aliases = $kernel->getMiddlewareAliases();
        $groups = $kernel->getMiddlewareGroups();

        $this->assertArrayNotHasKey(
            'tenant',
            $aliases,
            'O alias `tenant` voltou. Era por ele que as três listas de rotas pediam o middleware '
            .'que preenchia o TenantContext; ver .ai/rules/policies.md.',
        );

        $resolved = collect($kernel->getGlobalMiddleware())
            ->merge(collect($groups)->flatten())
            ->merge(collect($aliases)->values())
            ->merge(
                collect(Route::getRoutes()->getRoutes())
                    ->flatMap(fn ($route): array => $route->gatherMiddleware()),
            )
            ->filter(fn (mixed $entry): bool => is_string($entry))
            ->unique()
            ->values();

        // Alcance: a sonda realmente vê o que entra pelo append global e pelo grupo `web`.
        $this->assertTrue(
            $resolved->contains(SetSecurityHeaders::class),
            'A sonda perdeu o middleware global (bootstrap/app.php, $middleware->append).',
        );
        $this->assertTrue(
            $resolved->contains(AuthenticateWebSession::class),
            'A sonda perdeu o middleware de grupo (bootstrap/app.php, $middleware->web(append:)).',
        );

        foreach ($resolved as $entry) {
            $this->assertStringNotContainsStringIgnoringCase(
                'TenantContext',
                $entry,
                'Voltou middleware de contexto de tenant ('.$entry.'). O isolamento aqui é por '
                .'policy mais scope de visibilidade; ver .ai/rules/policies.md.',
            );
        }

        $this->assertFalse(
            $resolved->contains(fn (string $entry): bool => $entry === 'tenant'
                || str_starts_with($entry, 'tenant:')),
            'Alguma rota ou grupo voltou a pedir o middleware `tenant`.',
        );
    }

    /**
     * Remover um alias com rota ainda pedindo por ele só estoura quando a rota é chamada: o
     * `route:list` mostra a string e o 500 aparece em produção. Esta é a trava disso.
     */
    public function test_every_middleware_a_route_asks_for_is_known_to_the_kernel(): void
    {
        $kernel = app(HttpKernel::class);
        $aliases = $kernel->getMiddlewareAliases();
        $groups = $kernel->getMiddlewareGroups();
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $entry) {
                if (! is_string($entry)) {
                    continue;
                }

                $name = Str::before($entry, ':');
                $checked++;

                $this->assertTrue(
                    array_key_exists($name, $aliases)
                        || array_key_exists($name, $groups)
                        || class_exists($name),
                    'A rota '.($route->uri() ?: '/').' pede o middleware `'.$entry.'`, que não é '
                    .'alias, grupo nem classe — ele responde 500 quando a rota for chamada.',
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'Nenhuma rota registrada: o teste não mediu nada.');
    }

    /** Grupo de `routes/web.php`: a nota de outro tenant continua fechada. */
    public function test_web_invoice_route_still_denies_another_tenant(): void
    {
        $this->actingAs($this->stranger)
            ->get(route('invoices.show', $this->invoice))
            ->assertForbidden();
    }

    /** O mesmo grupo continua abrindo para o dono — o 403 acima não é vácuo. */
    public function test_web_invoice_route_still_serves_the_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('invoices.show', $this->invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** Grupo de `routes/api.php`: a OS de outro tenant continua fechada. */
    public function test_api_maintenance_route_still_denies_another_tenant(): void
    {
        Sanctum::actingAs($this->stranger, SanctumMobileToken::ABILITIES);

        $this->getJson('/api/v1/maintenances/'.$this->record->id)
            ->assertForbidden();
    }

    /** E continua abrindo para o dono. */
    public function test_api_maintenance_route_still_serves_the_owner(): void
    {
        Sanctum::actingAs($this->owner, SanctumMobileToken::ABILITIES);

        $this->getJson('/api/v1/maintenances/'.$this->record->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->record->id);
    }

    /**
     * Grupo de `routes/api-two-factor.php`: ele carregava o middleware só para montar o contexto,
     * e segue respondendo pela conta autenticada sem ele.
     */
    public function test_two_factor_route_group_still_answers_for_the_authenticated_account(): void
    {
        Sanctum::actingAs($this->owner, SanctumMobileToken::ABILITIES);

        $this->postJson('/api/v1/two-factor/enable')->assertOk();

        $this->assertNotNull($this->owner->fresh()->two_factor_secret);
    }
}
