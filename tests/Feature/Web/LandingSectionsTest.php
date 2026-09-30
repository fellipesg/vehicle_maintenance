<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\AppStorage;
use App\Support\IconLibrary;
use App\Support\LandingSampleVehicle;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Landing da Fase 3 (PUB-13, PUB-14, PUB-17, PUB-26, PUB-27, PUB-31, PUB-32, PUB-33, PUB-35): 9
 * seções, hero com um CTA primário e um secundário, showcase "Produto" em abas com a captura real,
 * CTAs por perfil, mocks do mais antigo para o mais novo com glifos de procedência e movimento
 * sempre atrás de prefers-reduced-motion.
 */
class LandingSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_has_nine_sections_in_the_planned_order(): void
    {
        $page = $this->landing();
        $sections = $page->querySelectorAll('[data-landing-page] > section');

        $headings = [];

        foreach ($sections as $section) {
            $heading = $page->getElementById((string) $section->getAttribute('aria-labelledby'));
            $this->assertInstanceOf(Element::class, $heading, 'Cada seção é rotulada pelo próprio título.');
            $headings[] = $this->text($heading);
        }

        $this->assertSame([
            'O histórico do carro viaja com o carro',
            'Quatro passos, e o histórico fica no carro',
            'Dá para ver o que é selo e o que é declaração',
            'O histórico como ele aparece para você',
            'Para quem é o RevisaLog',
            'O mesmo histórico, no seu bolso',
            'Grátis enquanto a rede cresce',
            'Perguntas frequentes',
            'Comece pelo primeiro veículo',
        ], $headings);

        $this->assertSame(
            [null, 'como-funciona', 'procedencia', 'produto', 'para-quem', 'app', 'preco', 'faq', null],
            array_map(fn (Element $section): ?string => $section->getAttribute('id'), iterator_to_array($sections)),
        );
        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertNull($page->getElementById('telas'));
        $this->assertNull($page->getElementById('recursos'));
    }

    public function test_guest_hero_has_one_primary_and_one_secondary_call_to_action(): void
    {
        $page = $this->landing();
        $actions = $this->element($page, '[data-landing-hero-actions]');
        $links = iterator_to_array($actions->querySelectorAll('a'));

        $this->assertCount(2, $links);
        [$primary, $secondary] = $links;

        $this->assertSame('Começar grátis', $this->text($primary));
        $this->assertSame(route('register'), $primary->getAttribute('href'));
        $this->assertSame('primary', $primary->getAttribute('data-variant'));

        $this->assertSame('Consultar um veículo', $this->text($secondary));
        $this->assertSame(route('vehicle.search'), $secondary->getAttribute('href'));
        $this->assertSame('secondary', $secondary->getAttribute('data-variant'));
        $this->assertIcon('lock-closed', $this->element($secondary, 'svg[data-slot="icon"]'));

        $note = $page->getElementById((string) $secondary->getAttribute('aria-describedby'));
        $this->assertInstanceOf(Element::class, $note);
        $this->assertSame('Grátis, com conta', $this->text($note));

        $this->assertNull($actions->querySelector('a[href="'.route('login').'"]'), '"Entrar" já está na navbar.');
    }

    public function test_hero_badge_is_translucent_and_does_not_repeat_the_price(): void
    {
        $badge = $this->element($this->landing(), '[data-landing-badge]');

        $this->assertTrue($badge->classList->contains('bg-accent'));
        $this->assertTrue($badge->classList->contains('text-accent-foreground'));
        $this->assertFalse($badge->classList->contains('bg-primary'));
        $this->assertStringNotContainsString('bg-wrench-500', (string) $badge->getAttribute('class'));
        $this->assertStringNotContainsStringIgnoringCase('grátis', $this->text($badge));
    }

    public function test_authenticated_hero_goes_to_the_portal_home_without_sign_up_calls(): void
    {
        $workshop = User::factory()->create(['user_type' => 'workshop']);

        $page = $this->landing($workshop);
        $links = iterator_to_array($this->element($page, '[data-landing-hero-actions]')->querySelectorAll('a'));

        $this->assertSame('Ir para o Início', $this->text($links[0]));
        $this->assertSame(route('workshop.dashboard'), $links[0]->getAttribute('href'));
        $this->assertSame(route('vehicle.search'), $links[1]->getAttribute('href'));
        $this->assertNull($links[1]->getAttribute('aria-describedby'));
        $this->assertNull($page->getElementById('landing-consulta-nota'));

        $main = $this->text($this->element($page, '[data-landing-page]'));
        $this->assertStringNotContainsString('Começar grátis', $main);
        $this->assertStringNotContainsString('Já tenho conta', $main);
    }

    public function test_hero_is_shorter_on_phones(): void
    {
        $page = $this->landing();
        $hero = $this->element($page, '[data-landing-hero]');

        $facts = $this->element($hero, 'dl');
        $this->assertTrue($facts->classList->contains('hidden'));
        $this->assertTrue($facts->classList->contains('sm:flex'));

        $mockFrame = $this->element($hero, '[data-landing-loop] > .relative');
        $this->assertTrue($mockFrame->classList->contains('max-h-[26rem]'));
        $this->assertTrue($mockFrame->classList->contains('[mask-image:linear-gradient(to_bottom,#000_75%,transparent)]'));
        $this->assertTrue($mockFrame->classList->contains('sm:max-h-none'));

        $scrollCue = $this->element($hero, 'a[href="#como-funciona"]');
        $this->assertTrue($scrollCue->parentElement->classList->contains('hidden'));
        $this->assertTrue($scrollCue->parentElement->classList->contains('lg:block'));

        $this->assertNull($hero->querySelector('ul.flex-wrap'), 'As pílulas "Sem cartão" repetiam o dl.');
    }

    public function test_hero_intro_is_a_short_stagger_of_the_text(): void
    {
        $intro = iterator_to_array($this->element($this->landing(), '[data-landing-hero]')->querySelectorAll('.landing-intro'));

        $this->assertSame(['p', 'h1', 'p', 'div'], array_map(fn (Element $element): string => strtolower($element->tagName), $intro));
        $this->assertFalse($intro[0]->classList->contains('[--landing-intro-delay:40ms]'));
        $this->assertTrue($intro[1]->classList->contains('[--landing-intro-delay:40ms]'));
        $this->assertTrue($intro[2]->classList->contains('[--landing-intro-delay:100ms]'));
        $this->assertTrue($intro[3]->classList->contains('[--landing-intro-delay:160ms]'));
    }

    public function test_how_it_works_rail_draws_step_by_step_and_respects_reduced_motion(): void
    {
        $rail = $this->element($this->landing(), '#como-funciona ol[data-landing-rail]');

        $this->assertTrue($rail->classList->contains('group/rail'));
        $this->assertSame('', $rail->getAttribute('data-landing-rail'), 'Sem JS o trilho já vem desenhado.');

        $steps = $this->childrenOf($rail, 'li');
        $this->assertCount(4, $steps);
        $this->assertSame(
            ['Cadastre o veículo', 'Registre cada serviço', 'Consulte quando precisar', 'Exporte o PDF'],
            array_map(fn (Element $step): string => $this->text($this->element($step, 'h3')), $steps),
        );

        foreach ($steps as $index => $step) {
            $this->assertSame('--landing-step: '.$index, $step->getAttribute('style'));

            $number = $this->element($step, 'span[aria-hidden="true"].rounded-full.size-10');
            $this->assertSame(sprintf('%02d', $index + 1), $this->text($number));
            $this->assertTrue($number->classList->contains('motion-reduce:transition-none'));
            $this->assertTrue($number->classList->contains('group-data-[landing-rail=pending]/rail:bg-surface'));

            $connector = $step->querySelector('span[aria-hidden="true"].absolute > span');

            if ($index === 3) {
                $this->assertNull($connector, 'O último passo não tem conector.');

                continue;
            }

            $this->assertInstanceOf(Element::class, $connector);
            $this->assertTrue($connector->classList->contains('transition-[scale]'));
            $this->assertTrue($connector->classList->contains('motion-reduce:transition-none'));
            $this->assertTrue($connector->classList->contains('group-data-[landing-rail=pending]/rail:scale-y-0'));
            $this->assertTrue($connector->classList->contains('lg:group-data-[landing-rail=pending]/rail:scale-x-0'));
        }
    }

    public function test_provenance_section_uses_glyphs_instead_of_letters(): void
    {
        $section = $this->element($this->landing(), '#procedencia');
        $glyphs = iterator_to_array($section->querySelectorAll('[data-landing-glyph]'));

        $this->assertSame(['sealed', 'declared'], array_map(fn (Element $glyph): string => (string) $glyph->getAttribute('data-landing-glyph'), $glyphs));

        foreach ($glyphs as $glyph) {
            $this->assertSame('true', $glyph->getAttribute('aria-hidden'));
            $this->assertSame('', $this->text($glyph), 'Marcador sem letras (S/D, OS/D).');
            $this->assertNotNull($glyph->querySelector('svg[data-slot="icon"]'));
        }

        $this->assertStringContainsString('Selo da oficina', $this->text($section));
        $this->assertStringContainsString('Declarada', $this->text($section));
        $this->assertStringNotContainsString('text-teal-800', $section->innerHTML);
        $this->assertStringNotContainsString('text-amber-800', $section->innerHTML);
    }

    public function test_product_showcase_is_an_accessible_tab_list_with_the_real_capture(): void
    {
        $page = $this->landing();
        $showcase = $this->element($page, '#produto [data-landing-showcase]');
        $tablist = $this->element($showcase, '[role="tablist"]');

        $this->assertSame('Telas do produto', $tablist->getAttribute('aria-label'));

        $tabs = iterator_to_array($tablist->querySelectorAll('[role="tab"]'));
        $this->assertSame(['Linha do tempo', 'Busca', 'PDF'], array_map(fn (Element $tab): string => $this->text($tab), $tabs));
        $this->assertSame(['true', 'false', 'false'], array_map(fn (Element $tab): ?string => $tab->getAttribute('aria-selected'), $tabs));
        $this->assertSame(['0', '-1', '-1'], array_map(fn (Element $tab): ?string => $tab->getAttribute('tabindex'), $tabs));

        foreach ($tabs as $index => $tab) {
            $panel = $page->getElementById((string) $tab->getAttribute('aria-controls'));

            $this->assertInstanceOf(Element::class, $panel);
            $this->assertSame('tabpanel', $panel->getAttribute('role'));
            $this->assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
            $this->assertSame($index !== 0, $panel->hasAttribute('hidden'));
            $this->assertTrue($panel->hasAttribute('data-landing-showcase-panel'));

            // Troca de painel: fade de 200ms com @starting-style, só sem redução de movimento.
            $halves = iterator_to_array($panel->querySelectorAll('.motion-safe\:starting\:opacity-0'));
            $this->assertGreaterThanOrEqual(2, count($halves));

            foreach ($halves as $half) {
                $this->assertTrue($half->classList->contains('motion-safe:duration-base'));
                $this->assertFalse($half->classList->contains('transition-all'));
            }
        }

        $timeline = $this->element($page, '#produto-linha-do-tempo');
        $capture = $this->element($timeline, 'img');
        $this->assertSame(AppStorage::landingUrl('app-timeline.png'), $capture->getAttribute('src'));
        $this->assertSame('lazy', $capture->getAttribute('loading'));
        $this->assertStringContainsString('da manutenção mais antiga à mais recente', (string) $capture->getAttribute('alt'));
        $this->assertNull($showcase->querySelector('[data-landing-timeline-mock]'), 'Sem celular falso em CSS quando existe a captura.');

        $this->assertSame('img', $this->element($page, '#produto-busca [data-landing-search-mock]')->getAttribute('role'));
        $this->assertSame('img', $this->element($page, '#produto-pdf [data-landing-pdf-mock]')->getAttribute('role'));
    }

    public function test_mocks_show_services_oldest_first_with_one_dot_per_service(): void
    {
        $page = $this->landing();
        $expected = array_map(fn (array $event): string => $event['sealed'] ? 'sealed' : 'declared', LandingSampleVehicle::events());

        foreach (['[data-landing-timeline-mock]', '[data-landing-pdf-mock]'] as $mockSelector) {
            $mock = $this->element($page, $mockSelector);
            $glyphs = array_map(fn (Element $glyph): string => (string) $glyph->getAttribute('data-landing-glyph'), iterator_to_array($mock->querySelectorAll('[data-landing-glyph]')));

            $this->assertSame($expected, $glyphs, $mockSelector);
            $this->assertKilometersIncrease($this->text($mock), $mockSelector);
        }

        foreach (['[data-landing-timeline-mock]', '[data-landing-search-mock]'] as $mockSelector) {
            $dots = array_map(
                fn (Element $dot): string => $dot->classList->contains('prov-dot--verified') ? 'sealed' : 'declared',
                iterator_to_array($this->element($page, $mockSelector.' [data-landing-dots]')->querySelectorAll('.prov-dot')),
            );

            $this->assertSame($expected, $dots, $mockSelector);
        }

        $timeline = $this->text($this->element($page, '[data-landing-timeline-mock]'));
        $this->assertStringContainsString('Placa atual', $timeline);
        $this->assertStringContainsString(LandingSampleVehicle::CHASSIS, $timeline);
        $this->assertStringContainsString(LandingSampleVehicle::RENAVAM, $timeline);
        $this->assertStringNotContainsString('landing-dot-pulse', $page->saveHtml());

        $search = $this->text($this->element($page, '[data-landing-search-mock]'));
        $this->assertStringContainsString('93H••••••••••4251', $search);
        $this->assertStringContainsString('•••••••9256', $search);
        $this->assertStringNotContainsString(LandingSampleVehicle::CHASSIS, $search);
    }

    public function test_sample_vehicle_services_are_chronological(): void
    {
        $events = LandingSampleVehicle::events();
        $dates = array_map(fn (array $event): string => implode('', array_reverse(explode('/', $event['date']))), $events);
        $kilometers = array_column($events, 'km');

        $sortedDates = $dates;
        sort($sortedDates);
        $this->assertSame($sortedDates, $dates);

        foreach (array_slice($kilometers, 1) as $index => $kilometer) {
            $this->assertGreaterThan($kilometers[$index], $kilometer);
        }

        $this->assertSame('3 com selo · 1 declarada', LandingSampleVehicle::provenanceSummary());
        $this->assertSame('40.012 km', LandingSampleVehicle::kilometers(40012));
    }

    public function test_audience_cards_offer_a_way_in_for_each_profile(): void
    {
        $page = $this->landing();
        $partnership = route('contact.show', ['assunto' => 'partnership']);

        $owner = $this->element($page, '[data-landing-audience="proprietario"]');
        $this->assertSame(['Começar grátis' => route('register')], $this->linksOf($owner));

        $dealer = $this->element($page, '[data-landing-audience="lojista"]');
        $this->assertSame([
            'Fale com a equipe' => $partnership,
            'Já tenho conta · Entrar como lojista' => route('login.lojista'),
        ], $this->linksOf($dealer));

        $workshop = $this->element($page, '[data-landing-audience="oficina"]');
        $this->assertSame([
            'Quero ser oficina parceira' => $partnership,
            'Já tenho conta · Entrar como oficina' => route('login.oficina'),
        ], $this->linksOf($workshop));

        foreach ([$owner, $dealer, $workshop] as $card) {
            $this->assertSame('article', strtolower($card->tagName));
            $this->assertNotNull($page->getElementById((string) $card->getAttribute('aria-labelledby')));
        }
    }

    public function test_audience_cards_hide_sign_up_and_sign_in_for_authenticated_users(): void
    {
        $page = $this->landing(User::factory()->create());
        $partnership = route('contact.show', ['assunto' => 'partnership']);

        $this->assertSame([], $this->linksOf($this->element($page, '[data-landing-audience="proprietario"]')));
        $this->assertSame(['Fale com a equipe' => $partnership], $this->linksOf($this->element($page, '[data-landing-audience="lojista"]')));
        $this->assertSame(['Quero ser oficina parceira' => $partnership], $this->linksOf($this->element($page, '[data-landing-audience="oficina"]')));
    }

    public function test_calls_to_action_slide_their_arrow_only_without_reduced_motion(): void
    {
        $ctas = iterator_to_array($this->landing()->querySelectorAll('[data-landing-cta]'));

        $this->assertGreaterThanOrEqual(5, count($ctas));

        foreach ($ctas as $cta) {
            $arrow = $cta->lastElementChild;

            $this->assertInstanceOf(Element::class, $arrow);
            $this->assertSame('svg', strtolower($arrow->tagName));
            $this->assertSame('icon', $arrow->getAttribute('data-slot'));
            $this->assertSame('true', $arrow->getAttribute('aria-hidden'));
            $this->assertIcon('arrow-right', $arrow);
            $this->assertCount(1, $cta->querySelectorAll('[data-slot="label"]'), 'O texto não é duplicado.');

            foreach ([
                'motion-safe:hover:*:data-[slot=icon]:translate-x-[3px]',
                'motion-safe:focus-visible:*:data-[slot=icon]:translate-x-[3px]',
                'motion-safe:active:*:data-[slot=icon]:translate-x-[5px]',
                'motion-reduce:*:data-[slot=icon]:transition-none',
                '*:data-[slot=icon]:duration-base',
                '*:data-[slot=icon]:ease-smooth-out',
            ] as $class) {
                $this->assertTrue($cta->classList->contains($class), $class);
            }
        }
    }

    public function test_page_copy_is_about_the_user_not_about_the_layout(): void
    {
        $text = $this->text($this->element($this->landing(), '[data-landing-page]'));

        foreach (['marketing vazio', 'Não é uma lista de ícones', 'não um mock', 'empilhados no celular', 'Role para saber mais', 'Cadastrar como usuário', 'Ir para o painel'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $text);
        }

        $this->assertStringContainsString('Ainda não cobramos e não há planos definidos. Quando houver, avisaremos com antecedência.', $text);
    }

    public function test_landing_script_draws_the_rail_and_pauses_every_loop(): void
    {
        $script = File::get(resource_path('js/landing.js'));

        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $script);
        $this->assertStringContainsString("'[data-landing-rail]'", $script);
        $this->assertStringContainsString("setAttribute('data-landing-rail', 'pending')", $script);
        $this->assertStringContainsString("setAttribute('data-landing-rail', 'drawn')", $script);
        $this->assertStringContainsString("'[data-landing-marquee-track], [data-landing-loop]'", $script);
        $this->assertStringContainsString("element.style.animationPlayState = 'paused'", $script);
        $this->assertStringContainsString('initLanding();', File::get(resource_path('js/app.js')));
    }

    public function test_public_footer_follows_the_new_landing(): void
    {
        $page = $this->landing();
        $footer = $this->element($page, 'footer nav[aria-label="Rodapé"]');

        $this->assertNotNull($footer->querySelector('a[href="'.route('home').'#produto"]'));
        $this->assertNull($footer->querySelector('a[href$="#telas"], a[href$="#recursos"]'));

        $search = $this->element($footer, 'a[href="'.route('vehicle.search').'"]');
        $this->assertSame('Consultar um veículo (grátis, com conta)', $this->text($search));

        $partner = $this->element($footer, 'a[href="'.route('contact.show', ['assunto' => 'partnership']).'"]');
        $this->assertSame('Quero ser parceiro', $this->text($partner));

        foreach ($footer->querySelectorAll('ul') as $list) {
            $this->assertFalse($list->classList->contains('text-xs'), 'Links do rodapé em text-sm, com alvo de 40px.');
        }

        foreach ($footer->querySelectorAll('a') as $link) {
            $this->assertTrue($link->classList->contains('min-h-10'));
        }
    }

    private function landing(?User $user = null): HTMLDocument
    {
        $response = $user ? $this->actingAs($user)->get(route('home')) : $this->get(route('home'));

        return HTMLDocument::createFromString((string) $response->assertOk()->getContent(), LIBXML_NOERROR);
    }

    private function element(HTMLDocument|Element $scope, string $selector): Element
    {
        $element = $scope->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));
    }

    /**
     * @return array<string, string>
     */
    private function linksOf(Element $scope): array
    {
        $links = [];

        foreach ($scope->querySelectorAll('a') as $link) {
            $links[$this->text($link)] = (string) $link->getAttribute('href');
        }

        return $links;
    }

    /**
     * @return list<Element>
     */
    private function childrenOf(Element $parent, string $tagName): array
    {
        $children = [];

        for ($child = $parent->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if (strtolower($child->tagName) === $tagName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function assertIcon(string $name, Element $svg): void
    {
        preg_match('/\sd="([^"]+)"/', IconLibrary::body($name), $expected);
        $path = $svg->querySelector('path');

        $this->assertInstanceOf(Element::class, $path);
        $this->assertSame($expected[1] ?? null, $path->getAttribute('d'), "Ícone {$name}.");
    }

    private function assertKilometersIncrease(string $text, string $context): void
    {
        preg_match_all('/(\d{1,3}(?:\.\d{3})+) km/u', $text, $matches);
        $kilometers = array_map(fn (string $value): int => (int) str_replace('.', '', $value), $matches[1]);

        $this->assertCount(count(LandingSampleVehicle::events()), $kilometers, $context);

        foreach (array_slice($kilometers, 1) as $index => $kilometer) {
            $this->assertGreaterThan($kilometers[$index], $kilometer, $context.': quilometragem sempre subindo.');
        }
    }
}
