<?php

namespace Tests\Feature\Web;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * Manutenções do proprietário renderizadas no servidor: lista com filtros e paginação, registro
 * com a trilha e o retorno para a ficha, detalhe com Garantia do serviço e as ações de Editar e
 * Excluir nas declaradas (MaintenancePolicy).
 */
class OwnerMaintenancePagesTest extends TestCase
{
    use InspectsOwnerPages;
    use RefreshDatabase;

    public function test_list_shows_vehicle_date_km_and_workshop_of_the_owner_maintenances(): void
    {
        $owner = $this->owner();
        $civic = $this->ownedVehicle($owner, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $civic->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Troca de óleo',
            'maintenance_date' => '2025-03-10',
            'kilometers' => 42_000,
            'workshop_id' => null,
            'workshop_name' => 'Oficina do Bairro',
        ]);
        $stranger = $this->owner();
        Maintenance::factory()->create([
            'vehicle_id' => $this->ownedVehicle($stranger)->id,
            'user_id' => $stranger->id,
            'tenant_id' => $stranger->tenant_id,
            'maintenance_type' => 'Serviço de outra conta',
        ]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index')));

        $this->assertOnlyHeading($xpath, 'Manutenções');
        // Mesma apresentação do Lojista: cards de procedência agrupados por mês (o mês é o h2).
        $this->assertSame(0, $xpath->query('//main//table')->length);
        $this->assertSame('Março de 2025', $this->ownerText($this->ownerElement($xpath, '//main//section/h2')));
        $rows = $this->ownerElements($xpath, '//main//section/ol/li[@data-maintenance-card]');
        $this->assertCount(1, $rows);
        $row = $this->ownerText($rows[0]);
        foreach (['10/03/2025', 'Troca de óleo', 'Honda Civic', 'CIV1C23', '42.000 km', 'Oficina do Bairro', 'Declarada pelo proprietário'] as $expected) {
            $this->assertStringContainsString($expected, $row);
        }
        $this->assertSame(
            route('user.maintenances.show', Maintenance::where('maintenance_type', 'Troca de óleo')->first()),
            $this->ownerElement($xpath, './/h3[@data-slot="provenance-card-title"]/a', $rows[0])->getAttribute('href'),
        );
        $this->assertSame(route('user.vehicles.show', $civic), $this->ownerElement($xpath, './/a[contains(., "Honda Civic")]', $rows[0])->getAttribute('href'));
        $this->assertNotNull($this->ownerButton($xpath, 'Registrar manutenção'));

        $legend = $this->ownerText($this->ownerElement($xpath, '//main//*[@data-slot="provenance-legend"]'));
        $this->assertStringContainsString('Declarada pelo proprietário', $legend);
        $this->assertStringContainsString('Declarada pelo lojista', $legend);
    }

    public function test_list_filters_by_vehicle_and_provenance_with_counts(): void
    {
        $owner = $this->owner();
        $civic = $this->ownedVehicle($owner, ['brand' => 'Honda', 'model' => 'Civic']);
        $gol = $this->ownedVehicle($owner, ['brand' => 'Volkswagen', 'model' => 'Gol']);
        $base = ['user_id' => $owner->id, 'tenant_id' => $owner->tenant_id];

        Maintenance::factory()->sealedByWorkshop()->create([...$base, 'vehicle_id' => $civic->id, 'maintenance_type' => 'Civic com selo']);
        Maintenance::factory()->declaredByOwner()->create([...$base, 'vehicle_id' => $civic->id, 'maintenance_type' => 'Civic declarada']);
        Maintenance::factory()->declaredByOwner()->create([...$base, 'vehicle_id' => $gol->id, 'maintenance_type' => 'Gol declarada']);

        $all = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index')));
        $this->assertCount(3, $this->ownerElements($all, '//main//li[@data-maintenance-card]'));
        $select = $this->ownerElement($all, '//main//form//select[@name="veiculo"]');
        $this->assertCount(3, $this->ownerElements($all, './/option', $select), 'Todos os veículos, Civic e Gol.');
        $this->assertStringContainsString('3', $this->ownerText($this->ownerElement($all, '//main//nav//a[contains(., "Todas")]')));

        $civicOnly = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index', ['veiculo' => $civic->id])));
        $this->assertSame(['Civic com selo', 'Civic declarada'], collect($this->ownerElements($civicOnly, '//main//li[@data-maintenance-card]//*[@data-slot="provenance-card-title"]'))
            ->map(fn (\DOMElement $cell): string => $this->ownerText($cell))->sort()->values()->all());
        $this->assertStringContainsString('1', $this->ownerText($this->ownerElement($civicOnly, '//main//nav//a[contains(., "Selo da oficina")]')));

        $sealed = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index', ['verified' => '1'])));
        $sealedRows = $this->ownerElements($sealed, '//main//li[@data-maintenance-card]');
        $this->assertCount(1, $sealedRows);
        $this->assertSame('1', $sealedRows[0]->getAttribute('data-verified'));

        $empty = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index', ['veiculo' => $gol->id, 'verified' => '1'])));
        $emptyState = $this->ownerElement($empty, '//*[@data-maintenance-list-empty="filtered"]');
        $this->assertStringContainsString('Nenhuma manutenção com Selo da oficina', $this->ownerText($emptyState));
        $this->ownerElement($empty, './/a[contains(., "Limpar filtros")]', $emptyState);
    }

    public function test_list_is_paginated_instead_of_cut_at_fifteen(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);
        Maintenance::factory()->count(17)->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);

        $first = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index')));
        $this->assertCount(15, $this->ownerElements($first, '//main//li[@data-maintenance-card]'));
        $this->ownerElement($first, '//main//a[contains(@href, "page=2")]');

        $second = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.index', ['page' => 2])));
        $this->assertCount(2, $this->ownerElements($second, '//main//li[@data-maintenance-card]'));
    }

    public function test_empty_list_without_vehicles_leads_to_add_a_vehicle(): void
    {
        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.maintenances.index')));

        $empty = $this->ownerElement($xpath, '//*[@data-maintenance-list-empty="all"]');
        $this->assertStringContainsString('Nenhum veículo ainda', $this->ownerText($empty));
        $this->ownerElement($xpath, './/a[@href="'.route('user.vehicles.create').'"]', $empty);
    }

    public function test_create_from_the_vehicle_keeps_the_trail_and_returns_to_it(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['brand' => 'Fiat', 'model' => 'Argo', 'odometer_at_registration' => 50_000, 'current_kilometers' => 60_000]);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_date' => '2026-06-12',
            'kilometers' => 60_000,
        ]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.create', ['vehicle_id' => $vehicle->id])));

        $this->assertOnlyHeading($xpath, 'Registrar manutenção');
        $this->assertSame(
            [
                ['label' => 'Meus veículos', 'href' => route('user.vehicles.index')],
                ['label' => 'Fiat Argo', 'href' => route('user.vehicles.show', $vehicle)],
                ['label' => 'Registrar manutenção', 'href' => null],
            ],
            $this->ownerTrail($xpath),
        );
        $this->ownerElement($xpath, '//select[@name="vehicle_id"]/option[@value="'.$vehicle->id.'"][@selected]');
        $this->ownerElement($xpath, '//input[@type="hidden"][@name="return_to"][@value="vehicle"]');
        $this->ownerElement($xpath, '//*[@data-slot="form-actions"]//a[@href="'.route('user.vehicles.show', $vehicle).'"]');
        $this->assertStringContainsString('Aparecerá como Declarada pelo proprietário', $this->ownerText($this->ownerElement($xpath, '//main')));

        // Dados para a faixa de quilometragem no navegador (a mesma regra do servidor).
        $mileage = json_decode($this->ownerElement($xpath, '//*[@data-owner-maintenance-form]')->getAttribute('data-mileage'), true);
        $this->assertSame($vehicle->id, $mileage[0]['id']);
        $this->assertSame(50_000, $mileage[0]['registration_kilometers']);
        $this->assertSame([['date' => '2026-06-12', 'kilometers' => 60_000]], $mileage[0]['records']);

        // A faixa de km e o aviso da nota fiscal são anunciados e ficam ligados aos campos.
        $km = $this->ownerElement($xpath, '//input[@name="kilometers"]');
        $this->assertContains('kilometers-range', explode(' ', $km->getAttribute('aria-describedby')));
        $this->assertSame('polite', $this->ownerElement($xpath, '//*[@id="kilometers-range"]')->getAttribute('aria-live'));
        $this->assertSame('', $km->getAttribute('value'), 'O km não é preenchido sozinho.');
        $invoices = $this->ownerElement($xpath, '//input[@type="file"][@name="invoices[]"]');
        $this->assertSame(['invoices-rules', 'invoices-requirement'], array_slice(explode(' ', $invoices->getAttribute('aria-describedby')), 0, 2));

        $this->actingAs($owner)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Alinhamento',
                'service_category' => 'suspension',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 61_000,
                'return_to' => 'vehicle',
            ])
            ->assertRedirect(route('user.vehicles.show', $vehicle).'#historico')
            ->assertSessionHas('success');
    }

    public function test_create_without_origin_goes_to_the_new_maintenance(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 10_000, 'odometer_at_registration' => 10_000]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.create')));
        $this->assertSame(0, $xpath->query('//input[@name="return_to"]')->length);
        $this->ownerElement($xpath, '//*[@data-slot="form-actions"]//a[@href="'.route('user.maintenances.index').'"]');

        $response = $this->actingAs($owner)->post(route('user.maintenances.store'), [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'service_category' => 'mechanical',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 11_000,
        ]);

        $response->assertRedirect(route('user.maintenances.show', Maintenance::where('maintenance_type', 'Revisão')->firstOrFail()));
    }

    public function test_create_preselects_the_workshop_from_the_directory(): void
    {
        $owner = $this->owner();
        $this->ownedVehicle($owner);
        $workshop = Workshop::factory()->create(['name' => 'Auto Center Rede']);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.create', ['workshop_id' => $workshop->id])));

        $this->ownerElement($xpath, '//select[@name="workshop_id"]/option[@value="'.$workshop->id.'"][@selected]');
        $this->ownerElement($xpath, '//*[@data-workshop-name-field]//input[@name="workshop_name"]');
    }

    public function test_create_without_vehicles_shows_the_empty_state_instead_of_the_form(): void
    {
        $xpath = $this->ownerPage($this->actingAs($this->owner())->get(route('user.maintenances.create')));

        $this->assertSame(0, $xpath->query('//main//form[@action="'.route('user.maintenances.store').'"]')->length);
        $this->ownerElement($xpath, '//main//*[@data-slot="empty-state"]//a[@href="'.route('user.vehicles.create').'"]');
    }

    public function test_store_rejects_kilometers_below_the_previous_record_with_the_server_message(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 60_000, 'odometer_at_registration' => 60_000]);

        $this->actingAs($owner)
            ->from(route('user.maintenances.create'))
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'service_category' => 'mechanical',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 55_000,
            ])
            ->assertRedirect(route('user.maintenances.create'))
            ->assertSessionHasErrors(['kilometers']);

        $this->assertSame(0, Maintenance::count());
    }

    public function test_store_requires_the_invoice_when_a_network_workshop_is_chosen(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 10_000, 'odometer_at_registration' => 10_000]);

        $this->actingAs($owner)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'workshop_id' => Workshop::factory()->create()->id,
                'maintenance_type' => 'Revisão',
                'service_category' => 'mechanical',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 11_000,
            ])
            ->assertSessionHasErrors(['invoices']);
    }

    public function test_declared_maintenance_detail_has_the_trail_warranty_and_owner_actions(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['brand' => 'Jeep', 'model' => 'Renegade', 'license_plate' => 'JEP1R23']);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Troca de pastilhas',
            'maintenance_date' => today()->subDays(10),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($maintenance)->create(['name' => 'Garantia de 90 dias', 'duration_days' => 90]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.show', $maintenance)));

        $this->assertOnlyHeading($xpath, 'Troca de pastilhas');
        $this->assertSame(
            [
                ['label' => 'Meus veículos', 'href' => route('user.vehicles.index')],
                ['label' => 'Jeep Renegade', 'href' => route('user.vehicles.show', $vehicle)],
                ['label' => 'Troca de pastilhas', 'href' => null],
            ],
            $this->ownerTrail($xpath),
        );
        $this->assertSame(
            'Jeep Renegade · JEP1R23 · '.today()->subDays(10)->format('d/m/Y'),
            $this->ownerText($this->ownerElement($xpath, '//*[@data-slot="page-header-description"]')),
        );

        $warranty = $this->ownerElement($xpath, '//*[@data-slot="maintenance-warranty"]');
        $this->assertStringContainsString('Garantia do serviço', $this->ownerText($warranty));
        $this->assertStringContainsString('Em garantia', $this->ownerText($warranty));
        $this->assertStringContainsString('Garantia de 90 dias', $this->ownerText($warranty));

        $this->assertSame(route('user.maintenances.edit', $maintenance), $this->ownerButton($xpath, 'Editar')->getAttribute('href'));
        $delete = $this->ownerElement($xpath, '//form[@action="'.route('user.maintenances.destroy', $maintenance).'"]');
        $this->ownerElement($xpath, './/input[@name="_method"][@value="DELETE"]', $delete);
        $deleteButton = $this->ownerElement($xpath, './/button[@type="submit"]', $delete);
        $this->assertSame('Excluir esta manutenção?', $deleteButton->getAttribute('data-confirm-title'));
        $this->assertSame('Excluir manutenção', $deleteButton->getAttribute('data-confirm-action-label'));
        $this->assertSame('danger', $deleteButton->getAttribute('data-confirm-variant'));
        $this->assertStringContainsString('Não é possível desfazer.', $deleteButton->getAttribute('data-confirm'));
        $this->assertSame(0, $xpath->query('//*[@data-maintenance-locked]')->length);
    }

    public function test_sealed_maintenance_detail_explains_why_there_are_no_actions(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.show', $maintenance)));

        $this->assertNull($this->ownerButton($xpath, 'Editar'));
        $this->assertSame(0, $xpath->query('//form[@action="'.route('user.maintenances.destroy', $maintenance).'"]')->length);
        $this->assertStringContainsString('só a oficina que aplicou o selo pode alterar', $this->ownerText($this->ownerElement($xpath, '//*[@data-maintenance-locked]')));
    }

    public function test_previous_owner_record_is_visible_but_locked(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);
        $previousOwner = $this->owner();
        $maintenance = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $previousOwner->id, 'tenant_id' => $previousOwner->tenant_id]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.show', $maintenance)));

        $this->assertStringContainsString('Registrada em outra conta', $this->ownerText($this->ownerElement($xpath, '//*[@data-maintenance-locked]')));
        $this->actingAs($owner)->get(route('user.maintenances.edit', $maintenance))->assertForbidden();
        $this->actingAs($owner)->delete(route('user.maintenances.destroy', $maintenance))->assertForbidden();
    }

    public function test_seller_cannot_edit_or_delete_the_record_after_the_vehicle_changed_owner(): void
    {
        $seller = $this->owner();
        $vehicle = $this->ownedVehicle($seller, ['current_kilometers' => 70_000, 'odometer_at_registration' => 50_000]);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $seller->id,
            'tenant_id' => $seller->tenant_id,
            'maintenance_type' => 'Revisão do vendedor',
            'maintenance_date' => now()->subMonths(3)->toDateString(),
            'kilometers' => 60_000,
        ]);
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $buyer = $this->owner();
        $buyer->vehicles()->attach($vehicle->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $buyer->tenant_id]);

        $xpath = $this->ownerPage($this->actingAs($seller)->get(route('user.maintenances.show', $maintenance)));

        $this->assertNull($this->ownerButton($xpath, 'Editar'));
        $this->assertSame(0, $xpath->query('//form[@action="'.route('user.maintenances.destroy', $maintenance).'"]')->length);
        $this->assertStringContainsString('O veículo não está mais na sua conta', $this->ownerText($this->ownerElement($xpath, '//*[@data-maintenance-locked]')));

        $this->actingAs($seller)->get(route('user.maintenances.edit', $maintenance))->assertForbidden();
        $this->actingAs($seller)
            ->put(route('user.maintenances.update', $maintenance), [
                'maintenance_type' => 'Revisão alterada',
                'service_category' => 'mechanical',
                'maintenance_date' => now()->subMonths(3)->toDateString(),
                'kilometers' => 90_000,
            ])
            ->assertForbidden();
        $this->actingAs($seller)->delete(route('user.maintenances.destroy', $maintenance))->assertForbidden();

        $this->assertSame('Revisão do vendedor', $maintenance->fresh()->maintenance_type);
        $this->assertSame(60_000, $maintenance->fresh()->kilometers);
        $this->assertSame(70_000, $vehicle->fresh()->current_kilometers, 'O hodômetro do comprador não muda.');
    }

    public function test_edit_updates_a_declared_maintenance_and_recalculates_the_odometer(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Revisão errada',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 80_000,
        ]);
        $vehicle->update(['current_kilometers' => 80_000]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.maintenances.edit', $maintenance)));
        $this->assertOnlyHeading($xpath, 'Editar manutenção');
        $this->assertSame(0, $xpath->query('//select[@name="vehicle_id"]')->length, 'O veículo da manutenção não muda.');
        $this->ownerElement($xpath, '//input[@name="maintenance_type"][@value="Revisão errada"]');
        $this->assertSame([], json_decode($this->ownerElement($xpath, '//*[@data-owner-maintenance-form]')->getAttribute('data-mileage'), true)[0]['records'], 'A própria manutenção fica fora da faixa.');

        $this->actingAs($owner)
            ->put(route('user.maintenances.update', $maintenance), [
                'maintenance_type' => 'Revisão dos 60.000 km',
                'service_category' => 'mechanical',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 60_000,
            ])
            ->assertRedirect(route('user.maintenances.show', $maintenance))
            ->assertSessionHas('success', 'Manutenção atualizada.');

        $this->assertSame('Revisão dos 60.000 km', $maintenance->fresh()->maintenance_type);
        $this->assertSame(60_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_sealed_maintenance_cannot_be_edited_or_deleted_by_the_owner(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);

        $this->actingAs($owner)->get(route('user.maintenances.edit', $maintenance))->assertForbidden();
        $this->actingAs($owner)->put(route('user.maintenances.update', $maintenance), ['maintenance_type' => 'X'])->assertForbidden();
        $this->actingAs($owner)->delete(route('user.maintenances.destroy', $maintenance))->assertForbidden();
        $this->assertNotNull($maintenance->fresh());
    }

    public function test_update_linking_a_network_workshop_keeps_an_existing_invoice_valid(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 10_000, 'odometer_at_registration' => 10_000]);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 12_000,
        ]);
        $workshop = Workshop::factory()->create(['name' => 'Oficina Parceira']);
        $payload = [
            'workshop_id' => $workshop->id,
            'maintenance_type' => 'Revisão',
            'service_category' => 'mechanical',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 12_000,
        ];

        $this->actingAs($owner)->put(route('user.maintenances.update', $maintenance), $payload)->assertSessionHasErrors(['invoices']);

        Invoice::factory()->create(['maintenance_id' => $maintenance->id]);

        $this->actingAs($owner)->put(route('user.maintenances.update', $maintenance), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Oficina Parceira', $maintenance->fresh()->workshop_name);
    }

    public function test_destroy_deletes_the_declared_record_with_its_files_and_returns_to_the_vehicle(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'kilometers' => 70_000,
        ]);
        $vehicle->update(['current_kilometers' => 70_000]);
        Storage::disk('public')->put('invoices/nota.pdf', 'pdf');
        Invoice::factory()->create(['maintenance_id' => $maintenance->id, 'file_path' => 'invoices/nota.pdf']);

        $this->actingAs($owner)
            ->delete(route('user.maintenances.destroy', $maintenance))
            ->assertRedirect(route('user.vehicles.show', $vehicle).'#historico')
            ->assertSessionHas('success', 'Manutenção excluída.');

        $this->assertNull($maintenance->fresh());
        $this->assertSame(50_000, $vehicle->fresh()->current_kilometers);
        Storage::disk('public')->assertMissing('invoices/nota.pdf');
    }
}
