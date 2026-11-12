<?php

namespace Tests\Feature\Workshop;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A pendência "OS sem fotos de depois" e o filtro de anexos correspondente só aparecem para a conta
 * que tem o recurso de fotos (App\Features\MaintenancePhotos, ligado conta a conta pelo admin e
 * desligado por padrão — o estado da maioria das oficinas).
 *
 * Medido antes: com o recurso desligado, o formulário da OS não trazia campo de foto nenhum e o
 * Início mostrava "1 OS sem fotos de depois", a descrição "A foto do resultado é a prova…" e o botão
 * "Ver OS sem fotos"; o índice ainda oferecia "Sem fotos de depois". A oficina clicava, chegava à OS
 * filtrada e não tinha o que anexar.
 */
class WorkshopDashboardPhotoPendencyTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'DSH1A23',
            'current_kilometers' => 40_000,
            'odometer_at_registration' => 40_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    public function test_without_the_feature_the_dashboard_does_not_ask_for_photos(): void
    {
        $this->sealedOrderWithoutPhotos();

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.dashboard'))
            ->assertOk()
            ->assertDontSee('sem fotos de depois')
            ->assertDontSee('Ver OS sem fotos')
            ->assertDontSee('A foto do resultado é a prova que o cliente vê na ficha do veículo.')
            // As outras pendências da mesma lista continuam: o que sai é só a de fotos.
            ->assertSee('1 OS sem nota fiscal');
    }

    public function test_with_the_feature_the_dashboard_asks_for_photos(): void
    {
        $this->enableMaintenancePhotosForEveryone();
        $this->sealedOrderWithoutPhotos();

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.dashboard'))
            ->assertOk()
            ->assertSee('1 OS sem fotos de depois')
            ->assertSee('Ver OS sem fotos');
    }

    public function test_without_the_feature_the_list_does_not_offer_the_photo_filter(): void
    {
        $this->sealedOrderWithoutPhotos();

        $html = $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.index'))
            ->assertOk()
            ->assertSee('Sem NF-e')
            ->getContent();

        $this->assertStringNotContainsString('Sem fotos de depois', $html);
    }

    public function test_with_the_feature_the_list_offers_the_photo_filter(): void
    {
        $this->enableMaintenancePhotosForEveryone();
        $this->sealedOrderWithoutPhotos();

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.index'))
            ->assertOk()
            ->assertSee('Sem fotos de depois');
    }

    /**
     * A OS nasce com o Selo desta oficina (é o store do portal dela) e sem foto nenhuma.
     */
    private function sealedOrderWithoutPhotos(): Maintenance
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'DSH1A23',
                'maintenance_type' => 'Suspensão',
                'maintenance_date' => now()->subDays(5)->toDateString(),
                'kilometers' => 40_000,
                'service_category' => 'suspension',
            ])
            ->assertSessionHasNoErrors();

        $maintenance = Maintenance::latest('id')->firstOrFail();

        $this->assertNotNull($maintenance->verified_at);
        $this->assertSame(0, $maintenance->photos()->count());

        return $maintenance;
    }
}
