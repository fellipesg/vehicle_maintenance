<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;
use Throwable;

/**
 * <x-ui.image-cropper>: sem JS é um input de arquivo comum (com a imagem atual e a regra); com
 * resources/js/ui/image-cropper.js vira o recorte em proporção fixa (capas 16:9 e 9:16 do veículo,
 * .ai/rules/user-vehicles.md). Aqui ficam a marcação que o script usa, a acessibilidade do palco e
 * as contas do recorte (image-cropper-geometry.js rodando no Node).
 */
class ImageCropperTest extends TestCase
{
    use InspectsRenderedComponents;

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_without_js_it_is_a_plain_file_input_with_label_and_rules(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" aspect="16:9" label="Capa paisagem (celular deitado)" :max-mb="5" required />');

        $root = $this->element($document, '[data-image-cropper]');
        $input = $this->element($document, 'input[type="file"]');
        $label = $this->element($document, 'label#cover-label');

        $this->assertSame('cover', $input->getAttribute('name'));
        $this->assertSame('cover', $input->getAttribute('id'));
        $this->assertSame('image/jpeg,image/png,image/webp', $input->getAttribute('accept'));
        $this->assertTrue($input->hasAttribute('required'), 'Sem imagem atual, required vai para o input.');
        $this->assertFalse($input->hasAttribute('hidden'), 'Sem JS o input nativo é o controle.');
        $this->assertTrue($input->hasAttribute('data-image-cropper-input'));
        $this->assertContains('peer', $this->classesOf($input), 'A área de soltar mostra o foco do input por peer-focus-visible.');
        $this->assertContains('group-data-enhanced/image-cropper:sr-only', $this->classesOf($input), 'Com JS o input fica oculto, mas focável.');
        $this->assertContains('file:bg-surface-muted', $this->classesOf($input), 'Sem JS o botão nativo segue os tokens, como no x-ui.file-input.');

        $this->assertSame('cover', $label->getAttribute('for'));
        $this->assertStringContainsString('Capa paisagem (celular deitado)', $label->textContent);
        $this->assertStringContainsString('(obrigatório)', $label->textContent);
        $this->assertSame('group', $root->getAttribute('role'));
        $this->assertSame('cover-label', $root->getAttribute('aria-labelledby'));

        $this->assertSame('empty', $root->getAttribute('data-state'));
        $this->assertSame('16:9', $root->getAttribute('data-aspect'));
        $this->assertSame('1920', $root->getAttribute('data-max-side'));
        $this->assertSame('0.85', $root->getAttribute('data-quality'));
        $this->assertSame('5242880', $root->getAttribute('data-max-bytes'));
        $this->assertSame('5 MB', $root->getAttribute('data-max-label'));
        $this->assertSame('JPG, PNG ou WEBP', $root->getAttribute('data-types-label'));
        $this->assertSame('Capa paisagem (celular deitado)', $root->getAttribute('data-label'));

        $this->assertSame(['cover-rules'], $this->describedByOf($input));
        $rules = $this->element($document, '#cover-rules');
        $fallback = $this->element($document, '#cover-rules > span:first-child');
        $enhanced = $this->element($document, '#cover-rules > span:last-child');
        $this->assertSame('JPG, PNG ou WEBP · até 5 MB · proporção 16:9', trim($fallback->textContent));
        $this->assertContains('group-data-enhanced/image-cropper:hidden', $this->classesOf($fallback));
        $this->assertSame('JPG, PNG ou WEBP · você enquadra em 16:9 antes do envio', trim($enhanced->textContent));
        $this->assertSame(['hidden', 'group-data-enhanced/image-cropper:inline'], $this->classesOf($enhanced));
        $this->assertStringContainsString('text-muted-foreground', (string) $rules->getAttribute('class'));

        foreach (['[data-image-cropper-dropzone]', '[data-image-cropper-actions]', '[data-image-cropper-editor]', '[data-image-cropper-preview]'] as $selector) {
            $this->assertTrue($this->element($document, $selector)->hasAttribute('hidden'), "{$selector} só aparece com JS (ou com imagem atual).");
        }
    }

    public function test_portrait_aspect_draws_a_nine_by_sixteen_preview_and_stage(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover_portrait" aspect="9:16" label="Capa retrato (celular em pé)" />');

        $root = $this->element($document, '[data-image-cropper]');
        $frame = $this->element($document, '[data-image-cropper-preview] > div');
        $stage = $this->element($document, '[data-image-cropper-stage]');

        $this->assertSame('9:16', $root->getAttribute('data-aspect'));
        $this->assertSame('cover_portrait', $this->element($document, 'input[type="file"]')->getAttribute('id'));
        $this->assertContains('aspect-[9/16]', $this->classesOf($frame));
        $this->assertNotContains('aspect-video', $this->classesOf($frame));
        $this->assertContains('aspect-[4/5]', $this->classesOf($stage));
        $this->assertSame('JPG, PNG ou WEBP · proporção 9:16', trim($this->element($document, '#cover_portrait-rules > span:first-child')->textContent));

        $landscape = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa paisagem" />');

        $this->assertSame('16:9', $this->element($landscape, '[data-image-cropper]')->getAttribute('data-aspect'), '16:9 é o padrão.');
        $this->assertContains('aspect-video', $this->classesOf($this->element($landscape, '[data-image-cropper-preview] > div')));
        $this->assertContains('aspect-[3/2]', $this->classesOf($this->element($landscape, '[data-image-cropper-stage]')));
    }

    public function test_current_image_is_shown_whole_and_the_input_stops_being_required(): void
    {
        $document = $this->renderComponent(
            '<x-ui.image-cropper name="cover" label="Capa paisagem (celular deitado)" :current="$url" current-label="Capa atual" required />',
            ['url' => 'https://cdn.example.test/covers/abc.jpg'],
        );

        $root = $this->element($document, '[data-image-cropper]');
        $preview = $this->element($document, '[data-image-cropper-preview]');
        $image = $this->element($document, 'img[data-image-cropper-preview-image]');

        $this->assertSame('current', $root->getAttribute('data-state'));
        $this->assertSame('FIGURE', $preview->tagName);
        $this->assertFalse($preview->hasAttribute('hidden'), 'A imagem atual aparece também sem JS.');
        $this->assertSame('https://cdn.example.test/covers/abc.jpg', $image->getAttribute('src'));
        $this->assertSame('Capa atual: Capa paisagem (celular deitado)', $image->getAttribute('alt'));
        $this->assertContains('object-contain', $this->classesOf($image), 'Capa nunca é cortada na prévia (.ai/rules/components.md).');
        $this->assertNotContains('object-cover', $this->classesOf($image));
        $this->assertSame('Capa atual', trim($this->element($document, '[data-image-cropper-preview-caption]')->textContent));
        $this->assertFalse($this->element($document, 'input[type="file"]')->hasAttribute('required'), 'Com imagem atual, não trocar é válido.');
        $this->assertStringContainsString('(obrigatório)', $this->element($document, 'label#cover-label')->textContent);

        $custom = $this->renderComponent(
            '<x-ui.image-cropper name="logo" label="Logo" :current="$url" current-alt="Logo da oficina" />',
            ['url' => '/storage/logo.png'],
        );

        $this->assertSame('Logo da oficina', $this->element($custom, 'img[data-image-cropper-preview-image]')->getAttribute('alt'));
        $this->assertSame('Imagem atual', trim($this->element($custom, '[data-image-cropper-preview-caption]')->textContent));
    }

    public function test_without_current_image_the_preview_waits_hidden_and_empty(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa" />');

        $image = $this->element($document, 'img[data-image-cropper-preview-image]');

        $this->assertTrue($this->element($document, '[data-image-cropper-preview]')->hasAttribute('hidden'));
        $this->assertFalse($image->hasAttribute('src'));
        $this->assertContains('object-contain', $this->classesOf($image));
    }

    public function test_dropzone_is_a_label_for_the_input_with_visible_focus_and_no_extra_name(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa paisagem" />');

        $dropzone = $this->element($document, 'label[data-image-cropper-dropzone]');
        $classes = $this->classesOf($dropzone);

        $this->assertSame('cover', $dropzone->getAttribute('for'));
        $this->assertStringContainsString('Escolher imagem', $dropzone->textContent);
        $this->assertStringContainsString('ou arraste até aqui', $dropzone->textContent);

        $spans = $document->querySelectorAll('label[data-image-cropper-dropzone] > span');

        $this->assertCount(2, $spans);

        foreach ($spans as $span) {
            $this->assertSame('true', $span->getAttribute('aria-hidden'), 'O texto da área não entra no nome do campo.');
        }

        foreach (['peer-focus-visible:outline-2', 'peer-focus-visible:outline-ring', 'peer-aria-invalid:border-danger', 'group-data-dragging/image-cropper:border-ring', 'group-data-dragging/image-cropper:bg-accent', 'border-dashed', 'border-input', 'motion-reduce:transition-none', 'duration-fast'] as $class) {
            $this->assertContains($class, $classes);
        }

        $input = $this->element($document, 'input[type="file"]');
        $this->assertTrue($input->parentNode->isSameNode($dropzone->parentNode), 'peer-* só funciona entre irmãos.');
    }

    public function test_stage_and_zoom_are_keyboard_operable_and_described(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa paisagem (celular deitado)" />');

        $stage = $this->element($document, '[data-image-cropper-stage]');
        $zoom = $this->element($document, 'input[type="range"][data-image-cropper-zoom]');

        $this->assertSame('0', $stage->getAttribute('tabindex'));
        $this->assertSame('application', $stage->getAttribute('role'), 'As setas movem a imagem: o leitor de tela precisa deixá-las passar.');
        $this->assertSame('área de recorte', $stage->getAttribute('aria-roledescription'));
        $this->assertSame('Enquadramento: Capa paisagem (celular deitado)', $stage->getAttribute('aria-label'));
        $this->assertSame(['cover-instructions', 'cover-keys'], $this->describedByOf($stage));
        $this->assertStringContainsString('Arraste a imagem', $this->element($document, '#cover-instructions')->textContent);
        $this->assertStringContainsString('setas movem', $this->element($document, '#cover-keys')->textContent);

        foreach (['focus-visible:outline-2', 'focus-visible:outline-ring', 'touch-none', 'select-none', 'overflow-hidden'] as $class) {
            $this->assertContains($class, $this->classesOf($stage));
        }

        $this->assertContains('theme-inverse', $this->classesOf($this->element($document, '[data-image-cropper-stage] > div')), 'Fundo escuro pelo escopo, não pela paleta crua; o contorno de foco do palco fica no escopo claro.');
        $this->assertSame('', $this->element($document, 'img[data-image-cropper-image]')->getAttribute('alt'));
        $this->assertSame('false', $this->element($document, 'img[data-image-cropper-image]')->getAttribute('draggable'));
        $this->assertContains('shadow-[0_0_0_9999px_var(--color-overlay)]', $this->classesOf($this->element($document, '[data-image-cropper-frame]')));

        $this->assertSame('cover-zoom', $zoom->getAttribute('id'));
        $this->assertSame('Zoom', trim($this->element($document, 'label[for="cover-zoom"]')->textContent));
        $this->assertSame('100', $zoom->getAttribute('min'));
        $this->assertSame('400', $zoom->getAttribute('max'), 'MAX_ZOOM = 4 em image-cropper-geometry.js.');
        $this->assertSame('100', $zoom->getAttribute('value'));
        $this->assertSame('100%', $zoom->getAttribute('aria-valuetext'));
        $this->assertContains('accent-ring', $this->classesOf($zoom), 'O polegar do controle precisa de 3:1 (ring, não primary).');
        $this->assertSame('Diminuir zoom', $this->element($document, '[data-image-cropper-zoom-out]')->getAttribute('aria-label'));
        $this->assertSame('Aumentar zoom', $this->element($document, '[data-image-cropper-zoom-in]')->getAttribute('aria-label'));
    }

    public function test_every_control_is_a_plain_button_that_never_submits_the_form(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa paisagem (celular deitado)" />');

        $expected = [
            '[data-image-cropper-apply]' => 'Usar este recorte',
            '[data-image-cropper-cancel]' => 'Cancelar',
            '[data-image-cropper-change]' => 'Trocar imagem, Capa paisagem (celular deitado)',
            '[data-image-cropper-adjust]' => 'Ajustar recorte, Capa paisagem (celular deitado)',
            '[data-image-cropper-discard]' => 'Descartar o novo recorte, Capa paisagem (celular deitado)',
            '[data-image-cropper-zoom-in]' => null,
            '[data-image-cropper-zoom-out]' => null,
        ];

        foreach ($expected as $selector => $name) {
            $button = $this->element($document, $selector);

            $this->assertSame('BUTTON', $button->tagName, $selector);
            $this->assertSame('button', $button->getAttribute('type'), "{$selector} dentro de um formulário não pode enviá-lo.");

            if ($name !== null) {
                $this->assertSame($name, trim((string) preg_replace('/\s+/u', ' ', $button->textContent)), $selector);
            }
        }

        $this->assertSame('Recortando…', $this->element($document, '[data-image-cropper-apply]')->getAttribute('data-loading-label'));
        $this->assertTrue($this->element($document, '[data-image-cropper-adjust]')->hasAttribute('hidden'), 'Ajustar só depois de recortar.');
        $this->assertTrue($this->element($document, '[data-image-cropper-discard]')->hasAttribute('hidden'));
    }

    public function test_server_error_hint_and_client_side_regions(): void
    {
        $this->withViewErrors(['cover_portrait' => ['A capa retrato precisa ser uma imagem JPG, PNG ou WEBP.']]);

        $document = $this->renderComponent('<x-ui.image-cropper name="cover_portrait" aspect="9:16" label="Capa retrato" hint="Usada em telas estreitas, avatares e no PDF." optional />');

        $input = $this->element($document, 'input[type="file"]');
        $error = $this->element($document, '#cover_portrait-error');
        $feedback = $this->element($document, '#cover_portrait-feedback');
        $status = $this->element($document, '[data-image-cropper-status]');

        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(['cover_portrait-hint', 'cover_portrait-rules', 'cover_portrait-error'], $this->describedByOf($input));
        $this->assertSame('Usada em telas estreitas, avatares e no PDF.', trim($this->element($document, '#cover_portrait-hint')->textContent));
        $this->assertStringContainsString('A capa retrato precisa ser uma imagem JPG, PNG ou WEBP.', $error->textContent);
        $this->assertStringContainsString('(opcional)', $this->element($document, 'label#cover_portrait-label')->textContent);

        $this->assertSame('alert', $feedback->getAttribute('role'));
        $this->assertSame('', $feedback->innerHTML, 'Vazio até o JS escrever: role="alert" anuncia a mudança.');
        $this->assertContains('empty:hidden', $this->classesOf($feedback));
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertContains('sr-only', $this->classesOf($status));

        $custom = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa" error="Envie a capa de novo." />');
        $this->assertStringContainsString('Envie a capa de novo.', $this->element($custom, '#cover-error')->textContent);
    }

    public function test_other_attributes_go_to_the_input_and_class_to_the_frame(): void
    {
        $document = $this->renderComponent('<x-ui.image-cropper name="cover" label="Capa" class="mt-4" disabled data-test="capa" id="capa-veiculo" :max-side="1280" :quality="0.9" />');

        $root = $this->element($document, '[data-image-cropper]');
        $input = $this->element($document, 'input[type="file"]');

        $this->assertContains('mt-4', $this->classesOf($root));
        $this->assertNotContains('mt-4', $this->classesOf($input));
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertSame('capa', $input->getAttribute('data-test'));
        $this->assertSame('capa-veiculo', $input->getAttribute('id'));
        $this->assertSame('capa-veiculo', $this->element($document, 'label[data-image-cropper-dropzone]')->getAttribute('for'));
        $this->assertSame('capa-veiculo-label', $root->getAttribute('aria-labelledby'));
        $this->assertSame('1280', $root->getAttribute('data-max-side'));
        $this->assertSame('0.9', $root->getAttribute('data-quality'));
        $this->assertFalse($root->hasAttribute('data-max-bytes'), 'Sem maxMb, o JPEG não é conferido por tamanho.');
        $this->assertSame('JPG, PNG ou WEBP · proporção 16:9', trim($this->element($document, '#capa-veiculo-rules > span:first-child')->textContent));
    }

    public function test_invalid_props_are_rejected(): void
    {
        $this->assertRejected('<x-ui.image-cropper name="cover" label="Capa" aspect="4:3" />', 'aspect "4:3" não existe');
        $this->assertRejected('<x-ui.image-cropper label="Capa" />', 'x-ui.image-cropper precisa de name');
        $this->assertRejected('<x-ui.image-cropper name="cover" />', 'x-ui.image-cropper precisa de label');
    }

    public function test_script_is_idempotent_and_started_by_the_bundle(): void
    {
        $source = file_get_contents(resource_path('js/ui/image-cropper.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('export function initImageCroppers(root = document)', $source);
        $this->assertStringContainsString('import { elementsWithin, prefersReducedMotion }', $source);
        $this->assertStringContainsString("dataset.imageCropperReady === 'true'", $source, 'Ligar duas vezes o mesmo elemento duplicaria os ouvintes.');
        $this->assertStringContainsString("dataset.imageCropperReady = 'true'", $source);
        $this->assertStringContainsString("import { initImageCroppers } from './ui/image-cropper';", $app);
        $this->assertMatchesRegularExpression('/^\s+initImageCroppers\(\);$/m', $app);
    }

    public function test_script_crops_to_jpeg_and_replaces_the_file_before_the_upload(): void
    {
        $source = file_get_contents(resource_path('js/ui/image-cropper.js'));

        $this->assertStringContainsString("canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('toBlob falhou.'))), 'image/jpeg', quality)", $source);
        $this->assertStringContainsString('Number(container.dataset.quality) || 0.85', $source);
        $this->assertStringContainsString('Number(container.dataset.maxSide) || 1920', $source);
        $this->assertStringContainsString('new DataTransfer()', $source, 'Sem DataTransfer, fica o input nativo.');
        $this->assertStringContainsString('input.files = transfer.files', $source);
        $this->assertStringContainsString('form.requestSubmit(submitter)', $source, 'Enviar com o palco aberto recorta antes e reenvia com o mesmo botão.');
        $this->assertStringContainsString('event.preventDefault();', $source);
        $this->assertStringContainsString("context.fillStyle = '#ffffff'", $source, 'PNG transparente não vira fundo preto no JPEG.');
        $this->assertStringContainsString('setPointerCapture', $source, 'Mouse e toque pelo mesmo caminho (pointer events).');
        $this->assertStringContainsString('pinch = pinchBetween(Array.from(pointers.values()));', $source, 'A pinça mede a partir do segundo dedo, não do primeiro movimento.');
        $this->assertStringContainsString('Promise.race([decoded, loaded])', $source, 'Com a aba em segundo plano o decode() pode não resolver: o load também libera o palco.');
        $this->assertStringContainsString('{ passive: false }', $source, 'A roda aproxima sem rolar a página.');
        $this->assertStringContainsString('ArrowLeft: [-step, 0]', $source, 'Setas movem a imagem, como o arraste.');
        $this->assertStringContainsString("setAttribute('aria-valuetext'", $source);
        $this->assertStringContainsString('prefersReducedMotion()', $source);
        $this->assertStringContainsString('URL.revokeObjectURL', $source);
        $this->assertStringContainsString('escapeHtml(message)', $source, 'Nome de arquivo vai escapado para o innerHTML.');
        $this->assertStringNotContainsString('confirm(', $source);
        $this->assertStringNotContainsString('cropperjs', $source);
    }

    public function test_geometry_crops_the_exact_aspect_without_empty_borders(): void
    {
        $result = $this->runGeometry();

        $this->assertEquals(['width' => 16, 'height' => 9, 'ratio' => 16 / 9], $result['aspectLandscape']);
        $this->assertEqualsWithDelta(0.5625, $result['aspectPortrait']['ratio'], 1e-9);
        $this->assertNull($result['aspectInvalid']);
        $this->assertEqualsWithDelta(['left' => 24, 'top' => 44.75, 'width' => 552, 'height' => 310.5], $result['frame'], 1e-9);

        $this->assertEqualsWithDelta(['x' => 0, 'y' => 375, 'width' => 4000, 'height' => 2250], $result['landscapeCrop'], 1e-6, 'Foto 4:3 em 16:9: largura inteira, centralizada na altura.');
        $this->assertSame(['width' => 1920, 'height' => 1080], $result['landscapeOutput'], 'Lado maior limitado a 1920 px.');
        $this->assertEqualsWithDelta(['x' => 1156.25, 'y' => 0, 'width' => 1687.5, 'height' => 3000], $result['portraitCrop'], 1e-6);
        $this->assertSame(['width' => 1080, 'height' => 1920], $result['portraitOutput']);
        $this->assertSame(['width' => 800, 'height' => 450], $result['smallOutput'], 'Recorte menor que 1920 px não é ampliado.');
    }

    public function test_geometry_keeps_the_crop_inside_the_image_and_the_zoom_in_range(): void
    {
        $result = $this->runGeometry();

        $this->assertEqualsWithDelta(0, $result['pannedRight']['x'], 1e-6, 'Arrastar para a direita encosta o recorte na borda esquerda da foto.');
        $this->assertEqualsWithDelta(4000 - $result['pannedLeft']['width'], $result['pannedLeft']['x'], 1e-6);
        $this->assertSame(4, $result['zoomTooFar']);
        $this->assertSame(1, $result['zoomTooClose']);
        $this->assertEqualsWithDelta($result['anchorBefore'], $result['anchorAfter'], 1e-6, 'O ponto sob o cursor não sai do lugar ao aproximar.');
        $this->assertEqualsWithDelta($result['cropSmallStage'], $result['cropLargeStage'], 1e-6, 'Redimensionar o palco não muda o recorte.');
        $this->assertEqualsWithDelta(2000, $result['zoomedCrop']['width'], 1e-6, 'Zoom 2 recorta metade da largura.');
        $this->assertSame(['zoom' => 100, 'horizontal' => null, 'vertical' => 50], $result['describedStart']);
        $this->assertSame(['zoom' => 200, 'horizontal' => 0, 'vertical' => 50], $result['describedLeft']);
    }

    /**
     * @return array<string, mixed>
     */
    private function runGeometry(): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run image-cropper-geometry.js.');
        }

        $this->sandbox = storage_path('framework/testing/image-cropper-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::copy(resource_path('js/ui/image-cropper-geometry.js'), "{$this->sandbox}/image-cropper-geometry.js");
        File::put("{$this->sandbox}/runner.js", <<<'JS'
            const geometry = await import('./image-cropper-geometry.js');
            const { parseAspect, fitFrame, initialView, clampView, panView, zoomView, imageTransform, cropRect, outputSize, describeView } = geometry;

            const photo = { width: 4000, height: 3000 };
            const landscape = parseAspect('16:9');
            const portrait = parseAspect('9:16');
            const landscapeFrame = fitFrame(360, 220, landscape.ratio, 20);
            const portraitFrame = fitFrame(300, 375, portrait.ratio, 18);
            const start = clampView(initialView(photo), photo, landscapeFrame);

            const landscapeCrop = cropRect(start, photo, landscapeFrame);
            const portraitCrop = cropRect(clampView(initialView(photo), photo, portraitFrame), photo, portraitFrame);
            const small = { width: 800, height: 600 };
            const smallCrop = cropRect(clampView(initialView(small), small, landscapeFrame), small, landscapeFrame);

            const zoomed = zoomView(start, 2, photo, landscapeFrame);
            const anchor = { x: landscapeFrame.left + 10, y: landscapeFrame.top + 10 };
            const imagePointUnder = (view) => {
                const { x, y, scale } = imageTransform(view, photo, landscapeFrame);
                return { x: (anchor.x - x) / scale, y: (anchor.y - y) / scale };
            };
            const anchoredZoom = zoomView(start, 2.5, photo, landscapeFrame, anchor);
            const largeFrame = fitFrame(1080, 720, landscape.ratio, 40);
            const moved = panView(zoomed, 37, -12, photo, landscapeFrame);

            console.log(JSON.stringify({
                aspectLandscape: landscape,
                aspectPortrait: portrait,
                aspectInvalid: parseAspect('quadrado'),
                frame: fitFrame(600, 400, 16 / 9, 24),
                landscapeCrop,
                landscapeOutput: outputSize(landscapeCrop, landscape, 1920),
                portraitCrop,
                portraitOutput: outputSize(portraitCrop, portrait, 1920),
                smallOutput: outputSize(smallCrop, landscape, 1920),
                pannedRight: cropRect(panView(zoomed, 100000, 0, photo, landscapeFrame), photo, landscapeFrame),
                pannedLeft: cropRect(panView(zoomed, -100000, 0, photo, landscapeFrame), photo, landscapeFrame),
                zoomTooFar: zoomView(start, 12, photo, landscapeFrame).zoom,
                zoomTooClose: zoomView(start, 0.2, photo, landscapeFrame).zoom,
                anchorBefore: imagePointUnder(start),
                anchorAfter: imagePointUnder(anchoredZoom),
                cropSmallStage: cropRect(moved, photo, landscapeFrame),
                cropLargeStage: cropRect(clampView(moved, photo, largeFrame), photo, largeFrame),
                zoomedCrop: cropRect(zoomed, photo, landscapeFrame),
                describedStart: describeView(start, photo, landscapeFrame),
                describedLeft: describeView(panView(zoomed, 100000, 0, photo, landscapeFrame), photo, landscapeFrame),
            }));
            JS);

        $process = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($process->successful(), $process->errorOutput());

        return json_decode(trim($process->output()), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertRejected(string $template, string $messageFragment): void
    {
        try {
            $this->blade($template);
        } catch (Throwable $exception) {
            $cause = $exception;

            while (! $cause instanceof InvalidArgumentException && $cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(InvalidArgumentException::class, $cause, 'Esperava InvalidArgumentException, veio '.$exception::class.': '.$exception->getMessage());
            $this->assertStringContainsString($messageFragment, $cause->getMessage());

            return;
        }

        $this->fail("O trecho deveria ter sido recusado: {$template}");
    }
}
