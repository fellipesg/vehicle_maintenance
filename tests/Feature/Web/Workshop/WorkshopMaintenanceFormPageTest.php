<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Nova OS e Editar OS: a placa como etapa 1 (form GET próprio), o estado vazio de placa sem
 * cadastro, as seções numeradas com fieldset, o resumo de erros, a garantia sempre visível, o
 * preço em R$ com total, as fotos com contador e remoção fora da imagem, e a barra de ações.
 */
class WorkshopMaintenanceFormPageTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_first_visit_shows_only_the_plate_step_in_its_own_get_form(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create')));

        $this->assertSingleH1($xpath, 'Nova ordem de serviço');
        $this->assertStringContainsString('Nova OS · Oficina · RevisaLog', $this->text($this->element($xpath, '//title')));

        $plateForm = $this->element($xpath, '//form[@data-plate-search]');
        $this->assertSame('GET', strtoupper($plateForm->getAttribute('method')));
        $this->assertSame(route('workshop.maintenances.create'), $plateForm->getAttribute('action'));

        $plate = $this->element($xpath, '//input[@name="license_plate"]', $plateForm);
        $this->assertSame('plate', $plate->getAttribute('data-mask'));
        $this->assertSame('off', $plate->getAttribute('autocomplete'));
        $this->assertSame('Buscar veículo', $this->text($this->element($xpath, './/button[@type="submit"]', $plateForm)));

        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-maintenance-os-form]'), 'O resto da OS só aparece com o veículo confirmado.');
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-vehicle-not-found]'));
    }

    public function test_unknown_plate_shows_the_empty_state_with_the_invite_and_hides_the_form(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'zzz-9z99'])));

        $notFound = $this->element($xpath, '//*[@data-vehicle-not-found]');
        $this->assertSame('status', $notFound->getAttribute('role'));
        $this->assertStringContainsString('Veículo ZZZ9Z99 ainda não está no RevisaLog', $this->text($notFound));
        $this->assertStringContainsString('Peça ao proprietário para cadastrar o veículo no app; depois registre a OS aqui.', $this->text($notFound));

        $invite = $this->element($xpath, './/button[@data-copy-button]', $notFound);
        $this->assertStringContainsString(route('register'), $invite->getAttribute('data-copy-value'));
        $this->assertSame('Convite copiado', $invite->getAttribute('data-copied-label'));
        $this->assertSame('Copiar convite para o cliente', $this->text($invite));
        $this->assertSame(route('workshop.maintenances.create'), $this->element($xpath, './/a[normalize-space()="Tentar outra placa"]', $notFound)->getAttribute('href'));

        $this->assertSame('ZZZ9Z99', $this->element($xpath, '//form[@data-plate-search]//input[@name="license_plate"]')->getAttribute('value'));
        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-maintenance-os-form]'));
    }

    public function test_found_vehicle_shows_the_vehicle_card_and_the_numbered_sections(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('ABC1D23', ['current_kilometers' => 45000]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'abc1d23'])));

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        $this->assertSame('status', $card->getAttribute('role'));
        $this->assertStringContainsString('Fiat Argo', $this->text($card));
        $this->assertStringContainsString('ABC1D23', $this->text($card));
        $this->assertStringContainsString('Última quilometragem registrada: 45.000 km', $this->text($card));
        $this->assertSame(route('workshop.maintenances.create'), $this->element($xpath, './/a[contains(., "Trocar veículo")]', $card)->getAttribute('href'));
        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-plate-search]'));

        $form = $this->element($xpath, '//form[@data-maintenance-os-form]');
        $this->assertSame(route('workshop.maintenances.store'), $form->getAttribute('action'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));
        $this->assertSame('ABC1D23', $this->element($xpath, './/input[@type="hidden"][@name="license_plate"]', $form)->getAttribute('value'));

        $legends = array_map(
            fn (\DOMElement $legend): string => $this->text($legend),
            iterator_to_array($xpath->query('.//fieldset[@data-slot="form-section"]/legend', $form)),
        );
        $this->assertSame([
            'Etapa 2: Serviço',
            'Etapa 3: Notas fiscais',
            'Etapa 4: Peças e serviços',
            'Etapa 5: Garantia',
            'Etapa 6: Fotos',
        ], array_map(fn (string $legend): string => preg_replace('/^\d+\s*/', '', $legend), $legends));
        $this->assertSame(5, $this->countNodes($xpath, './/fieldset[@data-slot="form-section"]/legend/h2', $form));
        $this->assertStringContainsString('Veículo', $this->text($this->element($xpath, '//section[@id="secao-veiculo"]//h2')));

        // NF-e antes das peças, com a dica do XML.
        $sectionIds = array_map(fn (\DOMElement $section): string => $section->getAttribute('id'), iterator_to_array($xpath->query('.//fieldset[@data-slot="form-section"]', $form)));
        $this->assertSame(['secao-servico', 'secao-notas', 'secao-itens', 'secao-garantia', 'secao-fotos'], $sectionIds);
        $this->assertStringContainsString('os itens do XML entram sozinhos na OS', $this->text($this->element($xpath, '//fieldset[@id="secao-notas"]')));

        // Km com a última registrada e o sufixo.
        $kilometers = $this->element($xpath, '//input[@name="kilometers"]');
        $this->assertSame('45000', $kilometers->getAttribute('value'));
        $this->assertStringContainsString('kilometers-hint', $kilometers->getAttribute('aria-describedby'));

        // Barra de ações com "Enviando…" no envio.
        $actions = $this->element($xpath, './/*[@data-slot="form-actions"]', $form);
        $this->assertStringContainsString('sticky', $actions->getAttribute('class'));
        $submit = $this->element($xpath, './/button[@type="submit"]', $actions);
        $this->assertSame('Registrar OS', $this->text($submit));
        $this->assertSame('Enviando fotos e notas…', $submit->getAttribute('data-loading-label'));
        $this->assertSame(route('workshop.maintenances.index'), $this->element($xpath, './/a[normalize-space()="Cancelar"]', $actions)->getAttribute('href'));
    }

    public function test_vehicle_without_owner_explains_and_does_not_show_the_form(): void
    {
        $user = $this->workshopUser();
        Vehicle::factory()->create(['license_plate' => 'SEM1D00']);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'SEM1D00']));
        $xpath = $this->page($response);

        $this->assertSame(1, $this->countNodes($xpath, '//*[@data-vehicle-found]'));
        $response->assertSee('Veículo sem proprietário vinculado');
        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-maintenance-os-form]'));
    }

    public function test_warranty_section_is_always_there_with_the_empty_state_when_there_is_no_active_template(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('GAR0A00');
        WarrantyTemplate::factory()->forWorkshop($user->workshop)->orderScope()->inactive()->create();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'GAR0A00'])));

        $section = $this->element($xpath, '//fieldset[@id="secao-garantia"]');
        $empty = $this->element($xpath, './/*[@data-warranty-empty]', $section);
        $this->assertStringContainsString('Você ainda não tem modelos de garantia ativos', $this->text($empty));
        $create = $this->element($xpath, './/a[contains(@href, "'.route('workshop.warranty-templates.create').'")]', $empty);
        $this->assertSame('_blank', $create->getAttribute('target'));
        $this->assertStringContainsString('(abre em nova aba)', $this->text($create));
        $this->assertSame(0, $this->countNodes($xpath, '//select[@name="general_warranty_template_id"]'));
    }

    public function test_warranty_options_carry_the_duration_and_the_selected_one_shows_the_end_date(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('GAR0A01');
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->orderScope()->create(['name' => 'Garantia 90 dias', 'duration_days' => 90]);

        $xpath = $this->page($this->actingAs($user)
            ->withSession(['_old_input' => ['license_plate' => 'GAR0A01', 'maintenance_date' => '2026-03-10', 'general_warranty_template_id' => (string) $template->id]])
            ->get(route('workshop.maintenances.create', ['license_plate' => 'GAR0A01'])));

        $option = $this->element($xpath, '//select[@name="general_warranty_template_id"]/option[@value="'.$template->id.'"]');
        $this->assertSame('90', $option->getAttribute('data-duration-days'));
        $this->assertTrue($option->hasAttribute('selected'));
        $this->assertSame('Válida até 08/06/2026', $this->text($this->element($xpath, '//*[@id="general_warranty_template_id-until"]')));
        $this->assertSame('polite', $this->element($xpath, '//*[@id="general_warranty_template_id-until"]')->getAttribute('aria-live'));
    }

    public function test_item_rows_have_price_in_reais_live_totals_and_ids_the_error_summary_can_reach(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('ITM0A01');
        $itemTemplate = WarrantyTemplate::factory()->forWorkshop($user->workshop)->itemScope()->create(['duration_days' => 365]);

        $response = $this->actingAs($user)
            ->withSession([
                '_old_input' => [
                    'license_plate' => 'ITM0A01',
                    'maintenance_date' => '2026-03-10',
                    'items' => [
                        ['name' => 'Pastilha', 'quantity' => '2', 'unit_price' => '150.50', 'warranty_template_id' => (string) $itemTemplate->id],
                        ['name' => '', 'quantity' => '1'],
                    ],
                ],
                'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
                    'items.1.name' => ['O campo nome do item é obrigatório.'],
                    'kilometers' => ['O campo quilometragem é obrigatório.'],
                ])),
            ])
            ->get(route('workshop.maintenances.create', ['license_plate' => 'ITM0A01']));
        $xpath = $this->page($response);

        // Resumo de erros com link para o campo da linha.
        $summary = $this->element($xpath, '//*[@data-slot="form-errors"]');
        $this->assertSame('alert', $summary->getAttribute('role'));
        $this->assertSame('#items_1_name', $this->element($xpath, './/a[contains(., "nome do item")]', $summary)->getAttribute('href'));
        $this->assertSame('#kilometers', $this->element($xpath, './/a[contains(., "quilometragem")]', $summary)->getAttribute('href'));

        // Campo da linha com erro ligado por id.
        $name = $this->element($xpath, '//input[@id="items_1_name"]');
        $this->assertSame('items[1][name]', $name->getAttribute('name'));
        $this->assertSame('true', $name->getAttribute('aria-invalid'));
        $this->assertSame('items_1_name-error', $name->getAttribute('aria-describedby'));
        $this->assertSame('items_1_name', $this->element($xpath, '//label[@data-item-label="name"][@for="items_1_name"]')->getAttribute('for'));

        // Preço com R$ e totais.
        $price = $this->element($xpath, '//input[@id="items_0_unit_price"]');
        $this->assertSame('decimal', $price->getAttribute('inputmode'));
        $this->assertStringContainsString('R$', $this->text($this->element($xpath, './ancestor::*[@data-slot="input-group"][1]', $price)));
        $this->assertSame('R$ 301,00', $this->text($this->element($xpath, '(//*[@data-item-line-total])[1]')));
        $this->assertSame('R$ 301,00', $this->text($this->element($xpath, '//*[@data-items-total]')));

        // Garantia do item com a data de fim.
        $this->assertSame('Válida até 10/03/2027', $this->text($this->element($xpath, '//*[@id="items_0_warranty_template_id-until"]')));

        // Aviso de que os arquivos precisam ser escolhidos de novo.
        $response->assertSee('Escolha os arquivos de novo');
    }

    public function test_photo_groups_count_the_saved_photos_and_keep_the_image_out_of_the_remove_label(): void
    {
        Storage::fake('public');
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('FOT0A01');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        MaintenancePhoto::factory()->count(3)->create([
            'maintenance_id' => $order->id,
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_BEFORE,
        ]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.edit', $order)));

        $group = $this->element($xpath, '//fieldset[@data-photo-group][@id="photos_vehicle_before-grupo"]');
        $this->assertSame('4', $group->getAttribute('data-max'));
        $this->assertSame('3 de 4 fotos · você pode adicionar mais 1', $this->text($this->element($xpath, './/*[@data-photo-count]', $group)));
        $this->assertSame('Carro antes do serviço', $this->text($this->element($xpath, './legend/h3', $group)));
        $this->assertSame(3, $this->countNodes($xpath, './/li[@data-photo-item]', $group));
        $this->assertSame(0, $this->countNodes($xpath, './/label//img', $group), 'Tocar na foto não marca a remoção.');
        $this->assertSame(3, $this->countNodes($xpath, './/label/input[@type="checkbox"][@name="delete_photos[]"]', $group));
        $this->assertSame('Carro antes do serviço, foto 1 de 3', $this->element($xpath, './/img', $group)->getAttribute('alt'));
        $this->assertStringContainsString('object-contain', $this->element($xpath, './/img', $group)->getAttribute('class'));

        $input = $this->element($xpath, './/input[@type="file"][@name="photos[vehicle_before][]"]', $group);
        $this->assertTrue($input->hasAttribute('data-photo-input'));
        $this->assertSame((string) (5 * 1024 * 1024), $this->element($xpath, './ancestor::*[@data-file-input][1]', $input)->getAttribute('data-max-bytes'));
        $this->assertSame('alert', $this->element($xpath, './/*[@data-photo-limit]', $group)->getAttribute('role'));

        $this->assertSame('0 de 4 fotos · você pode adicionar até 4', $this->text($this->element($xpath, '//fieldset[@id="photos_part_after-grupo"]//*[@data-photo-count]')));
    }

    public function test_edit_page_shows_the_vehicle_read_only_and_warns_about_the_seal(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('EDT0A01');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Troca de óleo']);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.edit', $order));
        $xpath = $this->page($response);

        $this->assertSingleH1($xpath, 'Editar ordem de serviço');
        $this->assertStringContainsString('Editar OS · Oficina · RevisaLog', $this->text($this->element($xpath, '//title')));
        $this->assertStringContainsString('Esta OS tem Selo da oficina ('.$order->verification_code.')', $this->text($this->element($xpath, '//*[@data-sealed-edit-notice]')));
        $this->assertSame(0, $this->countNodes($xpath, '//input[@name="license_plate"]'), 'A placa não é um campo na edição.');
        $this->assertStringContainsString('O veículo não muda depois que a OS é registrada.', $this->text($this->element($xpath, '//section[@id="secao-veiculo"]')));
        $this->assertSame(0, $this->countNodes($xpath, '//section[@id="secao-veiculo"]//a[contains(., "Trocar veículo")]'));

        $trail = array_map(fn (\DOMElement $item): string => $this->text($item), iterator_to_array($xpath->query('//nav[@aria-label="Trilha"]//li')));
        $this->assertSame(['Ordens de serviço', 'Troca de óleo · EDT0A01', 'Editar'], $trail);

        $submit = $this->element($xpath, '//form[@data-maintenance-os-form]//*[@data-slot="form-actions"]//button[@type="submit"]');
        $this->assertSame('Salvar alterações', $this->text($submit));
    }

    public function test_workshop_without_profile_sees_the_empty_state(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();
        $user->unsetRelation('workshop');

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create')));

        $this->assertSingleH1($xpath, 'Nova ordem de serviço');
        $this->assertStringContainsString('Cadastre sua oficina antes de registrar OS', $this->text($this->element($xpath, '//*[@data-slot="empty-state"]')));
        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-plate-search]'));
    }

    public function test_store_with_unknown_plate_returns_the_same_message_as_the_empty_state(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->from(route('workshop.maintenances.create', ['license_plate' => 'NAO0A00']))
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'nao-0a00',
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2026-03-10',
                'kilometers' => 1000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect(route('workshop.maintenances.create', ['license_plate' => 'NAO0A00']))
            ->assertSessionHasErrors(['license_plate' => 'Veículo NAO0A00 ainda não está no RevisaLog. Peça ao proprietário para cadastrar o veículo no app; depois registre a OS aqui.']);

        $this->assertSame(0, Maintenance::count());
    }

    public function test_store_flash_names_the_seal_code(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('SEL0A01');

        $response = $this->actingAs($user)->post(route('workshop.maintenances.store'), [
            'license_plate' => 'SEL0A01',
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 41000,
            'service_category' => 'mechanical',
        ]);

        $order = Maintenance::firstOrFail();
        $this->assertNotNull($order->verification_code);
        $response->assertRedirect(route('workshop.maintenances.show', $order))
            ->assertSessionHas('success', 'OS registrada. Selo da oficina emitido: '.$order->verification_code.'.');
    }
}
