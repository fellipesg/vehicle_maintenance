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
}
