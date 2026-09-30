<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Modelos de garantia: status com liga/desliga na lista, "Duplicar" (Novo modelo já preenchido),
 * Excluir bloqueado com a explicação quando o modelo foi usado, campos readonly (não disabled)
 * enquanto houver garantias vigentes, aviso uma vez só e estado vazio com CTA.
 */
class WarrantyTemplatePagesTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_empty_list_explains_and_offers_the_first_template(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.index')));

        $this->assertSingleH1($xpath, 'Modelos de garantia');
        $empty = $this->element($xpath, '//*[@data-templates-empty]');
        $this->assertStringContainsString('Nenhum modelo de garantia ainda', $this->text($empty));
        $this->assertSame(route('workshop.warranty-templates.create'), $this->element($xpath, './/a[contains(., "Criar primeiro modelo")]', $empty)->getAttribute('href'));
    }

    public function test_list_shows_status_switch_duplicate_and_blocked_delete_for_used_templates(): void
    {
        $user = $this->workshopUser();
        $used = WarrantyTemplate::factory()->forWorkshop($user->workshop)->orderScope()->create(['name' => 'Garantia usada', 'is_active' => true]);
        $unused = WarrantyTemplate::factory()->forWorkshop($user->workshop)->itemScope()->inactive()->create(['name' => 'Garantia nova']);
        foreach (range(1, 2) as $index) {
            $order = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $user->workshop->id, 'maintenance_date' => now()->subYears(2)]);
            MaintenanceWarranty::factory()->fromTemplate($used, $order)->create();
        }

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.index')));

        $usedItem = $this->element($xpath, '//li[@data-template="'.$used->id.'"]');
        $this->assertStringContainsString('Usado em 2 OS', $this->text($usedItem));
        $this->assertSame('Ativo', $this->text($this->element($xpath, './/*[@data-template-status]', $usedItem)));

        $switch = $this->element($xpath, './/form[@data-template-toggle]//button[@role="switch"]', $usedItem);
        $this->assertSame('true', $switch->getAttribute('aria-checked'));
        $this->assertSame('0', $this->element($xpath, './/form[@data-template-toggle]//input[@name="is_active"]', $usedItem)->getAttribute('value'));
        $this->assertSame(route('workshop.warranty-templates.update', $used), $this->element($xpath, './/form[@data-template-toggle]', $usedItem)->getAttribute('action'));

        $this->assertSame(
            route('workshop.warranty-templates.create', ['duplicar' => $used->id]),
            $this->element($xpath, './/a[contains(., "Duplicar")]', $usedItem)->getAttribute('href'),
        );

        $blocked = $this->element($xpath, './/button[@data-template-delete-blocked]', $usedItem);
        $this->assertSame('true', $blocked->getAttribute('aria-disabled'));
        $tooltip = $this->element($xpath, '//*[@id="'.$blocked->getAttribute('aria-describedby').'"]');
        $this->assertSame('tooltip', $tooltip->getAttribute('role'));
        $this->assertStringContainsString('Usado em 2 OS: não pode ser excluído', $this->text($tooltip));
        $this->assertSame(0, $this->countNodes($xpath, './/form[.//input[@name="_method"][@value="DELETE"]]', $usedItem));

        $unusedItem = $this->element($xpath, '//li[@data-template="'.$unused->id.'"]');
        $this->assertSame('Inativo', $this->text($this->element($xpath, './/*[@data-template-status]', $unusedItem)));
        $this->assertSame('false', $this->element($xpath, './/button[@role="switch"]', $unusedItem)->getAttribute('aria-checked'));
        $this->assertSame(1, $this->countNodes($xpath, './/form[.//input[@name="_method"][@value="DELETE"]][@data-confirm]', $unusedItem));
    }

    public function test_toggle_from_the_list_says_what_changed(): void
    {
        $user = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->create(['name' => 'Freios 90 dias', 'is_active' => true]);

        $this->actingAs($user)->put(route('workshop.warranty-templates.update', $template), ['is_active' => '0'])
            ->assertRedirect(route('workshop.warranty-templates.index'))
            ->assertSessionHas('success', 'Modelo "Freios 90 dias" desativado: não aparece mais em novas OS.');
        $this->assertFalse($template->fresh()->is_active);

        $this->actingAs($user)->put(route('workshop.warranty-templates.update', $template), ['is_active' => '1'])
            ->assertSessionHas('success', 'Modelo "Freios 90 dias" ativado: aparece ao registrar uma OS.');
    }

    public function test_duplicate_prefills_the_new_template(): void
    {
        $user = $this->workshopUser();
        $source = WarrantyTemplate::factory()->forWorkshop($user->workshop)->itemScope()->create(['name' => 'Bateria 1 ano', 'body' => 'Cobre defeito de fabricação.', 'duration_days' => 365]);
        $other = WarrantyTemplate::factory()->create(['name' => 'De outra oficina']);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.create', ['duplicar' => $source->id])));

        $this->assertSingleH1($xpath, 'Novo modelo de garantia');
        $this->assertSame('Cópia de Bateria 1 ano', $this->element($xpath, '//input[@name="name"]')->getAttribute('value'));
        $this->assertSame('Cobre defeito de fabricação.', $this->text($this->element($xpath, '//textarea[@name="body"]')));
        $this->assertSame('365', $this->element($xpath, '//input[@name="duration_days"]')->getAttribute('value'));
        $this->assertTrue($this->element($xpath, '//select[@name="scope"]/option[@value="item"]')->hasAttribute('selected'));

        $foreign = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.create', ['duplicar' => $other->id])));
        $this->assertSame('', (string) $this->element($foreign, '//input[@name="name"]')->getAttribute('value'), 'Modelo de outra oficina não é copiado.');
    }

    public function test_locked_template_fields_are_readonly_and_can_still_be_saved(): void
    {
        $user = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($user->workshop)->orderScope()->create(['name' => 'Travado', 'is_active' => true]);
        $order = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $user->workshop->id, 'maintenance_date' => now()->subDays(5)]);
        MaintenanceWarranty::factory()->forMaintenance($order)->create(['duration_days' => 90]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.edit', $template)));

        $this->assertSingleH1($xpath, 'Editar modelo de garantia');
        $this->assertSame(1, $this->countNodes($xpath, '//*[@data-template-locked]'), 'O aviso aparece uma vez.');
        $this->assertSame(0, $this->countNodes($xpath, '//form[@data-warranty-template-form]//*[@disabled]'), 'Sem disabled: o texto pode ser selecionado e copiado.');
        foreach (['name', 'duration_days'] as $field) {
            $this->assertTrue($this->element($xpath, '//input[@name="'.$field.'"]')->hasAttribute('readonly'), $field);
        }
        $this->assertTrue($this->element($xpath, '//textarea[@name="body"]')->hasAttribute('readonly'));
        $this->assertSame(0, $this->countNodes($xpath, '//select[@name="scope"]'));
        $this->assertSame(
            route('workshop.warranty-templates.create', ['duplicar' => $template->id]),
            $this->element($xpath, '//*[@data-template-locked]//a[contains(., "Duplicar como novo modelo")]')->getAttribute('href'),
        );
        $this->assertSame('Salvar', $this->text($this->element($xpath, '//*[@data-slot="form-actions"]//button[@type="submit"]')));

        // Os campos readonly vão junto com o status e não contam como alteração.
        $this->actingAs($user)->put(route('workshop.warranty-templates.update', $template), [
            'name' => $template->name,
            'body' => $template->body,
            'duration_days' => $template->duration_days,
            'is_active' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('workshop.warranty-templates.index'));
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_index_shows_the_lock_notice_once(): void
    {
        $user = $this->workshopUser();
        WarrantyTemplate::factory()->forWorkshop($user->workshop)->create();
        $order = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $user->workshop->id, 'maintenance_date' => now()->subDays(5)]);
        MaintenanceWarranty::factory()->forMaintenance($order)->create(['duration_days' => 90]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.warranty-templates.index')));

        $this->assertSame(1, $this->countNodes($xpath, '//*[@data-templates-locked]'));
        $this->assertSame(1, $this->countNodes($xpath, '//a[contains(., "Ver")][contains(@href, "/edit")]'));
    }
}
