<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.lightbox>: <dialog> escuro no motor de resources/js/ui/dialog.js, com carrossel em
 * scroll-snap (deslizar nativo), slides com aria-roledescription, contador, região aria-live e
 * setas que ficam aria-disabled nas pontas. O movimento respeita prefers-reduced-motion.
 */
class LightboxTest extends TestCase
{
    use InspectsRenderedComponents;

    /**
     * @var list<array{src: string, alt: string, caption: string}>
     */
    private const ITEMS = [
        ['src' => 'https://cdn.revisalog.test/a.jpg', 'alt' => 'Foto 1 de 3 — Troca de óleo, 12/03/2026. Carro antes do serviço', 'caption' => 'Carro antes do serviço'],
        ['src' => 'https://cdn.revisalog.test/b.jpg', 'alt' => 'Foto 2 de 3 — Troca de óleo, 12/03/2026. Carro depois do serviço', 'caption' => 'Carro depois do serviço'],
        ['src' => 'https://cdn.revisalog.test/c.jpg', 'alt' => 'Foto 3 de 3 — Troca de óleo, 12/03/2026. Peças ao retirar', 'caption' => ''],
    ];

    public function test_dialog_is_a_carousel_of_slides_with_contextual_alt(): void
    {
        $document = $this->renderComponent('<x-ui.lightbox id="fotos-os" title="Fotos do serviço" :items="$items" />', ['items' => self::ITEMS]);

        $dialog = $this->element($document, 'dialog#fotos-os');
        $this->assertTrue($dialog->hasAttribute('data-ui-dialog'));
        $this->assertTrue($dialog->hasAttribute('data-ui-lightbox'));
        $this->assertSame('fotos-os-titulo', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame('true', $dialog->getAttribute('data-close-on-backdrop'));
        $this->assertSame('Fotos do serviço', $this->element($document, '#fotos-os-titulo')->textContent);

        $classes = $this->classesOf($dialog);
        foreach (['theme-inverse', 'open:flex', 'backdrop:bg-overlay', 'motion-safe:scale-[.96]', 'motion-safe:open:scale-100', 'ease-smooth-out', 'duration-fast', 'open:duration-base', 'motion-reduce:transition-none'] as $class) {
            $this->assertContains($class, $classes, "Faltou {$class} no diálogo.");
        }

        $region = $this->element($document, '[aria-roledescription="carrossel"]');
        $this->assertSame('region', $region->getAttribute('role'));
        $this->assertSame('Fotos do serviço', $region->getAttribute('aria-label'));

        $track = $this->element($document, '[data-lightbox-track]');
        $this->assertSame('fotos-os-trilho', $track->getAttribute('id'));
        foreach (['snap-x', 'snap-mandatory', 'overflow-x-auto', 'overscroll-x-contain'] as $class) {
            $this->assertContains($class, $this->classesOf($track), "Faltou {$class}: o deslizar no celular é a rolagem nativa.");
        }

        $slides = $document->querySelectorAll('[data-lightbox-slide]');
        $this->assertCount(3, $slides);
        $this->assertSame('group', $slides->item(0)->getAttribute('role'));
        $this->assertSame('slide', $slides->item(0)->getAttribute('aria-roledescription'));
        $this->assertSame('Foto 2 de 3', $slides->item(1)->getAttribute('aria-label'));
        $this->assertFalse($slides->item(0)->hasAttribute('aria-hidden'));
        $this->assertSame('true', $slides->item(1)->getAttribute('aria-hidden'), 'Fora da tela, o slide sai da árvore de acessibilidade.');
        $this->assertSame(self::ITEMS[1]['alt'], $slides->item(1)->getAttribute('data-lightbox-alt'));
        $this->assertSame(self::ITEMS[1]['src'], $slides->item(1)->getAttribute('data-lightbox-src'));

        $image = $slides->item(1)->querySelector('img');
        $this->assertSame(self::ITEMS[1]['alt'], $image->getAttribute('alt'));
        $this->assertSame('lazy', $image->getAttribute('loading'));
        $this->assertContains('object-contain', $this->classesOf($image));
        $this->assertStringContainsString('Carro depois do serviço', $slides->item(1)->textContent);
        $this->assertNull($slides->item(2)->querySelector('p'), 'Sem legenda, sem parágrafo vazio.');

        $this->assertSame('Foto 1 de 3', $this->element($document, '[data-lightbox-counter]')->textContent);
        $this->assertSame('true', $this->element($document, '[data-lightbox-counter]')->getAttribute('aria-hidden'));
        $status = $this->element($document, '[data-lightbox-status]');
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertContains('sr-only', $this->classesOf($status));

        $original = $this->element($document, 'a[data-lightbox-original]');
        $this->assertSame(self::ITEMS[0]['src'], $original->getAttribute('href'));
        $this->assertSame('_blank', $original->getAttribute('target'));
        $this->assertStringContainsString('(abre em nova aba)', $original->textContent);
    }

    public function test_arrows_control_the_track_and_start_disabled_at_the_first_photo(): void
    {
        $document = $this->renderComponent('<x-ui.lightbox id="fotos-os" title="Fotos do serviço" :items="$items" />', ['items' => self::ITEMS]);

        $previous = $this->element($document, 'button[data-lightbox-prev]');
        $next = $this->element($document, 'button[data-lightbox-next]');

        $this->assertSame('Foto anterior', $previous->getAttribute('aria-label'));
        $this->assertSame('Próxima foto', $next->getAttribute('aria-label'));
        $this->assertSame('fotos-os-trilho', $previous->getAttribute('aria-controls'));
        $this->assertSame('fotos-os-trilho', $next->getAttribute('aria-controls'));
        $this->assertSame('true', $previous->getAttribute('aria-disabled'), 'aria-disabled, não disabled: o foco não se perde na ponta.');
        $this->assertFalse($previous->hasAttribute('disabled'));
        $this->assertTrue($next->hasAttribute('data-dialog-initial-focus'));
        $this->assertContains('size-11', $this->classesOf($next), 'Alvo de 44px.');
        $this->assertStringContainsString('Use as setas do teclado ou deslize', $document->body->textContent);

        $close = $this->element($document, 'button[data-dialog-close-button]');
        $this->assertSame('Fechar', $close->getAttribute('aria-label'));
        $this->assertFalse($close->hasAttribute('data-dialog-initial-focus'));
    }

    public function test_single_photo_has_no_arrows_and_focuses_close(): void
    {
        $document = $this->renderComponent('<x-ui.lightbox id="foto" title="Foto do serviço" label="Foto da peça" :items="[$item]" />', ['item' => self::ITEMS[0]]);

        $this->assertNull($document->querySelector('[data-lightbox-prev]'));
        $this->assertNull($document->querySelector('[data-lightbox-next]'));
        $this->assertTrue($this->element($document, 'button[data-dialog-close-button]')->hasAttribute('data-dialog-initial-focus'));
        $this->assertSame('Foto da peça', $this->element($document, '[aria-roledescription="carrossel"]')->getAttribute('aria-label'));
        $this->assertStringNotContainsString('Use as setas do teclado', $document->body->textContent);
    }

    public function test_every_photo_needs_an_alt(): void
    {
        foreach ([
            '<x-ui.lightbox id="f" title="Fotos" :items="[[\'src\' => \'a.jpg\']]" />' => 'x-ui.lightbox precisa de alt',
            '<x-ui.lightbox id="f" title="Fotos" :items="[]" />' => 'x-ui.lightbox precisa de items',
            '<x-ui.lightbox id="f" :items="[[\'src\' => \'a.jpg\', \'alt\' => \'Foto\']]" />' => 'x-ui.lightbox precisa de title',
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
