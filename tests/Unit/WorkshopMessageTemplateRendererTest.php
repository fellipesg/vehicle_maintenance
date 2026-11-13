<?php

namespace Tests\Unit;

use App\Support\WorkshopMessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * O renderer devolve texto puro: o Blade do e-mail e do sino escapam, e o assunto do MailMessage e
 * o payload do FCM são texto. Escapar aqui dobrava o escape de "José & Filhos".
 */
class WorkshopMessageTemplateRendererTest extends TestCase
{
    public function test_an_ampersand_in_the_workshop_name_is_not_escaped(): void
    {
        $rendered = (new WorkshopMessageTemplateRenderer)->render(
            'Olá {{customer_name}}, da {{workshop_name}}.',
            ['customer_name' => 'João', 'workshop_name' => 'José & Filhos'],
        );

        $this->assertSame('Olá João, da José & Filhos.', $rendered);
        $this->assertStringNotContainsString('&amp;', $rendered);
    }

    public function test_quotes_and_angle_brackets_come_through_as_typed(): void
    {
        $rendered = (new WorkshopMessageTemplateRenderer)->render(
            '{{workshop_name}} — {{vehicle}}',
            ['workshop_name' => 'Oficina "O Pai & o Filho"', 'vehicle' => 'Fiat <Argo>'],
        );

        $this->assertSame('Oficina "O Pai & o Filho" — Fiat <Argo>', $rendered);
    }

    public function test_kilometers_keep_the_thousands_separator_and_missing_values_are_empty(): void
    {
        $rendered = (new WorkshopMessageTemplateRenderer)->render(
            '{{estimated_km}} / {{next_due_km}} / {{last_service}}',
            ['estimated_km' => 123456],
        );

        $this->assertSame('123.456 /  / ', $rendered);
    }

    /**
     * TAGS é a lista que o formulário mostra e que App\Rules\KnownMessageTemplateTags cobra. Se ela
     * e o `render()` divergirem, a tela passa a prometer uma variável que chega literal ao cliente
     * (ou a validação recusa uma que funcionaria).
     */
    public function test_every_listed_tag_is_one_the_render_actually_replaces(): void
    {
        $template = collect(array_keys(WorkshopMessageTemplateRenderer::TAGS))
            ->map(fn (string $tag): string => '{{'.$tag.'}}')
            ->implode('|');

        $rendered = (new WorkshopMessageTemplateRenderer)->render($template, [
            'workshop_name' => 'Oficina',
            'customer_name' => 'João',
            'vehicle' => 'Fiat Argo',
            'estimated_km' => 1000,
            'next_due_km' => 2000,
            'days_since_service' => 30,
            'last_service' => '01/01/2026',
        ]);

        $this->assertStringNotContainsString('{{', $rendered, 'Alguma variável de TAGS não é trocada pelo render().');
        $this->assertStringNotContainsString('}}', $rendered);
    }

    public function test_the_rendered_text_keeps_a_variable_that_does_not_exist(): void
    {
        $rendered = (new WorkshopMessageTemplateRenderer)->render(
            'Olá {{cliente}} e {{ customer_name }}.',
            ['customer_name' => 'João'],
        );

        // É por isto que a validação recusa as duas formas: elas chegam ao cliente como estão.
        $this->assertSame('Olá {{cliente}} e {{ customer_name }}.', $rendered);
    }
}
