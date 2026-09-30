<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.field>: rótulo, controle, dica e erro ligados por id. O controle recebe o name do campo
 * (via @aware) ou vem pronto, escrito à mão, no slot.
 */
class FieldTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_label_points_to_the_control_that_inherits_the_name(): void
    {
        $document = $this->renderComponent('<x-ui.field name="items[0][price]" label="Preço"><x-ui.input inputmode="decimal" /></x-ui.field>');

        $field = $this->element($document, '[data-slot="field"]');
        $label = $this->element($document, 'label');
        $input = $this->element($document, 'input');

        $this->assertSame('items_0_price', $label->getAttribute('for'));
        $this->assertSame('items_0_price', $input->getAttribute('id'));
        $this->assertSame('items[0][price]', $input->getAttribute('name'));
        $this->assertSame('Preço', trim($label->textContent));
        $this->assertFalse($input->hasAttribute('aria-describedby'));
        $this->assertFalse($input->hasAttribute('aria-invalid'));
        $this->assertFalse($field->hasAttribute('data-invalid'));
    }

    public function test_required_shows_an_asterisk_and_tells_screen_readers(): void
    {
        $document = $this->renderComponent('<x-ui.field name="plate" label="Placa" required><x-ui.input /></x-ui.field>');

        $label = $this->element($document, 'label');
        $asterisk = $this->element($document, 'label span[aria-hidden="true"]');
        $spoken = $this->element($document, 'label span.sr-only');

        $this->assertSame('*', $asterisk->textContent);
        $this->assertSame(' (obrigatório)', $spoken->textContent);
        $this->assertSame('Placa* (obrigatório)', $label->textContent);
        $this->assertFalse($this->element($document, 'input')->hasAttribute('required'), 'O campo não liga a validação do navegador sozinho.');
    }

    public function test_optional_marks_the_label(): void
    {
        $document = $this->renderComponent('<x-ui.field name="nickname" label="Apelido" optional><x-ui.input /></x-ui.field>');

        $this->assertStringContainsString('(opcional)', $this->element($document, 'label')->textContent);
    }

    public function test_hint_is_linked_to_the_control(): void
    {
        $document = $this->renderComponent('<x-ui.field name="plate" label="Placa" hint="Ex.: ABC1D23"><x-ui.input /></x-ui.field>');

        $hint = $this->element($document, '#plate-hint');

        $this->assertSame('Ex.: ABC1D23', trim($hint->textContent));
        $this->assertSame(['plate-hint'], $this->describedByOf($this->element($document, 'input')));
        $this->assertContains('text-muted-foreground', $this->classesOf($hint));
    }

    public function test_hint_slot_accepts_markup(): void
    {
        $document = $this->renderComponent('<x-ui.field name="chassis" label="Chassi"><x-slot:hint>Está no <a href="/ajuda">documento do carro</a>.</x-slot:hint><x-ui.input /></x-ui.field>');

        $this->assertSame('/ajuda', $this->element($document, '#chassis-hint a')->getAttribute('href'));
    }

    public function test_error_from_the_bag_is_announced_and_marks_the_control_invalid(): void
    {
        $this->withViewErrors(['plate' => ['Informe a placa no padrão ABC1D23.']]);

        $document = $this->renderComponent('<x-ui.field name="plate" label="Placa" hint="Ex.: ABC1D23"><x-ui.input /></x-ui.field>');

        $input = $this->element($document, 'input');
        $error = $this->element($document, '#plate-error');

        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(['plate-hint', 'plate-error'], $this->describedByOf($input));
        $this->assertSame('Erro: Informe a placa no padrão ABC1D23.', trim(preg_replace('/\s+/', ' ', $error->textContent)));
        $this->assertSame('Erro: ', $this->element($document, '#plate-error .sr-only')->textContent);
        $this->assertContains('text-danger', $this->classesOf($error));
        $this->assertNotNull($error->querySelector('svg[aria-hidden="true"]'), 'O erro tem ícone, não só cor.');
        $this->assertSame('true', $this->element($document, '[data-slot="field"]')->getAttribute('data-invalid'));
    }

    public function test_named_error_bag_and_error_override(): void
    {
        $this->withViewErrors(['name' => ['Já existe uma marca com esse nome.']], 'brand');

        $fromBag = $this->renderComponent('<x-ui.field name="name" label="Marca" bag="brand"><x-ui.input /></x-ui.field>');
        $defaultBag = $this->renderComponent('<x-ui.field name="name" label="Marca"><x-ui.input /></x-ui.field>');
        $override = $this->renderComponent('<x-ui.field name="renavam" label="Renavam" error="Confira o Renavam no documento."><x-ui.input /></x-ui.field>');

        $this->assertStringContainsString('Já existe uma marca com esse nome.', $this->element($fromBag, '#name-error')->textContent);
        $this->assertSame('true', $this->element($fromBag, 'input')->getAttribute('aria-invalid'));
        $this->assertNull($defaultBag->querySelector('#name-error'));
        $this->assertStringContainsString('Confira o Renavam no documento.', $this->element($override, '#renavam-error')->textContent);
        $this->assertSame('true', $this->element($override, 'input')->getAttribute('aria-invalid'));
    }

    public function test_legacy_control_in_the_slot_is_wired_without_repeating_anything(): void
    {
        $this->withViewErrors(['mileage' => ['A quilometragem não pode ser menor que a última.']]);

        $document = $this->renderComponent(
            '<x-ui.field name="mileage" label="Quilometragem (km)" hint="Veja no painel."><input type="number" name="mileage" class="form-input"></x-ui.field>',
        );

        $input = $this->element($document, 'input');

        $this->assertSame('mileage', $input->getAttribute('id'));
        $this->assertSame('mileage', $this->element($document, 'label')->getAttribute('for'));
        $this->assertSame(['mileage-hint', 'mileage-error'], $this->describedByOf($input));
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
    }

    public function test_control_with_its_own_id_keeps_it_and_the_field_follows(): void
    {
        $document = $this->renderComponent('<x-ui.field name="email" label="E-mail" hint="Usamos para o acesso."><x-ui.input id="login-email" type="email" /></x-ui.field>');

        $this->assertSame('login-email', $this->element($document, 'label')->getAttribute('for'));
        $this->assertSame('login-email', $this->element($document, 'input')->getAttribute('id'));
        $this->assertNotNull($document->querySelector('#login-email-hint'));
        $this->assertSame(['login-email-hint'], $this->describedByOf($this->element($document, 'input')));
    }

    public function test_aside_and_label_slots_and_screen_reader_only_label(): void
    {
        $document = $this->renderComponent(
            '<x-ui.field name="password" label="Senha"><x-slot:aside><a href="/esqueci-senha">Esqueci minha senha</a></x-slot:aside><x-ui.input type="password" /></x-ui.field>',
        );
        $hiddenLabel = $this->renderComponent('<x-ui.field name="q" label="Buscar placa" label-sr-only><x-ui.input type="search" /></x-ui.field>');
        $richLabel = $this->renderComponent('<x-ui.field name="terms"><x-slot:label>Nome <abbr title="Registro">RG</abbr></x-slot:label><x-ui.input /></x-ui.field>');

        $this->assertSame('/esqueci-senha', $this->element($document, 'label + div a')->getAttribute('href'));
        $this->assertContains('sr-only', $this->classesOf($this->element($hiddenLabel, 'label')));
        $this->assertNotNull($richLabel->querySelector('label abbr'));
    }

    public function test_extra_attributes_and_classes_reach_the_wrapper(): void
    {
        $document = $this->renderComponent('<x-ui.field name="year" label="Ano" class="sm:col-span-2" data-row="1"><x-ui.input /></x-ui.field>');

        $field = $this->element($document, '[data-slot="field"]');

        $this->assertContains('sm:col-span-2', $this->classesOf($field));
        $this->assertContains('grid', $this->classesOf($field));
        $this->assertSame('1', $field->getAttribute('data-row'));
    }

    public function test_field_wires_select_textarea_and_file_input_too(): void
    {
        $this->withViewErrors(['brand_id' => ['Escolha a marca.'], 'notes' => ['Escreva menos.'], 'invoice' => ['Envie um PDF.']]);

        $document = $this->renderComponent(
            '<x-ui.field name="brand_id" label="Marca"><x-ui.select :options="[1 => \'Ford\']" /></x-ui.field>'
            .'<x-ui.field name="notes" label="Observações"><x-ui.textarea /></x-ui.field>'
            .'<x-ui.field name="invoice" label="Nota fiscal"><x-ui.file-input accept="application/pdf" /></x-ui.field>',
        );

        $this->assertSame(['brand_id-error'], $this->describedByOf($this->element($document, 'select')));
        $this->assertSame(['notes-error'], $this->describedByOf($this->element($document, 'textarea')));
        $this->assertSame(['invoice-rules', 'invoice-error'], $this->describedByOf($this->element($document, 'input[type="file"]')));
        $this->assertSame('invoice', $this->element($document, 'label[for="invoice"]')->getAttribute('for'));
    }
}
