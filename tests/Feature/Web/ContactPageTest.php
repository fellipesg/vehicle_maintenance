<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Página de contato no padrão das páginas públicas (PUB-24): x-ui.page-header com eyebrow de
 * contexto e formulário com x-ui.field (rótulo, dica e erro ligados ao controle).
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_uses_the_public_page_header_with_the_support_email(): void
    {
        $page = $this->page($this->get(route('contact.show'))->assertOk());
        $header = $this->find($page, 'header[data-slot="page-header"]');

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Contato', $this->text($this->find($header, 'h1')));
        $this->assertSame('Suporte', $this->text($this->find($header, '[data-slot="page-header-eyebrow"]')));
        $this->assertSame('mailto:'.config('legal.support_email'), $this->find($header, 'a[data-slot="link"]')->getAttribute('href'));
    }

    public function test_fields_are_labelled_required_and_prefilled_for_a_signed_in_person(): void
    {
        $user = User::factory()->asUser()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);

        $page = $this->page($this->actingAs($user)->get(route('contact.show', ['assunto' => 'support']))->assertOk());
        $form = $this->find($page, 'form[data-slot="contact-form"]');

        foreach (['name' => 'Nome', 'email' => 'E-mail', 'subject' => 'Assunto', 'message' => 'Mensagem'] as $name => $label) {
            $control = $this->find($form, '[name="'.$name.'"]');
            $this->assertTrue($control->hasAttribute('required'), "{$name} obrigatório");
            $this->assertStringStartsWith($label, $this->text($this->find($form, 'label[for="'.$control->getAttribute('id').'"]')));
        }

        $this->assertSame('Ana Souza', $this->find($form, 'input[name="name"]')->getAttribute('value'));
        $this->assertSame('ana@example.com', $this->find($form, 'input[name="email"]')->getAttribute('value'));
        $this->assertSame('email', $this->find($form, 'input[name="email"]')->getAttribute('autocomplete'));
        $this->assertTrue($this->find($form, 'option[value="support"]')->hasAttribute('selected'));
        $message = $this->find($form, 'textarea[name="message"]');
        $this->assertSame('4000', $message->getAttribute('maxlength'));

        // Contador "0/4.000" do x-ui.textarea, ligado ao campo (resources/js/ui/textarea-counter.js).
        $counter = $this->find($form, '#'.$message->getAttribute('id').'-contador');
        $this->assertContains($counter->getAttribute('id'), preg_split('/\s+/', (string) $message->getAttribute('aria-describedby')));
        $this->assertSame('4000', $counter->getAttribute('data-max'));
        $this->assertSame('0 de 4.000 caracteres', $this->text($this->find($counter, '[data-textarea-counter-text]')));
        $this->assertSame('Enviando…', $this->find($form, 'button[type="submit"]')->getAttribute('data-loading-label'));
    }

    public function test_validation_errors_are_tied_to_their_fields(): void
    {
        $this->from(route('contact.show'))
            ->post(route('contact.store'), [])
            ->assertRedirect(route('contact.show'));

        $page = $this->page($this->get(route('contact.show'))->assertOk());
        $message = $this->find($page, 'textarea[name="message"]');

        $this->assertSame('true', $message->getAttribute('aria-invalid'));
        $this->assertContains($message->getAttribute('id').'-error', preg_split('/\s+/', (string) $message->getAttribute('aria-describedby')));
        $this->assertNotNull($page->querySelector('[data-slot="form-errors"][role="alert"]'), 'Com 2 ou mais erros aparece o resumo.');
    }

    public function test_honeypot_stays_out_of_the_tab_order_and_of_screen_readers(): void
    {
        $page = $this->page($this->get(route('contact.show'))->assertOk());
        $honeypot = $this->find($page, 'input[name="website"]');

        $this->assertSame('-1', $honeypot->getAttribute('tabindex'));
        $this->assertSame('true', $honeypot->parentElement->getAttribute('aria-hidden'));
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function find(HTMLDocument|Element $scope, string $selector): Element
    {
        $element = $scope->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}
