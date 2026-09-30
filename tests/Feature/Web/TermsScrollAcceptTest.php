<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsScrollAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_box_is_a_labelled_keyboard_focusable_region(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->assertSee('<h3 id="terms-accepted-title"', false)
            ->assertSee('tabindex="0"', false)
            ->assertSee('role="region"', false)
            ->assertSee('aria-labelledby="terms-accepted-title"', false)
            ->assertSee('id="terms-accepted-hint"', false)
            ->assertSee('aria-describedby="terms-accepted-hint"', false)
            ->assertSee('data-submit-selector="[data-terms-submit]"', false)
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('id="terms-accepted-status"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Para continuar, role os termos até o final e marque o aceite.');
    }

    public function test_terms_box_and_checkbox_keep_focus_and_fill_at_three_to_one(): void
    {
        $user = User::factory()->asUser()->create();

        $html = $this->actingAs($user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<div class="terms-scroll-box[^"]*focus-visible:-outline-offset-2[^"]*"/', $html);
        $this->assertDoesNotMatchRegularExpression('/terms-scroll-box[^"]*outline-wrench-/', $html);

        $checkbox = $this->termsCheckboxTag($html);

        // Mesmas classes do x-ui.checkbox: preenchimento em accent-foreground (wrench-800, 6,31:1),
        // borda border-input (3,26:1) e o contorno de foco global.
        $this->assertStringContainsString('text-accent-foreground', $checkbox);
        $this->assertStringContainsString('border-input', $checkbox);
        $this->assertStringContainsString('focus-visible:outline-ring', $checkbox);
        $this->assertStringNotContainsString('wrench-', $checkbox);
        $this->assertStringNotContainsString('automotive-', $checkbox);
    }

    public function test_checkbox_is_not_disabled_by_the_server(): void
    {
        $user = User::factory()->asUser()->create();

        $html = $this->actingAs($user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->getContent();

        $checkbox = $this->termsCheckboxTag($html);

        $this->assertDoesNotMatchRegularExpression('/\sdisabled(?=[\s>=])/', $checkbox);
        $this->assertDoesNotMatchRegularExpression('/\schecked(?=[\s>=])/', $checkbox);
        $this->assertDoesNotMatchRegularExpression('/\sdata-terms-accepted(?=[\s>])/', $html);
    }

    public function test_previously_accepted_terms_come_back_checked_and_enabled(): void
    {
        $user = User::factory()->asUser()->create();

        $html = $this->actingAs($user)
            ->withSession(['_old_input' => ['terms_accepted' => '1']])
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->getContent();

        $checkbox = $this->termsCheckboxTag($html);

        $this->assertMatchesRegularExpression('/\schecked(?=[\s>=])/', $checkbox);
        $this->assertDoesNotMatchRegularExpression('/\sdisabled(?=[\s>=])/', $checkbox);
        $this->assertMatchesRegularExpression('/\sdata-terms-accepted(?=[\s>])/', $html);
        $this->assertMatchesRegularExpression('/<p id="terms-accepted-status"[^>]*\shidden(?=[\s>])/', $html);
    }

    public function test_validation_error_round_trip_keeps_terms_accepted_and_enabled(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->from('/usuario/veiculos/novo')
            ->post('/usuario/veiculos', [
                'license_plate' => 'ABC1D23',
                'renavam' => '123',
                'terms_accepted' => '1',
            ])
            ->assertRedirect('/usuario/veiculos/novo')
            ->assertSessionHasErrors('renavam')
            ->assertSessionDoesntHaveErrors('terms_accepted');

        $html = $this->actingAs($user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->getContent();

        $checkbox = $this->termsCheckboxTag($html);

        $this->assertMatchesRegularExpression('/\schecked(?=[\s>=])/', $checkbox);
        $this->assertDoesNotMatchRegularExpression('/\sdisabled(?=[\s>=])/', $checkbox);
    }

    public function test_terms_error_is_linked_to_the_checkbox(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->from('/usuario/veiculos/novo')
            ->post('/usuario/veiculos', ['license_plate' => 'ABC1D23'])
            ->assertSessionHasErrors('terms_accepted');

        $html = $this->actingAs($user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->assertSee('id="terms-accepted-error"', false)
            ->getContent();

        $checkbox = $this->termsCheckboxTag($html);

        $this->assertStringContainsString('aria-describedby="terms-accepted-hint terms-accepted-error"', $checkbox);
        $this->assertStringContainsString('aria-invalid="true"', $checkbox);
    }

    public function test_garage_create_page_uses_the_same_accessible_terms(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->get('/garagem/estoque/novo')
            ->assertOk()
            ->assertSee('role="region"', false)
            ->assertSee('aria-labelledby="terms-accepted-title"', false)
            ->assertSee('data-submit-selector="[data-terms-submit]"', false);
    }

    public function test_heading_level_and_submit_selector_are_configurable(): void
    {
        $this->withViewErrors([]);

        $this->blade('<x-terms-scroll-accept heading-level="h2" submit-selector="#salvar" name="aceite_termos" />')
            ->assertSee('<h2 id="aceite-termos-title"', false)
            ->assertSee('</h2>', false)
            ->assertSee('data-submit-selector="#salvar"', false)
            ->assertSee('name="aceite_termos"', false)
            ->assertSee('aria-labelledby="aceite-termos-title"', false);
    }

    public function test_invalid_heading_level_falls_back_to_h3(): void
    {
        $this->withViewErrors([]);

        $this->blade('<x-terms-scroll-accept heading-level="div" />')
            ->assertSee('<h3 id="terms-accepted-title"', false)
            ->assertDontSee('<div id="terms-accepted-title"', false);
    }

    /**
     * O componente controla o botão com aria-disabled e bloqueia o envio por JS. Um disabled fixo
     * no HTML travaria o formulário quando o JS não carrega.
     */
    public function test_submit_buttons_are_not_disabled_by_the_server(): void
    {
        $owner = User::factory()->asUser()->create();
        $garage = User::factory()->asGarage()->create();

        $pages = [
            [$owner, '/usuario/veiculos/novo'],
            [$garage, '/garagem/estoque/novo'],
        ];

        foreach ($pages as [$user, $path]) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertSame(1, preg_match('/<button\s[^>]*data-terms-submit[^>]*>/s', $html, $matches), $path);
            $this->assertDoesNotMatchRegularExpression('/\sdisabled(?=[\s>=])/', $matches[0], $path);
        }

        foreach (['vehicles/entry/review'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('data-terms-submit>', $source, $view);
            $this->assertStringNotContainsString('data-terms-submit disabled', $source, $view);
        }
    }

    private function termsCheckboxTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<input\s[^>]*data-terms-checkbox[^>]*>/s', $html, $matches));

        return $matches[0];
    }
}
