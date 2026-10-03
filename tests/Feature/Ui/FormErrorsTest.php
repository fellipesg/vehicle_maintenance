<?php

namespace Tests\Feature\Ui;

use Dom\Element;
use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.form-errors>: resumo role="alert" no topo do formulário, focável, com links para os campos.
 */
class FormErrorsTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_nothing_is_rendered_without_errors_or_below_the_threshold(): void
    {
        $this->assertSame('', trim((string) $this->blade('<x-ui.form-errors />')));

        $this->withViewErrors(['plate' => ['Informe a placa.']]);

        $this->assertSame('', trim((string) $this->blade('<x-ui.form-errors />')), 'Com 1 erro a mensagem do campo basta.');
        $this->assertNotSame('', trim((string) $this->blade('<x-ui.form-errors :threshold="1" />')));
    }

    public function test_summary_is_a_focusable_alert_with_links_to_each_control(): void
    {
        $this->withViewErrors([
            'plate' => ['Informe a placa.', 'A placa precisa de 7 caracteres.'],
            'items.0.price' => ['Informe o preço do item 1.'],
        ]);

        $document = $this->renderComponent('<x-ui.form-errors class="mb-6" />');

        $summary = $this->element($document, '[data-slot="form-errors"]');
        $links = iterator_to_array($summary->querySelectorAll('a[data-form-errors-link]'));

        $this->assertSame('resumo-erros', $summary->getAttribute('id'));
        $this->assertSame('alert', $summary->getAttribute('role'));
        $this->assertSame('-1', $summary->getAttribute('tabindex'));
        $this->assertTrue($summary->hasAttribute('data-autofocus'));
        $this->assertTrue($summary->hasAttribute('autofocus'));
        $this->assertSame('resumo-erros-title', $summary->getAttribute('aria-labelledby'));
        $this->assertSame('Revise 2 campos antes de continuar', trim($this->element($document, '#resumo-erros-title')->textContent));
        $this->assertSame(['#plate', '#items_0_price'], array_map(fn (Element $link): string => $link->getAttribute('href'), $links));
        $this->assertSame(['Informe a placa.', 'Informe o preço do item 1.'], array_map(fn (Element $link): string => trim($link->textContent), $links), 'Uma mensagem por campo.');
        $this->assertContains('mb-6', $this->classesOf($summary));
        $this->assertContains('bg-danger-soft', $this->classesOf($summary));
        $this->assertSame('true', $this->element($document, '[data-slot="form-errors"] svg')->getAttribute('aria-hidden'));
    }

    public function test_ids_map_wildcards_and_messages_without_link(): void
    {
        $this->withViewErrors([
            'credentials' => ['E-mail ou senha não conferem.'],
            'photos.2' => ['A foto 3 passa de 5 MB.'],
        ]);

        $document = $this->renderComponent('<x-ui.form-errors :ids="[\'credentials\' => null, \'photos.*\' => \'photos\']" title="Não foi possível entrar" :autofocus="false" />');

        $summary = $this->element($document, '[data-slot="form-errors"]');
        $items = iterator_to_array($summary->querySelectorAll('li'));

        $this->assertNull($items[0]->querySelector('a'));
        $this->assertSame('E-mail ou senha não conferem.', trim($items[0]->textContent));
        $this->assertSame('#photos', $items[1]->querySelector('a')->getAttribute('href'));
        $this->assertSame('Não foi possível entrar', trim($this->element($document, '#resumo-erros-title')->textContent));
        $this->assertFalse($summary->hasAttribute('data-autofocus'));
        $this->assertFalse($summary->hasAttribute('autofocus'));
    }

    public function test_named_bag_custom_id_and_singular_title(): void
    {
        $this->withViewErrors(['name' => ['Informe o nome.']], 'brand');

        $document = $this->renderComponent('<x-ui.form-errors bag="brand" id="erros-marca" :threshold="1" />');

        $this->assertSame('Revise 1 campo antes de continuar', trim($this->element($document, '#erros-marca-title')->textContent));
        $this->assertSame('', trim((string) $this->blade('<x-ui.form-errors :threshold="1" />')), 'O bag default está vazio.');
    }

    public function test_messages_are_escaped(): void
    {
        $this->withViewErrors(['a' => ['<script>alert(1)</script>'], 'b' => ['ok']]);

        $html = (string) $this->blade('<x-ui.form-errors />');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
