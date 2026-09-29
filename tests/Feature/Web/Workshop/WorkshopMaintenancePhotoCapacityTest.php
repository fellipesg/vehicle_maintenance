<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\MaintenancePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Editar OS: fotos que ficam + novas não passam de 4 por grupo. A conferência roda antes de
 * gravar qualquer coisa, e o erro vai para photos.{grupo}, mostrado no próprio grupo.
 */
class WorkshopMaintenancePhotoCapacityTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('r2');
    }

    public function test_new_photos_over_the_group_capacity_are_refused_before_anything_is_saved(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('CAP1A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Original', 'maintenance_date' => '2026-03-10', 'kilometers' => 40000]);
        MaintenancePhoto::factory()->count(3)->create([
            'maintenance_id' => $order->id,
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_BEFORE,
        ]);

        $this->actingAs($user)
            ->from(route('workshop.maintenances.edit', $order))
            ->put(route('workshop.maintenances.update', $order), $this->payload([
                'photos' => ['vehicle_before' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]],
            ]))
            ->assertRedirect(route('workshop.maintenances.edit', $order))
            ->assertSessionHasErrors(['photos.vehicle_before' => 'Este grupo aceita até 4 fotos e já tem 3. Envie no máximo 1 ou remova alguma.']);

        $this->assertSame('Original', $order->fresh()->maintenance_type, 'Nada foi salvo.');
        $this->assertSame(3, $order->photos()->count());
    }

    public function test_removing_saved_photos_makes_room_for_new_ones(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('CAP2A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-03-10', 'kilometers' => 40000]);
        $photos = MaintenancePhoto::factory()->count(4)->create([
            'maintenance_id' => $order->id,
            'subject' => MaintenancePhoto::SUBJECT_PART,
            'stage' => MaintenancePhoto::STAGE_AFTER,
        ]);

        $this->actingAs($user)
            ->put(route('workshop.maintenances.update', $order), $this->payload([
                'delete_photos' => [$photos[0]->id, $photos[1]->id],
                'photos' => ['part_after' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.maintenances.show', $order))
            ->assertSessionHas('success', 'OS atualizada.');

        $this->assertSame(4, $order->photos()->where('subject', MaintenancePhoto::SUBJECT_PART)->where('stage', MaintenancePhoto::STAGE_AFTER)->count());
    }

    public function test_full_group_message_asks_to_remove_one_first(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('CAP3A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-03-10', 'kilometers' => 40000]);
        MaintenancePhoto::factory()->count(4)->create([
            'maintenance_id' => $order->id,
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_AFTER,
        ]);

        $this->actingAs($user)
            ->put(route('workshop.maintenances.update', $order), $this->payload([
                'photos' => ['vehicle_after' => [UploadedFile::fake()->image('a.jpg')]],
            ]))
            ->assertSessionHasErrors(['photos.vehicle_after' => 'Este grupo já tem 4 fotos. Remova alguma antes de adicionar outra.']);
    }

    public function test_capacity_error_is_shown_in_the_group_on_the_edit_page(): void
    {
        $user = $this->workshopUser();
        $order = $this->sealedOrder($user, ['vehicle_id' => $this->ownedVehicle('CAP4A23')->id]);

        $xpath = $this->page($this->actingAs($user)
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
                'photos.vehicle_before' => ['Este grupo já tem 4 fotos. Remova alguma antes de adicionar outra.'],
            ]))])
            ->get(route('workshop.maintenances.edit', $order)));

        $group = $this->element($xpath, '//fieldset[@id="photos_vehicle_before-grupo"]');
        $this->assertStringContainsString('Este grupo já tem 4 fotos.', $this->text($this->element($xpath, './/*[@id="photos_vehicle_before-error"]', $group)));
        $this->assertSame('true', $this->element($xpath, './/input[@id="photos_vehicle_before"]', $group)->getAttribute('aria-invalid'));
        $this->assertSame('#photos_vehicle_before', $this->element($xpath, '//*[@data-slot="form-errors"]//a')->getAttribute('href'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'maintenance_type' => 'Alterada',
            'maintenance_date' => '2026-03-10',
            'kilometers' => 40000,
            'service_category' => 'mechanical',
        ], $overrides);
    }
}
