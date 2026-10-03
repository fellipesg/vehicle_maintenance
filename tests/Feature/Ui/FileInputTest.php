<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.file-input>: input nativo que funciona sem JS e os moldes (<template>) que
 * resources/js/ui/file-input.js usa para a área de soltar e a lista de arquivos.
 */
class FileInputTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_native_input_works_without_js_and_carries_the_rules(): void
    {
        $document = $this->renderComponent('<x-ui.file-input name="invoice" accept="application/pdf,image/jpeg,image/png" :max-mb="10" required class="mt-4" />');

        $root = $this->element($document, '[data-file-input]');
        $input = $this->element($document, 'input[type="file"]');

        $this->assertSame('invoice', $input->getAttribute('name'));
        $this->assertSame('invoice', $input->getAttribute('id'));
        $this->assertSame('application/pdf,image/jpeg,image/png', $input->getAttribute('accept'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertFalse($input->hasAttribute('multiple'));
        $this->assertSame(['invoice-rules'], $this->describedByOf($input));
        $this->assertSame('PDF, JPG ou PNG · até 10 MB', trim($this->element($document, '#invoice-rules')->textContent));
        $this->assertSame('10485760', $root->getAttribute('data-max-bytes'));
        $this->assertSame('10 MB', $root->getAttribute('data-max-label'));
        $this->assertSame('PDF, JPG ou PNG', $root->getAttribute('data-types-label'));
        $this->assertContains('mt-4', $this->classesOf($root), 'class vai para a moldura.');
        $this->assertNotContains('mt-4', $this->classesOf($input));
        $this->assertContains('group-data-enhanced/file-input:sr-only', $this->classesOf($input), 'Com JS o input fica oculto, mas focável.');
        $this->assertContains('file:bg-surface-muted', $this->classesOf($input), 'Sem JS o botão nativo segue os tokens.');
        $this->assertTrue($this->element($document, '[data-file-input-list]')->hasAttribute('hidden'));
    }

    public function test_dropzone_template_is_a_label_for_the_input_with_visible_focus(): void
    {
        $document = $this->renderComponent('<x-ui.file-input name="photos[]" accept="image/*" multiple :max-files="4" :max-mb="5" />');

        $input = $this->element($document, 'input[type="file"]');
        $dropzone = $this->element($this->templateContent($document, 'template[data-file-input-dropzone-template]'), 'label[data-file-input-dropzone]');
        $classes = $this->classesOf($dropzone);

        $this->assertSame('photos', $input->getAttribute('id'));
        $this->assertSame('photos[]', $input->getAttribute('name'));
        $this->assertTrue($input->hasAttribute('multiple'));
        $this->assertSame('4', $this->element($document, '[data-file-input]')->getAttribute('data-max-files'));
        $this->assertSame('Imagens · até 5 MB · até 4 arquivos', trim($this->element($document, '#photos-rules')->textContent));
        $this->assertSame('photos', $dropzone->getAttribute('for'));
        $this->assertStringContainsString('Escolher arquivos', $dropzone->textContent);
        $this->assertStringContainsString('ou arraste até aqui', $dropzone->textContent);

        foreach ($this->templateContent($document, 'template[data-file-input-dropzone-template]')->querySelectorAll('label[data-file-input-dropzone] > span') as $span) {
            $this->assertSame('true', $span->getAttribute('aria-hidden'), 'O texto da área não entra no nome do campo.');
        }

        foreach (['peer-focus-visible:outline-2', 'peer-focus-visible:outline-ring', 'peer-aria-invalid:border-danger', 'data-dragging:border-ring', 'data-dragging:bg-accent', 'border-dashed', 'motion-reduce:transition-none', 'duration-fast'] as $class) {
            $this->assertContains($class, $classes);
        }
    }

    public function test_item_template_has_name_size_preview_and_a_forty_pixel_remove_button(): void
    {
        $item = $this->templateContent($this->renderComponent('<x-ui.file-input name="invoice" />'), 'template[data-file-input-item-template]');

        $remove = $this->element($item, 'button[data-file-input-remove]');
        $image = $this->element($item, 'img[data-file-input-item-image]');

        $this->assertSame('button', $remove->getAttribute('type'));
        $this->assertContains('size-10', $this->classesOf($remove));
        $this->assertNotNull($item->querySelector('[data-file-input-item-name]'));
        $this->assertNotNull($item->querySelector('[data-file-input-item-size]'));
        $this->assertSame('', $image->getAttribute('alt'));
        $this->assertTrue($image->hasAttribute('hidden'));
        $this->assertContains('object-contain', $this->classesOf($image), 'Prévia sem corte.');
    }

    public function test_feedback_and_status_regions_for_client_side_messages(): void
    {
        $document = $this->renderComponent('<x-ui.file-input name="crlv" accept=".pdf" />');

        $feedback = $this->element($document, '#crlv-feedback');
        $status = $this->element($document, '[data-file-input-status]');

        $this->assertSame('alert', $feedback->getAttribute('role'));
        $this->assertSame('', $feedback->innerHTML, 'Vazio até o JS escrever: role="alert" anuncia a mudança.');
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertContains('sr-only', $this->classesOf($status));
        $this->assertSame('Arquivos escolhidos', $this->element($document, '[data-file-input-list]')->getAttribute('aria-label'));
    }

    public function test_server_error_marks_the_input_and_rules_can_be_replaced_or_removed(): void
    {
        $this->withViewErrors(['photos.1' => ['A foto 2 passa de 5 MB.']]);

        $invalid = $this->element($this->renderComponent('<x-ui.file-input name="photos[]" multiple />'), 'input[type="file"]');
        $custom = $this->renderComponent('<x-ui.file-input name="logo" accept="image/png" rules="PNG quadrado, de preferência 512 × 512" />');
        $none = $this->renderComponent('<x-ui.file-input name="logo" accept="image/png" :rules="false" />');

        $this->assertSame('true', $invalid->getAttribute('aria-invalid'));
        $this->assertSame('PNG quadrado, de preferência 512 × 512', trim($this->element($custom, '#logo-rules')->textContent));
        $this->assertNull($none->querySelector('#logo-rules'));
        $this->assertFalse($this->element($none, 'input')->hasAttribute('aria-describedby'));
    }
}
