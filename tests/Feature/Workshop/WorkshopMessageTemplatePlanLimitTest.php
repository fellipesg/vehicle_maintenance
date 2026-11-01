<?php

namespace Tests\Feature\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Enums\WorkshopPlan;
use App\Models\User;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Teto de modelos de mensagem ativos por plano, que já estava em config('workshop_messages.plans')
 * sem nada o ler (free 2, pro sem teto) e agora passa por App\Support\Workshop\WorkshopPlanLimits:
 * cada modelo ativo é um disparo a mais por veículo atendido na rodada diária, e o teto vale nas
 * quatro superfícies que criam ou ativam um modelo.
 */
class WorkshopMessageTemplatePlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_web_store_refuses_the_template_past_the_plan_limit(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasErrors('is_active');

        $this->assertSame(2, $user->workshop->messageTemplates()->count());
    }

    public function test_the_web_store_accepts_the_template_inside_the_plan_limit(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.message-templates.index'));

        $this->assertSame(2, $user->workshop->messageTemplates()->count());
    }

    public function test_an_inactive_template_never_hits_the_limit(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $user->workshop->messageTemplates()->count());
        $this->assertSame(2, $user->workshop->messageTemplates()->where('is_active', true)->count());
    }

    public function test_activating_a_template_past_the_limit_is_refused_but_editing_an_active_one_is_not(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);
        $parked = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => false]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $parked), $this->payload(['is_active' => '1']))
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($parked->fresh()->is_active);

        $active = $user->workshop->messageTemplates()->where('is_active', true)->first();

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $active), $this->payload(['title' => 'Novo título', 'is_active' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Novo título', $active->fresh()->title);
    }

    public function test_the_pro_plan_has_no_ceiling_in_the_config(): void
    {
        $user = $this->workshopOnFreePlan();
        $user->workshop->forceFill(['plan' => WorkshopPlan::Pro])->save();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);

        $this->assertNull(config('workshop_messages.plans.pro.active_templates'));

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $user->workshop->messageTemplates()->where('is_active', true)->count());
    }

    public function test_the_api_store_refuses_the_template_past_the_plan_limit(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/message-templates", $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->assertSame(2, $user->workshop->messageTemplates()->count());
    }

    public function test_the_api_update_refuses_to_activate_past_the_plan_limit(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(2)->create(['is_active' => true]);
        $parked = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => false]);

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->putJson("/api/v1/workshops/{$user->workshop->id}/message-templates/{$parked->id}", ['is_active' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->assertFalse($parked->fresh()->is_active);
    }

    /**
     * Uma oficina pode estar acima do teto sem ter burlado nada: o teto é novo (o config existia
     * sem leitor), e o plano é trocado pelo admin, não pela oficina. Nesse estado ela ainda tem de
     * conseguir corrigir o texto do que já tem — a saída é desativar um modelo, e trancar a edição
     * trancava justamente a tela onde se desativa.
     */
    public function test_a_workshop_over_the_ceiling_still_edits_an_active_template(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(3)->create(['is_active' => true]);
        $active = $user->workshop->messageTemplates()->where('is_active', true)->first();

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $active), $this->payload([
                'title' => 'Título corrigido',
                'is_active' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Título corrigido', $active->fresh()->title);
        $this->assertSame(3, $user->workshop->messageTemplates()->where('is_active', true)->count());
    }

    public function test_a_workshop_over_the_ceiling_still_deactivates_a_template(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(3)->create(['is_active' => true]);
        $active = $user->workshop->messageTemplates()->where('is_active', true)->first();

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $active), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($active->fresh()->is_active);
        $this->assertSame(2, $user->workshop->messageTemplates()->where('is_active', true)->count());
    }

    /**
     * Soltar a edição não solta o teto: acima dele, ativar mais um continua recusado.
     */
    public function test_a_workshop_over_the_ceiling_still_cannot_activate_another_template(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(3)->create(['is_active' => true]);
        $parked = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => false]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $parked), $this->payload(['is_active' => '1']))
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($parked->fresh()->is_active);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasErrors('is_active');

        $this->assertSame(3, $user->workshop->messageTemplates()->where('is_active', true)->count());
    }

    public function test_the_api_update_over_the_ceiling_still_edits_an_active_template(): void
    {
        $user = $this->workshopOnFreePlan();
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->count(3)->create(['is_active' => true]);
        $active = $user->workshop->messageTemplates()->where('is_active', true)->first();

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->putJson("/api/v1/workshops/{$user->workshop->id}/message-templates/{$active->id}", [
            'title' => 'Título corrigido',
        ])->assertOk();

        $this->assertSame('Título corrigido', $active->fresh()->title);
        $this->assertTrue($active->fresh()->is_active);
    }

    private function workshopOnFreePlan(): User
    {
        $user = User::factory()->asWorkshop()->create();
        // `plan` fica fora do $fillable da oficina: quem muda de plano é o admin, não um formulário.
        $user->workshop->forceFill(['plan' => WorkshopPlan::Free])->save();

        $this->assertSame(2, config('workshop_messages.plans.free.active_templates'));

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
            'title' => 'Revisão — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, sua revisão está próxima.',
        ], $overrides);
    }
}
