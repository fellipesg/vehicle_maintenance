<?php

namespace Tests\Feature\Web\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * O liga/desliga "Modelo ativo" do formulário de mensagem automática.
 *
 * A caixa anterior não tinha o hidden do estado desligado: desmarcada, nada era enviado, e aí o
 * store criava ativo (default da coluna) e o update nem tocava na coluna (regra `sometimes`). Como
 * a lista de modelos de mensagem não tem o liga/desliga que a de garantias tem, desativar um modelo
 * pela web era impossível. O <x-ui.switch> traz esse hidden por padrão, como no formulário de
 * modelos de garantia.
 */
class WorkshopMessageTemplateFormPageTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_the_create_form_carries_the_off_state_and_starts_on(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.create')));

        $section = $this->element($xpath, '//*[@data-slot="switch"][.//input[@name="is_active"]]');

        $this->assertSame('0', $this->element($xpath, './/input[@type="hidden"][@name="is_active"]', $section)->getAttribute('value'));

        $control = $this->element($xpath, './/input[@data-slot="control"]', $section);
        $this->assertSame('1', $control->getAttribute('value'));
        $this->assertSame('true', $control->getAttribute('aria-checked'));

        // O texto de apoio ia num atributo `hint`, que o componente não tem: não aparecia na tela.
        $this->assertStringContainsString('não conta no limite do plano', $this->text($section));
    }

    public function test_the_edit_form_of_an_inactive_template_starts_off(): void
    {
        $user = $this->workshopUser();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => false]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.edit', $template)));

        $control = $this->element($xpath, '//input[@data-slot="control"][@name="is_active"]');

        $this->assertSame('false', $control->getAttribute('aria-checked'));
        $this->assertFalse($control->hasAttribute('checked'));
    }

    public function test_the_switch_turned_off_creates_an_inactive_template(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->formBody(false))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.message-templates.index'));

        $this->assertFalse($user->workshop->messageTemplates()->sole()->is_active);
    }

    public function test_the_switch_turned_on_still_creates_an_active_template(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->formBody(true))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->workshop->messageTemplates()->sole()->is_active);
    }

    public function test_the_switch_turned_off_deactivates_the_template_on_edit(): void
    {
        $user = $this->workshopUser();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => true]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $template), $this->formBody(false))
            ->assertSessionHasNoErrors();

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_the_hidden_field_comes_before_the_control_so_turning_it_on_wins(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.create')));
        $inputs = $xpath->query('//input[@name="is_active"]');

        // Marcada, a caixa manda os dois campos: vale o último, então o hidden do "0" vem antes.
        $this->assertSame(2, $inputs->length);
        $this->assertSame('hidden', $inputs->item(0)->getAttribute('type'));
        $this->assertSame('0', $inputs->item(0)->getAttribute('value'));
        $this->assertSame('checkbox', $inputs->item(1)->getAttribute('type'));
        $this->assertSame('1', $inputs->item(1)->getAttribute('value'));
    }

    /**
     * O que chega ao servidor em cada estado do liga/desliga.
     *
     * @return array<string, mixed>
     */
    private function formBody(bool $active): array
    {
        return [
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, sua revisão está próxima.',
            'is_active' => $active ? '1' : '0',
        ];
    }
}
