<?php

namespace Tests\Feature;

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

        $this->invoice = Invoice::factory()->create([
            'maintenance_id' => $this->record->id,
            'invoice_type' => 'general',
        ]);
    }

    /**
     * Nenhum model de tenant carrega escopo global: a consulta nasce sem filtro e quem limita é a
     * chamada (Vehicle::maintenancesVisibleTo, Vehicle::inspectionsVisibleTo). Um escopo global
     * aqui faria `findOrFail` responder 404 para a oficina autora lendo a OS que ela registrou em
     * veículo de outro tenant, e zerar as listagens do admin, da busca pública, dos jobs e dos
     * comandos de console, que rodam sem requisição.
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

            $this->assertSame(
                [],
                $model->getGlobalScopes(),
                $class.' ganhou escopo global. O isolamento deste projeto é por policy e por scope '
                .'de visibilidade na consulta; ver .ai/rules/policies.md antes de mudar isto.',
            );
        }
    }

    /**
     * O alias de middleware `tenant` não existe mais, e nenhuma rota pede um que o kernel não
     * conheça — um alias órfão numa lista de rotas só aparece quando a rota é chamada.
     */
    public function test_no_route_asks_for_a_tenant_middleware(): void
    {
        $middleware = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route): array => $route->gatherMiddleware())
            ->filter(fn ($entry): bool => is_string($entry))
            ->unique()
            ->values();

        $this->assertNotEmpty($middleware, 'Nenhuma rota registrada: o teste não está medindo nada.');
        $this->assertFalse(
            $middleware->contains(fn (string $entry): bool => $entry === 'tenant' || str_starts_with($entry, 'tenant:')),
            'Uma rota ainda pede o middleware `tenant`, que foi removido junto com o TenantContext.',
        );
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
            ->assertNotFound(); // arquivo ausente no storage do teste: a autorização passou
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
