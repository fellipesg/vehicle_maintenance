<?php

namespace Tests\Feature\Workshop;

use App\Enums\WorkshopMessageTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Só gatilho com rodada entra num modelo de mensagem.
 *
 * O enum tem quatro casos e o WorkshopMessageTemplateDispatcher sabe disparar dois. O formulário
 * oferecia os quatro e a validação aceitava os quatro, então "Garantia vencendo" e "Cliente sumido"
 * criavam modelo ativo que não manda mensagem nenhuma — e derrubavam a rodada diária de todas as
 * oficinas no `match` do dispatcher. Ver WorkshopMessageTrigger::isDispatchable().
 */
class WorkshopMessageTemplateTriggerTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_the_form_offers_only_the_triggers_that_have_a_round(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.create')));
        $values = [];

        foreach ($xpath->query('//select[@name="trigger"]/option') as $option) {
            $values[] = $option->getAttribute('value');
        }

        // O primeiro é o "Escolha o gatilho…"; depois dele, só o que o dispatcher dispara.
        $this->assertSame([''], array_slice($values, 0, 1));
        $this->assertSame(WorkshopMessageTrigger::dispatchableValues(), array_slice($values, 1));
        $this->assertNotContains(WorkshopMessageTrigger::WarrantyExpiring->value, $values);
        $this->assertNotContains(WorkshopMessageTrigger::Reactivation->value, $values);
    }

    public function test_the_web_store_refuses_a_trigger_without_a_round(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload(WorkshopMessageTrigger::WarrantyExpiring))
            ->assertSessionHasErrors('trigger');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_web_store_still_accepts_a_trigger_with_a_round(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload(WorkshopMessageTrigger::CorrectiveFollowUp))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.message-templates.index'));

        $this->assertSame(1, $user->workshop->messageTemplates()->count());
    }

    public function test_the_api_store_refuses_a_trigger_without_a_round(): void
    {
        $user = $this->workshopUser();

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/message-templates", $this->payload(WorkshopMessageTrigger::Reactivation))
            ->assertStatus(422)
            ->assertJsonValidationErrors('trigger');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WorkshopMessageTrigger $trigger): array
    {
        return [
            'trigger' => $trigger->value,
            'title' => 'Aviso — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, a {{workshop_name}} tem um aviso.',
        ];
    }
}
