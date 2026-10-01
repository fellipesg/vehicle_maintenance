<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.copy-button>: o botão só aparece com JS, o valor vai escapado num data-*, o check troca o
 * ícone por 1,5s (CSS por data-copied) e a região role="status" anuncia "Copiado"; sem área de
 * transferência, o campo só de leitura já está pronto ao lado.
 */
class CopyButtonTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_button_carries_the_value_and_announces_through_a_status_region(): void
    {
        $html = (string) $this->blade('<x-ui.copy-button :value="$chassis" label="Copiar chassi" />', ['chassis' => '9BWZZZ377VT004251"<x>']);
        $document = $this->parseHtml($html);

        $button = $this->element($document, 'button[data-copy-button]');
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertTrue($button->hasAttribute('hidden'), 'Sem JS não há como copiar: o botão nasce oculto.');
        $this->assertSame('9BWZZZ377VT004251"<x>', $button->getAttribute('data-copy-value'));
        $this->assertStringNotContainsString('"<x>', $html, 'O valor sai escapado.');
        $this->assertSame('Copiado', $button->getAttribute('data-copied-label'));
        $this->assertSame('Copiar chassi', trim($this->element($document, 'button [data-slot="label"]')->textContent));
        $this->assertFalse($button->hasAttribute('aria-label'), 'Com rótulo visível, o nome é o texto.');

        $classes = $this->classesOf($button);
        foreach (['group/copy', 'focus-visible:outline-2', 'focus-visible:outline-ring', 'motion-reduce:transition-none', 'duration-fast', 'ease-smooth-out', 'border-border-strong', 'min-h-8'] as $class) {
            $this->assertContains($class, $classes, "Faltou {$class}.");
        }

        $icons = $button->querySelectorAll('svg');
        $this->assertCount(2, $icons, 'Ícone de copiar e check empilhados.');
        $this->assertContains('group-data-copied/copy:opacity-0', $this->classesOf($icons->item(0)));
        $this->assertContains('group-data-copied/copy:opacity-100', $this->classesOf($icons->item(1)));
        $this->assertContains('text-success', $this->classesOf($icons->item(1)));
        $this->assertContains('motion-safe:scale-50', $this->classesOf($icons->item(1)), 'Com movimento reduzido o check só aparece, sem escala.');

        $status = $this->element($document, '[data-copy-status]');
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertContains('sr-only', $this->classesOf($status));

        $fallback = $this->element($document, 'input[data-copy-fallback]');
        $this->assertTrue($fallback->hasAttribute('readonly'));
        $this->assertTrue($fallback->hasAttribute('hidden'));
        $this->assertSame('9BWZZZ377VT004251"<x>', $fallback->getAttribute('value'));
        $this->assertSame('Copiar chassi: texto para copiar', $fallback->getAttribute('aria-label'));
    }

    public function test_icon_only_variant_names_the_button_with_the_label(): void
    {
        $document = $this->renderComponent('<x-ui.copy-button value="https://revisalog.com.br/v/ABC123" label="Copiar link" icon-only variant="ghost" size="md" copied-label="Link copiado" />');

        $button = $this->element($document, 'button[data-copy-button]');
        $this->assertSame('Copiar link', $button->getAttribute('aria-label'));
        $this->assertSame('Link copiado', $button->getAttribute('data-copied-label'));
        $this->assertNull($button->querySelector('[data-slot="label"]'));
        $this->assertContains('size-10', $this->classesOf($button));
        $this->assertContains('hover:bg-surface-muted', $this->classesOf($button));
    }

    public function test_copy_script_keeps_the_feedback_short_and_the_fallback_accessible(): void
    {
        $source = file_get_contents(resource_path('js/ui/copy.js'));

        $this->assertStringContainsString('export const COPIED_FEEDBACK_MS = 1500;', $source);
        $this->assertStringContainsString('export function initCopyButtons(root = document)', $source);
        $this->assertStringContainsString("dataset.copyButtonReady === 'true'", $source);
        $this->assertStringContainsString("button.dataset.copied = '';", $source);
        $this->assertStringContainsString('fallback.select();', $source);
        $this->assertStringContainsString('Ctrl+C', $source);
        $this->assertStringNotContainsString('window.prompt', $source);
        $this->assertStringNotContainsString('innerHTML', $source);
    }

    public function test_value_and_label_are_required(): void
    {
        foreach ([
            '<x-ui.copy-button label="Copiar chassi" />' => 'x-ui.copy-button precisa de value',
            '<x-ui.copy-button value="9BW" />' => 'x-ui.copy-button precisa de label',
            '<x-ui.copy-button value="9BW" label="Copiar" variant="primary" />' => 'x-ui.copy-button: variant "primary" não existe',
        ] as $template => $message) {
            try {
                $this->blade($template);
                $this->fail("Esperava erro em {$template}");
            } catch (\Throwable $exception) {
                $cause = $exception;

                while (! $cause instanceof \InvalidArgumentException && $cause->getPrevious() !== null) {
                    $cause = $cause->getPrevious();
                }

                $this->assertInstanceOf(\InvalidArgumentException::class, $cause, $exception->getMessage());
                $this->assertStringContainsString($message, $cause->getMessage());
            }
        }
    }
}
