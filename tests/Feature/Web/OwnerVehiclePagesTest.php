<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * Meus veículos, ficha e edição do veículo, renderizados no servidor com os componentes
 * compartilhados (x-vehicle.card, x-vehicle.detail, x-ui.image-cropper).
 */
class OwnerVehiclePagesTest extends TestCase
{
    use InspectsOwnerPages;
    use RefreshDatabase;

    public function test_vehicle_list_shows_only_the_current_vehicles_as_clickable_cards(): void
    {
        $owner = $this->owner();
        $civic = $this->ownedVehicle($owner, [
            'brand' => 'Honda',
            'model' => 'Civic',
            'license_plate' => 'CIV1C23',
            'odometer_at_registration' => 50_000,
            'current_kilometers' => 52_000,
        ]);
        $sold = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Palio']);
        $owner->vehicles()->attach($sold->id, ['is_current_owner' => false, 'tenant_id' => $owner->tenant_id]);
        $someoneElse = $this->ownedVehicle($this->owner(), ['brand' => 'Renault', 'model' => 'Sandero']);

        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $civic->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id, 'kilometers' => 51_000]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.vehicles.index')));

        $this->assertOnlyHeading($xpath, 'Meus veículos');
        $cards = $this->ownerElements($xpath, '//*[@data-owner-vehicles]/*[@data-slot="vehicle-card"]');
        $this->assertCount(1, $cards);
        $this->assertSame((string) $civic->id, $cards[0]->getAttribute('data-vehicle-id'));
        $this->assertSame('li', $cards[0]->tagName);

        $link = $this->ownerElement($xpath, './/h2/a[@data-slot="vehicle-card-link"]', $cards[0]);
        $this->assertSame(route('user.vehicles.show', $civic), $link->getAttribute('href'));
        $this->assertSame('Honda Civic', $this->ownerText($link));
        $this->assertStringContainsString('CIV1C23', $this->ownerText($cards[0]));
        $this->assertStringContainsString('1 com selo', $this->ownerText($cards[0]));
        $this->assertSame('Próxima revisão aos 61.000 km · faltam 9.000 km', $this->ownerText($this->ownerElement($xpath, './/*[@data-next-revision]', $cards[0])));

        // Ações secundárias no menu "Mais ações", clicáveis por cima do link do card.
        $menu = $this->ownerElement($xpath, './/*[@data-ui-dropdown]', $cards[0]);
        $this->assertSame('Mais ações: Honda Civic', $this->ownerElement($xpath, './/button[@aria-haspopup="menu"]', $menu)->getAttribute('aria-label'));
        $this->ownerElement($xpath, './/a[@role="menuitem"][@href="'.route('user.maintenances.create', ['vehicle_id' => $civic->id]).'"]', $menu);
        $this->ownerElement($xpath, './/a[@role="menuitem"][@href="'.route('user.vehicles.edit', $civic).'"]', $menu);

        $html = $this->actingAs($owner)->get(route('user.vehicles.index'))->getContent();
        $this->assertStringNotContainsString('Fiat Palio', $html);
        $this->assertStringNotContainsString($someoneElse->model, $html);
        $this->assertStringNotContainsString('Carregando', $html);
    }

    public function test_vehicle_list_does_not_query_per_card(): void
    {
        $owner = $this->owner();
        $this->ownedVehicle($owner);
        $this->actingAs($owner)->get(route('user.vehicles.index'))->assertOk();

        $oneVehicle = $this->countQueries(fn () => $this->actingAs($owner)->get(route('user.vehicles.index'))->assertOk());

        foreach (range(1, 4) as $index) {
            $vehicle = $this->ownedVehicle($owner);
            Maintenance::factory()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);
        }

        $fiveVehicles = $this->countQueries(fn () => $this->actingAs($owner)->get(route('user.vehicles.index'))->assertOk());

        $this->assertSame($oneVehicle, $fiveVehicles, 'Cada card não pode fazer consultas próprias.');
    }

    public function test_empty_vehicle_list_leads_to_the_add_vehicle_wizard(): void
    {
        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.vehicles.index')));

        $empty = $this->ownerElement($xpath, '//main//*[@data-slot="empty-state"]');
        $this->assertStringContainsString('Nenhum veículo ainda', $this->ownerText($empty));
        $this->ownerElement($xpath, './/a[@data-slot="button"][@href="'.route('user.vehicles.create').'"]', $empty);
    }

    public function test_vehicle_detail_is_server_rendered_with_the_owner_actions(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2021, 'color' => 'Prata', 'license_plate' => 'TOY2A21']);
        $declared = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Troca de óleo',
        ]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.vehicles.show', $vehicle)));

        $this->assertOnlyHeading($xpath, 'Toyota Corolla');
        $this->assertSame(
            [['label' => 'Meus veículos', 'href' => route('user.vehicles.index')], ['label' => 'Toyota Corolla', 'href' => null]],
            $this->ownerTrail($xpath),
        );
        $this->assertSame('2021 · Prata · Placa TOY2A21', $this->ownerText($this->ownerElement($xpath, '//*[@data-slot="page-header-description"]')));

        // Exportar PDF: formulário (sem JS, pede o PDF por e-mail) que o user-portal.js assume.
        $export = $this->ownerElement($xpath, '//form[@data-vehicle-pdf-export]');
        $this->assertSame(route('user.vehicles.export-pdf', $vehicle), $export->getAttribute('action'));
        $this->assertSame((string) $vehicle->id, $export->getAttribute('data-vehicle-id'));
        $this->assertSame('off', $export->getAttribute('data-submit-busy'));
        $this->ownerElement($xpath, './/button[@type="submit"][@data-pdf-export-button]', $export);
        $this->ownerElement($xpath, './/*[@role="status"][@data-pdf-export-status]', $export);
        $this->assertSame(0, $xpath->query('//*[@download]')->length, 'Nenhum link com o atributo download (.ai/rules/js.md).');

        // Ordem das ações: Exportar PDF, Editar e o primário Registrar manutenção por último.
        $actions = $this->ownerElement($xpath, '//*[@data-slot="page-header-actions"]');
        $labels = array_map(fn (\DOMElement $label): string => $this->ownerText($label), $this->ownerElements($xpath, './/*[@data-slot="label"]', $actions));
        $this->assertSame(['Exportar PDF', 'Editar', 'Registrar manutenção'], $labels);
        $this->assertSame(route('user.vehicles.edit', $vehicle), $this->ownerButton($xpath, 'Editar')->getAttribute('href'));
        $this->assertSame(
            route('user.maintenances.create', ['vehicle_id' => $vehicle->id]),
            $this->ownerElement($xpath, './/a[@data-variant="primary"]', $actions)->getAttribute('href'),
        );

        // Histórico com o link para o detalhe da manutenção no portal do proprietário.
        $this->ownerElement($xpath, '//*[@id="historico-lista"]//a[@href="'.route('user.maintenances.show', $declared).'"]');
        $this->ownerElement($xpath, '//*[@id="linha-do-tempo"]');
        $this->ownerElement($xpath, '//*[@id="documentos"]');
        $this->assertStringNotContainsString('Carregando', $this->ownerText($this->ownerElement($xpath, '//main')));
    }

    public function test_vehicle_detail_without_history_offers_to_register_a_maintenance(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.vehicles.show', $vehicle)));

        $this->ownerElement($xpath, '//*[@id="historico"]//*[@data-slot="empty-state"]//a[@href="'.route('user.maintenances.create', ['vehicle_id' => $vehicle->id]).'"]');
    }

    public function test_vehicle_detail_is_forbidden_for_someone_elses_vehicle(): void
    {
        $vehicle = $this->ownedVehicle($this->owner());

        $this->actingAs($this->owner())->get(route('user.vehicles.show', $vehicle))->assertForbidden();
    }

    public function test_admin_owner_account_sees_other_vehicles_without_owner_actions(): void
    {
        $admin = \App\Models\User::factory()->asUser()->asAdmin()->create();
        $vehicle = $this->ownedVehicle($this->owner(), ['brand' => 'Ford', 'model' => 'Ka']);

        $xpath = $this->ownerPage($this->actingAs($admin)->get(route('user.vehicles.show', $vehicle)));

        $this->assertOnlyHeading($xpath, 'Ford Ka');
        $this->assertNull($this->ownerButton($xpath, 'Editar'));
        $this->assertNull($this->ownerButton($xpath, 'Registrar manutenção'));
    }

    public function test_vehicle_edit_is_split_in_sections_with_the_cover_croppers(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['brand' => 'Honda', 'model' => 'Fit', 'license_plate' => 'FIT1A23']);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.vehicles.edit', $vehicle)));

        $this->assertOnlyHeading($xpath, 'Editar veículo');
        $this->assertSame(
            [
                ['label' => 'Meus veículos', 'href' => route('user.vehicles.index')],
                ['label' => 'Honda Fit', 'href' => route('user.vehicles.show', $vehicle)],
                ['label' => 'Editar', 'href' => null],
            ],
            $this->ownerTrail($xpath),
        );

        $form = $this->ownerElement($xpath, '//form[@data-vehicle-edit-form]');
        $this->assertSame(route('user.vehicles.update', $vehicle), $form->getAttribute('action'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));

        $covers = $this->ownerElement($xpath, './/section[@id="capas"]', $form);
        $landscape = $this->ownerElement($xpath, './/*[@data-image-cropper][@data-aspect="16:9"]', $covers);
        $portrait = $this->ownerElement($xpath, './/*[@data-image-cropper][@data-aspect="9:16"]', $covers);
        $this->ownerElement($xpath, './/input[@type="file"][@name="cover"]', $landscape);
        $this->ownerElement($xpath, './/input[@type="file"][@name="cover_portrait"]', $portrait);

        $this->ownerElement($xpath, './/section[@id="dados"]//input[@name="license_plate"]', $form);
        $this->ownerElement($xpath, './/*[@data-slot="form-actions"]//a[@href="'.route('user.vehicles.show', $vehicle).'"]', $form);
        $this->ownerElement($xpath, './/*[@data-slot="form-actions"]//button[@type="submit"]', $form);

        // O CRLV-e fica num formulário à parte, depois, com o aviso do que ele substitui.
        $crlv = $this->ownerElement($xpath, '//section[@data-crlv-import]');
        $this->assertStringContainsString('Atualizar pelo CRLV-e', $this->ownerText($crlv));
        $this->assertStringContainsString('substituídos na hora', $this->ownerText($crlv));
        $this->assertSame(route('user.vehicles.import-crlv.edit', $vehicle), $this->ownerElement($xpath, './/form', $crlv)->getAttribute('action'));
    }

    public function test_crlv_update_on_edit_records_the_new_plate_in_the_plate_history(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['license_plate' => 'AAA1A11', 'renavam' => '01159110473']);
        app(\App\Services\Vehicle\VehiclePlateHistoryService::class)->recordInitialPlate($vehicle, 'crlv_import', $owner);

        $file = new \Illuminate\Http\UploadedFile(base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'), 'CRLV-e.pdf', 'application/pdf', null, true);

        $this->actingAs($owner)
            ->post(route('user.vehicles.import-crlv.edit', $vehicle), ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.show', $vehicle));

        $this->assertSame('QOS6H54', $vehicle->fresh()->license_plate);
        $this->assertDatabaseHas('vehicle_plates', ['vehicle_id' => $vehicle->id, 'plate' => 'QOS6H54', 'source' => 'crlv_import', 'ended_at' => null]);
        $this->assertDatabaseMissing('vehicle_plates', ['vehicle_id' => $vehicle->id, 'plate' => 'AAA1A11', 'ended_at' => null]);
    }

    /**
     * O proprietário salva pelo mesmo contrato do Lojista (UpdatesVehicleDetails): a quilometragem
     * atual do formulário é gravada e nunca fica abaixo da última manutenção.
     */
    public function test_vehicle_update_saves_the_current_kilometers_above_the_last_maintenance(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020, 'current_kilometers' => 60_000, 'odometer_at_registration' => 50_000]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id, 'kilometers' => 55_000]);

        $this->actingAs($owner)
            ->from(route('user.vehicles.edit', $vehicle))
            ->put(route('user.vehicles.update', $vehicle), $this->vehiclePayload($vehicle, ['current_kilometers' => 54_000]))
            ->assertRedirect(route('user.vehicles.edit', $vehicle))
            ->assertSessionHasErrors(['current_kilometers' => 'A quilometragem atual não pode ser menor que a da última manutenção registrada (55.000 km).']);

        $this->assertSame(60_000, $vehicle->fresh()->current_kilometers);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), $this->vehiclePayload($vehicle, ['current_kilometers' => 62_000, 'color' => 'Azul']))
            ->assertRedirect(route('user.vehicles.show', $vehicle))
            ->assertSessionHas('success', 'Veículo atualizado.');

        $this->assertSame(62_000, $vehicle->fresh()->current_kilometers);
        $this->assertSame('Azul', $vehicle->fresh()->color);
    }

    public function test_vehicle_update_does_not_clear_the_chassis(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020]);
        $chassis = $vehicle->chassis;

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), $this->vehiclePayload($vehicle, ['chassis' => '']))
            ->assertSessionHasErrors('chassis');

        $this->assertSame($chassis, $vehicle->fresh()->chassis);
    }

    public function test_vehicle_edit_is_forbidden_for_someone_elses_vehicle(): void
    {
        $vehicle = $this->ownedVehicle($this->owner());

        $this->actingAs($this->owner())->get(route('user.vehicles.edit', $vehicle))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePayload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'license_plate' => $vehicle->license_plate,
            'renavam' => $vehicle->renavam,
            'crv_number' => $vehicle->crv_number ?? '123456789012',
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'chassis' => $vehicle->chassis,
            'motorization' => $vehicle->motorization,
            'engine' => $vehicle->engine,
        ], $overrides);
    }
}
