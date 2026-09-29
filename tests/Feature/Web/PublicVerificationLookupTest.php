<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Maintenance\VerificationCode;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Conferência pública do Selo da oficina (PUB-X03, PUB-34, PUB-03): /verificar para digitar o
 * código do PDF, /v/{código} com um H1 no page-header e 404 com o campo para tentar de novo.
 */
class PublicVerificationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_page_is_public_with_one_h1_and_a_code_field(): void
    {
        $page = $this->page($this->get(route('verification.lookup'))
            ->assertOk()
            ->assertSee('<title>Conferir selo da oficina · RevisaLog</title>', false));

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Conferir selo da oficina', $this->text($this->element($page, 'h1')));
        $this->assertSame('Selo da oficina', $this->text($this->element($page, '[data-slot="page-header-eyebrow"]')));

        $form = $this->element($page, 'form[data-verification-lookup-form]');
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('verification.lookup'), $form->getAttribute('action'));

        $input = $this->element($page, '#codigo');
        $this->assertSame('codigo', $input->getAttribute('name'));
        $this->assertStringContainsString('Código de verificação', $this->text($this->element($page, 'label[for="codigo"]')));
        $this->assertContains('codigo-hint', explode(' ', (string) $input->getAttribute('aria-describedby')));
        $this->assertStringContainsString('RVL-XXXX-XX', $this->text($this->element($page, '#codigo-hint')));
        $this->assertSame('characters', $input->getAttribute('autocapitalize'));
        $this->assertSame('false', $input->getAttribute('spellcheck'));
        $this->assertTrue($input->hasAttribute('autofocus'));
        $this->assertSame('Conferindo…', $this->element($form, 'button[type="submit"]')->getAttribute('data-loading-label'));
    }

    public function test_typed_code_is_normalized_and_sent_to_the_verification_page(): void
    {
        foreach (['RVL-TEST-12', 'rvl-test-12', ' rvl test 12 ', 'RVLTEST12', 'test-12', 'TEST12'] as $typed) {
            $this->get(route('verification.lookup', ['codigo' => $typed]))
                ->assertRedirect(route('verification.show', 'RVL-TEST-12'));
        }
    }

    public function test_code_in_the_wrong_format_returns_to_the_field_with_an_error(): void
    {
        $page = $this->page($this->get(route('verification.lookup', ['codigo' => 'abc']))
            ->assertUnprocessable()
            ->assertSee('Digite o código no formato RVL-XXXX-XX, como aparece no relatório.'));

        $input = $this->element($page, '#codigo');
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame('abc', $input->getAttribute('value'));
        $this->assertContains('codigo-error', explode(' ', (string) $input->getAttribute('aria-describedby')));
        $this->assertTrue($input->hasAttribute('autofocus'));
    }

    public function test_code_that_is_not_text_shows_the_empty_field(): void
    {
        $this->get('/verificar?codigo[]=RVL-TEST-12')
            ->assertOk()
            ->assertDontSee('Digite o código no formato');
    }

    public function test_verification_url_in_lowercase_redirects_to_the_canonical_code(): void
    {
        $this->get('/v/rvl-test-12')->assertStatus(301)->assertRedirect(route('verification.show', 'RVL-TEST-12'));
    }

    public function test_valid_code_draws_the_h1_in_the_page_header_and_offers_another_lookup(): void
    {
        $this->sealedMaintenance('RVL-PAGE-12', 'Silva Auto Center');

        $page = $this->page($this->get('/v/RVL-PAGE-12')
            ->assertOk()
            ->assertSee('<title>Selo da oficina confirmado · RevisaLog</title>', false));

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Selo da oficina confirmado', $this->text($this->element($page, '[data-slot="page-header"] h1')));
        $this->assertStringContainsString('Silva Auto Center registrou este serviço', $this->text($this->element($page, '[data-slot="page-header-description"]')));

        $seal = $this->element($page, '[data-slot="verification-seal"]');
        $this->assertStringContainsString('prov-seal', (string) $seal->getAttribute('class'));
        $this->assertSame('Silva Auto Center', $this->text($this->element($seal, 'h2')));
        $this->assertNotNull($seal->querySelector('.prov-marker--lg.prov-marker--verified'));
        $this->assertSame(route('verification.lookup'), $this->link($page, 'Conferir outro código')->getAttribute('href'));
    }

    public function test_unknown_code_is_a_404_with_the_field_prefilled_and_ways_out(): void
    {
        $page = $this->page($this->get('/v/RVL-NADA-99')
            ->assertNotFound()
            ->assertSee('<title>Código não encontrado · RevisaLog</title>', false)
            ->assertSee('<meta name="robots" content="noindex">', false));

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Código não encontrado', $this->text($this->element($page, 'h1')));

        $form = $this->element($page, 'form[data-verification-lookup-form]');
        $this->assertSame(route('verification.lookup'), $form->getAttribute('action'));
        $this->assertSame('RVL-NADA-99', $this->element($form, '#codigo')->getAttribute('value'));
        $this->assertFalse($this->element($form, '#codigo')->hasAttribute('aria-invalid'));

        $this->assertStringContainsString('não usa as letras I e O nem os números 0 e 1', $this->text($this->element($page, 'main')));
        $this->assertStringContainsString('declarado pelo proprietário ou pelo lojista', $this->text($this->element($page, 'main')));
        $this->assertSame(route('contact.show', ['assunto' => 'support']), $this->link($page, 'Falar com o suporte')->getAttribute('href'));
        $this->assertSame(route('home'), $this->link($page, 'Voltar ao início')->getAttribute('href'));
    }

    public function test_unknown_code_in_the_url_is_escaped(): void
    {
        $this->get('/v/'.rawurlencode('"><b>x'))
            ->assertNotFound()
            ->assertSee('Código não encontrado')
            ->assertDontSee('"><b>x', false)
            ->assertSee('value="&quot;&gt;&lt;b&gt;x"', false);
    }

    public function test_lookup_works_for_logged_in_accounts_too(): void
    {
        $this->actingAs(User::factory()->asWorkshop()->create())
            ->get(route('verification.lookup'))
            ->assertOk()
            ->assertSee('Conferir selo da oficina');
    }

    public function test_lookup_and_verification_are_throttled(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->get(route('verification.lookup'))->assertOk();
        }

        $this->get(route('verification.lookup'))->assertTooManyRequests();
        $this->get('/v/RVL-NADA-99')->assertTooManyRequests();
    }

    public function test_verification_code_normalization(): void
    {
        $this->assertSame('RVL-ABCD-EF', VerificationCode::normalize('rvl-abcd-ef'));
        $this->assertSame('RVL-ABCD-EF', VerificationCode::normalize('ABCDEF'));
        $this->assertSame('RVL-RVLA-BC', VerificationCode::normalize('RVL-RVLA-BC'));
        $this->assertSame('RVL-RVLA-BC', VerificationCode::normalize('RVLABC'), 'Seis caracteres são o código sem o prefixo, mesmo começando com RVL.');
        $this->assertNull(VerificationCode::normalize('RVL-ABC-EF'));
        $this->assertNull(VerificationCode::normalize(''));
        $this->assertNull(VerificationCode::normalize(null));
    }

    private function sealedMaintenance(string $code, string $workshopName): Maintenance
    {
        $workshop = Workshop::factory()->create(['name' => $workshopName]);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'workshop_id' => $workshop->id,
        ]);
        $maintenance->forceFill([
            'registered_by_type' => 'workshop',
            'verified_at' => now(),
            'verified_workshop_id' => $workshop->id,
            'verification_code' => $code,
        ])->saveQuietly();

        return $maintenance;
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function element(HTMLDocument|Element $root, string $selector): Element
    {
        $element = $root->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function link(HTMLDocument|Element $root, string $label): Element
    {
        foreach ($root->querySelectorAll('a') as $anchor) {
            if ($this->text($anchor) === $label) {
                return $anchor;
            }
        }

        $this->fail("Nenhum link \"{$label}\".");
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));
    }
}
