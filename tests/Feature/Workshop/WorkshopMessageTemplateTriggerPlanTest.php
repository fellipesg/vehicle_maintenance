<?php

namespace Tests\Feature\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Enums\WorkshopPlan;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageDispatch;
use App\Models\WorkshopMessageTemplate;
use App\Services\Workshop\WorkshopMessageTemplateDispatcher;
use App\Support\Workshop\WorkshopPlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Recorte de gatilhos por plano, de config('workshop_messages.plans.*.triggers').
 *
 * A chave existia no config desde o começo sem nada a ler: o gatilho era limitado só por
 * WorkshopMessageTrigger::isDispatchable(), igual para todo plano. Enquanto os dois gatilhos com
 * rodada eram exatamente os dois do Free, nada vazava — a armadilha era para o futuro: implementar
 * "Garantia vencendo" é trocar um false por true no enum, e isso liberaria para o Free um gatilho
 * que o config diz ser do Pro, sem ninguém notar.
 *
 * O recorte só **estreita**: a lista do plano é cruzada com dispatchableCases(), porque quem decide
 * se um gatilho pode ser escolhido continua sendo a existência da rodada no dispatcher. Plano não
 * liga gatilho sem rodada — por isso `warranty_expiring` no Pro ainda não vale nada.
 */
class WorkshopMessageTemplateTriggerPlanTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    /**
     * O guarda que importa: o config lista `warranty_expiring` no Pro, e isso **não** pode valer
     * como liberação enquanto o gatilho não tiver rodada. Se valesse, o modelo entraria ativo sem
     * disparar nada e cairia no `default => 0` do dispatcher.
     */
    public function test_the_plan_list_only_narrows_what_already_has_a_round(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Pro);

        $this->assertContains(
            WorkshopMessageTrigger::WarrantyExpiring->value,
            config('workshop_messages.plans.pro.triggers'),
            'O config do Pro precisa listar warranty_expiring para este teste provar algo.',
        );

        $this->assertNotContains(WorkshopMessageTrigger::WarrantyExpiring, WorkshopPlanLimits::triggers($user->workshop));
        $this->assertFalse(WorkshopPlanLimits::allowsTrigger($user->workshop, WorkshopMessageTrigger::WarrantyExpiring));

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload(['trigger' => WorkshopMessageTrigger::WarrantyExpiring->value]))
            ->assertSessionHasErrors('trigger');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_form_offers_only_the_triggers_the_plan_includes(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.create')));
        $values = [];

        foreach ($xpath->query('//select[@name="trigger"]/option') as $option) {
            $values[] = $option->getAttribute('value');
        }

        $this->assertSame(['', WorkshopMessageTrigger::ScheduledRevision->value], $values);
    }

    public function test_the_web_store_refuses_a_trigger_outside_the_plan(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasErrors(['trigger' => 'O plano Gratuito não inclui o gatilho "Retorno após serviço". Escolha outro gatilho.']);

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_pro_plan_accepts_the_trigger_the_free_plan_lost(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Pro);
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.message-templates.index'));

        $this->assertSame(
            WorkshopMessageTrigger::CorrectiveFollowUp,
            $user->workshop->messageTemplates()->sole()->trigger,
        );
    }

    public function test_the_api_store_refuses_a_trigger_outside_the_plan(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->postJson("/api/v1/workshops/{$user->workshop->id}/message-templates", $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('trigger');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    /**
     * Quem troca de plano é o admin, e o modelo fica no banco com o gatilho que tinha. Trancar a
     * edição trancaria a tela onde a oficina corrige o texto e desativa o modelo — o mesmo erro
     * que o teto de ativos já aprendeu a não cometer.
     */
    public function test_a_downgraded_workshop_still_edits_the_template_with_the_old_trigger(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->correctiveFollowUp()->create();
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $template), $this->payload(['title' => 'Título corrigido']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Título corrigido', $template->fresh()->title);
        $this->assertSame(WorkshopMessageTrigger::CorrectiveFollowUp, $template->fresh()->trigger);

        // E o select do edit mantém o gatilho herdado, senão salvar o trocaria sem a oficina pedir.
        $xpath = $this->page($this->actingAs($user)->get(route('workshop.message-templates.edit', $template)));
        $selected = $this->element($xpath, '//select[@name="trigger"]/option[@selected]');

        $this->assertSame(WorkshopMessageTrigger::CorrectiveFollowUp->value, $selected->getAttribute('value'));
    }

    /**
     * A porta de saída não é um buraco: o gatilho herdado passa no modelo que já o tem, e em
     * nenhum outro.
     */
    public function test_a_downgraded_workshop_cannot_move_another_template_to_the_old_trigger(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->correctiveFollowUp()->create();
        $other = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->scheduledRevision()->create();
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $other), $this->payload())
            ->assertSessionHasErrors('trigger');

        $this->assertSame(WorkshopMessageTrigger::ScheduledRevision, $other->fresh()->trigger);
    }

    public function test_the_api_update_without_the_trigger_keeps_the_old_one(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->correctiveFollowUp()->create();
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->putJson("/api/v1/workshops/{$user->workshop->id}/message-templates/{$template->id}", [
            'title' => 'Título corrigido',
        ])->assertOk();

        $this->assertSame('Título corrigido', $template->fresh()->title);
        $this->assertSame(WorkshopMessageTrigger::CorrectiveFollowUp, $template->fresh()->trigger);
    }

    public function test_a_plan_without_the_triggers_key_keeps_every_trigger_with_a_round(): void
    {
        $user = $this->workshopOnPlan(WorkshopPlan::Free);
        config(['workshop_messages.plans.free' => ['active_templates' => null]]);

        $this->assertSame(WorkshopMessageTrigger::dispatchableCases(), WorkshopPlanLimits::triggers($user->workshop));

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $user->workshop->messageTemplates()->count());
    }

    /**
     * A validação só guarda a transição, então sem esta guarda o modelo herdado de um plano melhor
     * continuaria mandando mensagem todos os dias depois do rebaixamento.
     */
    public function test_the_daily_round_skips_a_template_whose_trigger_left_the_plan(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-06-01');

        $workshop = $this->workshopWithServicedVehicle();
        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->correctiveFollowUp()->create([
            'min_days_since_service' => 60,
        ]);

        // Controle: no plano que inclui o gatilho, esta mesma montagem dispara.
        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 1);

        // Zera o registro do disparo: ele é o dedupe da rodada, e sem isso a segunda rodada não
        // mandaria nada por motivo nenhum, provando nada sobre o plano.
        WorkshopMessageDispatch::query()->delete();
        $this->narrowFreePlanTo([WorkshopMessageTrigger::ScheduledRevision]);

        $this->assertSame(0, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 0);

        Carbon::setTestNow();
    }

    /**
     * Oficina no plano, com um veículo que ela atendeu há tempo suficiente para o retorno sair.
     */
    private function workshopWithServicedVehicle(): Workshop
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->forUser(User::factory()->asWorkshop()->create())->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 55_000]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 50_000,
            'maintenance_date' => '2026-03-01',
            'service_category' => 'mechanical',
        ]);

        return $workshop;
    }

    private function workshopOnPlan(WorkshopPlan $plan): User
    {
        $user = User::factory()->asWorkshop()->create();
        // `plan` fica fora do $fillable da oficina: quem muda de plano é o admin, não um formulário.
        $user->workshop->forceFill(['plan' => $plan])->save();

        return $user->refresh();
    }

    /**
     * @param  list<WorkshopMessageTrigger>  $triggers
     */
    private function narrowFreePlanTo(array $triggers): void
    {
        config([
            'workshop_messages.plans.free.triggers' => array_map(
                fn (WorkshopMessageTrigger $trigger): string => $trigger->value,
                $triggers,
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'trigger' => WorkshopMessageTrigger::CorrectiveFollowUp->value,
            'title' => 'Como está o serviço? — {{vehicle}}',
            'body' => 'Olá {{customer_name}}, tudo certo com o {{vehicle}}?',
        ], $overrides);
    }
}
