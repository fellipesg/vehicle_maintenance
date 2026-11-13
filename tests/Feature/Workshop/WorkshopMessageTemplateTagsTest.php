<?php

namespace Tests\Feature\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Models\User;
use App\Models\WorkshopMessageTemplate;
use App\Support\WorkshopMessageTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * As variáveis do texto do modelo: o formulário diz quais existem e a validação recusa o resto.
 *
 * `WorkshopMessageTemplateRenderer::render` usa `strtr` com sete chaves exatas, então o que não casa
 * fica no texto e chega ao cliente como está. Nenhuma tela listava as sete e nada na validação
 * avisava: a oficina escrevia `{{cliente}}` e descobria pelo cliente. `{{ customer_name }}`, com
 * espaço dentro das chaves, também não casa.
 */
class WorkshopMessageTemplateTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_web_store_refuses_a_variable_that_does_not_exist(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload([
                'body' => 'Olá {{cliente}}, sua revisão está próxima.',
            ]))
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_web_store_refuses_a_variable_with_spaces_inside_the_braces(): void
    {
        $user = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload([
                'title' => 'Revisão de { { vehicle } }',
                'body' => 'Olá {{ customer_name }}, tudo bem?',
            ]))
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_web_store_accepts_the_seven_variables(): void
    {
        $user = $this->workshopUser();

        $body = collect(array_keys(WorkshopMessageTemplateRenderer::TAGS))
            ->map(fn (string $tag): string => '{{'.$tag.'}}')
            ->implode(' ');

        $this->actingAs($user)
            ->post(route('workshop.message-templates.store'), $this->payload(['body' => $body]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.message-templates.index'));

        $this->assertSame(1, $user->workshop->messageTemplates()->count());
    }

    /**
     * Porta de saída, igual ao teto de ativos e ao recorte de gatilhos por plano: o recorte vale na
     * transição, não no estado. Um modelo gravado antes desta regra continua editável — inclusive
     * para ser desativado, que no formulário da web reenvia título e corpo no mesmo POST.
     */
    public function test_resending_the_stored_text_unchanged_still_passes(): void
    {
        $user = $this->workshopUser();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create([
            'trigger' => WorkshopMessageTrigger::ScheduledRevision,
            'title' => 'Revisão do {{carro}}',
            'body' => 'Olá {{cliente}}, sua revisão está próxima.',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $template), [
                'trigger' => WorkshopMessageTrigger::ScheduledRevision->value,
                'title' => $template->title,
                'body' => $template->body,
                'is_active' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_changing_the_stored_text_to_another_unknown_variable_is_refused(): void
    {
        $user = $this->workshopUser();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($user->workshop)->create([
            'trigger' => WorkshopMessageTrigger::ScheduledRevision,
            'body' => 'Olá {{cliente}}, sua revisão está próxima.',
        ]);

        $this->actingAs($user)
            ->put(route('workshop.message-templates.update', $template), $this->payload([
                'body' => 'Olá {{nome_do_cliente}}, sua revisão está próxima.',
            ]))
            ->assertSessionHasErrors('body');

        $this->assertSame('Olá {{cliente}}, sua revisão está próxima.', $template->fresh()->body);
    }

    public function test_the_api_store_refuses_a_variable_that_does_not_exist(): void
    {
        $user = $this->workshopUser();
        Sanctum::actingAs($user, ['workshops:read', 'workshops:write']);

        $this->postJson(
            "/api/v1/workshops/{$user->workshop->id}/message-templates",
            $this->payload(['body' => 'Olá {{cliente}}, tudo bem?']),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, $user->workshop->messageTemplates()->count());
    }

    public function test_the_form_lists_the_variables_and_no_longer_promises_the_warranty_trigger(): void
    {
        $user = $this->workshopUser();

        $html = $this->withoutVite()
            ->actingAs($user)
            ->get(route('workshop.message-templates.create'))
            ->assertOk()
            ->getContent();

        foreach (array_keys(WorkshopMessageTemplateRenderer::TAGS) as $tag) {
            $this->assertStringContainsString('{{'.$tag.'}}', $html, "A tela deve listar {{{$tag}}}.");
        }

        // "Garantia vencendo" não tem rodada (WorkshopMessageTrigger::isDispatchable), então o select
        // nunca a oferece: a dica que a prometia mandava a oficina configurar o que não existe.
        $this->assertStringNotContainsString('garantia vencendo', $html);
        $this->assertStringNotContainsString('Garantia vencendo', $html);
    }

    private function workshopUser(): User
    {
        return User::factory()->asWorkshop()->create();
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
