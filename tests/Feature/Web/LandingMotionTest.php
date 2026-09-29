<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marquee_exposes_only_the_first_copy_to_assistive_technology(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<ul class="landing-marquee-track[^"]*"(?<attrs>[^>]*)>(?<items>.*?)</ul>#s', $html, $marquee);

        $this->assertNotEmpty($marquee, 'A faixa de capacidades não foi renderizada.');
        $this->assertStringContainsString('aria-label="O que o RevisaLog faz"', $marquee['attrs']);

        preg_match_all('#<li\b[^>]*>#', $marquee['items'], $items);

        // 7 capacidades x 4 cópias: só a primeira cópia fica legível.
        $this->assertCount(28, $items[0]);
        $hiddenItems = array_filter($items[0], fn (string $tag) => str_contains($tag, 'aria-hidden="true"'));
        $this->assertCount(21, $hiddenItems);

        foreach (array_slice($items[0], 0, 7) as $tag) {
            $this->assertStringNotContainsString('aria-hidden', $tag);
        }

        $this->assertStringNotContainsString('<a ', $marquee['items']);
        $this->assertStringNotContainsString('<button', $marquee['items']);
    }

    public function test_marquee_has_pause_control_outside_the_masked_strip(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<button\s[^>]*aria-label="Pausar animação"\s+aria-pressed="false"\s+data-landing-marquee-toggle\s+hidden#s',
            $html
        );
        $this->assertStringContainsString('data-landing-marquee-track', $html);

        $marqueeStart = strpos($html, 'class="landing-marquee ');
        $marqueeEnd = strpos($html, '</ul>', $marqueeStart);
        $toggle = strpos($html, 'data-landing-marquee-toggle');

        $this->assertGreaterThan($marqueeEnd, $toggle, 'O botão não pode ficar dentro da faixa com máscara.');
    }

    /**
     * O botão "Pausar animação" fica no contêiner da faixa, fora dela. Se o hover ou o foco nesse
     * contêiner pausassem a faixa, "Retomar" (aria-pressed="false") deixaria a faixa parada enquanto
     * o botão tivesse o foco ou o ponteiro. Só o hover e o foco da própria faixa pausam.
     */
    public function test_pause_button_focus_does_not_keep_the_strip_paused_after_resume(): void
    {
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString(':focus-within > .landing-marquee', $stylesheet);
        $this->assertStringNotContainsString(':hover > .landing-marquee', $stylesheet);
        $this->assertMatchesRegularExpression(
            '/\.landing-marquee:hover \.landing-marquee-track,\s*\.landing-marquee:focus-within \.landing-marquee-track \{\s*animation-play-state: paused;/',
            $stylesheet,
        );

        $script = file_get_contents(resource_path('js/landing.js'));
        $this->assertStringContainsString("element.style.removeProperty('animation-play-state');", $script);
    }

    /**
     * A indicação de rolagem pula 3 vezes e para em menos de 5s: movimento automático acima disso
     * precisaria de controle de pausa (WCAG 2.2.2), e o botão "Pausar animação" não a controla.
     */
    public function test_scroll_hint_bounce_stops_before_five_seconds(): void
    {
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        preg_match('/\.landing-bounce \{\s*animation: landing-bounce (?<seconds>[\d.]+)s ease-in-out (?<iterations>\d+);/', $stylesheet, $bounce);

        $this->assertNotEmpty($bounce, 'A indicação de rolagem perdeu a animação.');
        $this->assertSame(3, (int) $bounce['iterations']);
        $this->assertLessThan(5.0, (float) $bounce['seconds'] * (int) $bounce['iterations']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#class="landing-bounce[^"]*"[^>]*data-landing-loop#', $html, 'A indicação para sozinha; não é um laço do botão.');
    }

    /**
     * Um laço decorativo só (a flutuação do mock do hero), que o botão "Pausar animação" também para
     * (data-landing-loop). Os pontos de procedência do mock não pulsam: não indicam nada "ao vivo".
     * A indicação de rolagem pula 3 vezes e para, e só aparece a partir de lg.
     */
    public function test_decorative_motion_is_limited_to_the_hero(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'landing-float'));
        $this->assertMatchesRegularExpression('#<div class="landing-float" data-landing-loop>#', $html);
        $this->assertSame(0, substr_count($html, 'landing-dot-pulse'));
        $this->assertMatchesRegularExpression('#class="landing-bounce [^"]*\[animation-iteration-count:3\][^"]*"#', $html);
        $this->assertMatchesRegularExpression('#<div class="relative hidden pb-6 text-center lg:block">\s*<a href="\#como-funciona" class="landing-bounce#', $html);
    }

    /**
     * Entrada do hero (landing-intro, do ObsidianUI): selo, título, texto e ações sobem 12px e ganham
     * nitidez em 500ms, escalonados por --landing-intro-delay, só com movimento permitido. Os
     * laços antigos da landing (pulso dos pontos e segunda flutuação) saíram do CSS.
     */
    public function test_hero_entrance_is_css_only_and_off_with_reduced_motion(): void
    {
        $stylesheet = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: no-preference\) \{\s*\.landing-intro \{\s*animation: landing-intro var\(--duration-hero\) var\(--ease-smooth-out\) both;\s*animation-delay: var\(--landing-intro-delay, 0ms\);/',
            $stylesheet,
        );
        $this->assertMatchesRegularExpression('/@keyframes landing-intro \{\s*from \{\s*opacity: 0;\s*transform: translateY\(var\(--distance-reveal\)\);\s*filter: blur\(3px\);/', $stylesheet);

        $reduce = substr($stylesheet, (int) strrpos($stylesheet, '@media (prefers-reduced-motion: reduce)'));
        $this->assertMatchesRegularExpression('/\.landing-intro,[^{]*\{\s*animation: none !important;/', $reduce);

        foreach (['landing-dot-pulse', 'landing-float-delay'] as $removed) {
            $this->assertStringNotContainsString($removed, $stylesheet);
        }

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'class="landing-intro '), 'Selo, título, texto e ações.');
    }

    /**
     * O botão "Pausar animação" desenha os ícones pelo x-ui.icon (Heroicons pause e play).
     */
    public function test_pause_button_uses_the_icon_library(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $toggleStart = strpos($html, 'data-landing-marquee-toggle');
        $toggle = substr($html, $toggleStart, strpos($html, '</button>', $toggleStart) - $toggleStart);

        $this->assertMatchesRegularExpression('#<svg [^>]*data-slot="icon"[^>]*aria-hidden="true"[^>]*data-landing-marquee-icon="pause"[^>]*>#', $toggle);
        $this->assertMatchesRegularExpression('#<svg [^>]*data-slot="icon"[^>]*aria-hidden="true"[^>]*data-landing-marquee-icon="play" hidden(?:="hidden")?>#', $toggle);
    }

    public function test_static_cards_do_not_lift_on_hover(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('hover:-translate-y-1', $html);
        $this->assertStringNotContainsString('hover:-translate-y-0.5', $html);
    }

    public function test_landing_uses_workshop_seal_terminology(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Selo da oficina')
            ->assertSee('Pela oficina que fez o serviço')
            ->assertSee('a oficina aplica o Selo da oficina')
            ->assertDontSee('Serviço verificado')
            ->assertDontSee('selo verificado')
            ->assertDontSee('Selo verificado');
    }

    public function test_blog_card_hover_motion_respects_reduced_motion(): void
    {
        BlogPost::factory()->create(['title' => 'Troca de óleo por quilometragem']);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('motion-safe:hover:-translate-y-0.5', $html);
        $this->assertStringContainsString('motion-safe:group-hover:scale-[1.03]', $html);
        $this->assertDoesNotMatchRegularExpression('#[\s"]hover:-translate-y-0\.5#', $html);
        $this->assertDoesNotMatchRegularExpression('#[\s"]group-hover:scale-\[1\.03\]#', $html);
    }
}
