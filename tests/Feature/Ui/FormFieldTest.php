<?php

namespace Tests\Feature\Ui;

use App\Support\FormField;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * App\Support\FormField: as regras que os componentes de formulário compartilham (id, chave de
 * erro, old input, opções e a ligação do controle legado com o <x-ui.field>).
 */
class FormFieldTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_control_id_comes_from_the_name_in_bracket_or_dot_notation(): void
    {
        $this->assertSame('items_0_name', FormField::controlId('items[0][name]'));
        $this->assertSame('items_0_name', FormField::controlId('items.0.name'));
        $this->assertSame('photos', FormField::controlId('photos[]'));
        $this->assertSame('license_plate', FormField::controlId('license_plate'));
        $this->assertSame('services_troca_de_oleo', FormField::controlId('services[]', 'Troca de óleo'));
        $this->assertSame('category_1', FormField::controlId('category', '1'));
        $this->assertNull(FormField::controlId(null));
        $this->assertNull(FormField::controlId(''));
    }

    public function test_error_key_matches_the_message_bag_and_old_input_keys(): void
    {
        $this->assertSame('items.0.name', FormField::errorKey('items[0][name]'));
        $this->assertSame('photos', FormField::errorKey('photos[]'));
        $this->assertSame('user.email', FormField::errorKey('user.email'));
        $this->assertNull(FormField::errorKey(null));
    }

    public function test_error_reads_the_named_bag_and_item_errors_of_a_list(): void
    {
        $errors = (new ViewErrorBag)
            ->put('default', new MessageBag(['photos.1' => ['A foto 2 passa de 5 MB.'], 'plate' => ['Informe a placa.']]))
            ->put('brand', new MessageBag(['name' => ['Informe o nome da marca.']]));

        $this->assertSame('A foto 2 passa de 5 MB.', FormField::error($errors, 'photos[]'));
        $this->assertSame('Informe a placa.', FormField::error($errors, 'plate'));
        $this->assertSame('Informe o nome da marca.', FormField::error($errors, 'name', 'brand'));
        $this->assertNull(FormField::error($errors, 'name'));
        $this->assertNull(FormField::error(null, 'plate'));
    }

    public function test_is_checked_uses_old_input_only_after_a_submission(): void
    {
        $this->assertTrue(FormField::isChecked('remember', '1', true));

        $this->withOldInput(['email' => 'ana@example.com', 'services' => ['oil']]);

        $this->assertFalse(FormField::isChecked('remember', '1', true), 'Ausente do old input = desmarcado pelo usuário.');
        $this->assertTrue(FormField::isChecked('services[]', 'oil', false));
        $this->assertFalse(FormField::isChecked('services[]', 'filters', true));
    }

    public function test_is_selected_compares_as_text_single_values_and_lists(): void
    {
        $this->assertTrue(FormField::isSelected(3, '3'));
        $this->assertTrue(FormField::isSelected(['a', 2], '2'));
        $this->assertTrue(FormField::isSelected(collect(['x']), 'x'));
        $this->assertFalse(FormField::isSelected(null, ''));
        $this->assertFalse(FormField::isSelected(false, '0'));
    }

    public function test_options_accept_maps_item_lists_and_groups(): void
    {
        $this->assertSame([
            ['type' => 'option', 'value' => '1', 'label' => 'Ford', 'disabled' => false],
        ], FormField::options([1 => 'Ford']));

        $this->assertSame([
            ['type' => 'option', 'value' => 'sp', 'label' => 'São Paulo', 'disabled' => true],
        ], FormField::options([['value' => 'sp', 'label' => 'São Paulo', 'disabled' => true]]));

        $this->assertSame([
            ['type' => 'group', 'label' => 'Sudeste', 'options' => [
                ['type' => 'option', 'value' => 'rj', 'label' => 'Rio de Janeiro', 'disabled' => false],
            ]],
        ], FormField::options(['Sudeste' => ['rj' => 'Rio de Janeiro']]));

        $this->assertSame([], FormField::options(null));
    }

    public function test_described_by_joins_ids_without_blanks_or_repeats(): void
    {
        $this->assertSame('a b c', FormField::describedBy('a', null, 'b  a', '', 'c'));
        $this->assertNull(FormField::describedBy(null, ''));
    }

    public function test_wire_control_gives_a_legacy_input_the_id_description_and_invalid_state(): void
    {
        $wiring = FormField::wireControl(
            '<input type="hidden" name="_token" value="x"><input name="plate" class="form-input" placeholder="Ex.: id=&quot;fake&quot;">',
            'plate',
            fn (string $id): array => [$id.'-hint', $id.'-error'],
            true,
        );

        $input = $this->element($this->parseHtml($wiring['html']), 'input[name="plate"]');

        $this->assertTrue($wiring['found']);
        $this->assertSame('plate', $wiring['id']);
        $this->assertSame('plate', $input->getAttribute('id'));
        $this->assertSame(['plate-hint', 'plate-error'], $this->describedByOf($input));
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame('Ex.: id="fake"', $input->getAttribute('placeholder'), 'Texto entre aspas não é atributo.');
        $this->assertStringContainsString('<input type="hidden" name="_token" value="x">', $wiring['html'], 'O hidden fica intacto.');
    }

    public function test_wire_control_keeps_an_existing_id_and_merges_existing_descriptions(): void
    {
        $wiring = FormField::wireControl(
            '<select id="login-brand" aria-describedby="brand-note" aria-invalid="false" required><option>A</option></select>',
            'brand',
            fn (string $id): array => [$id.'-hint'],
            true,
        );

        $select = $this->element($this->parseHtml($wiring['html']), 'select');

        $this->assertSame('login-brand', $wiring['id']);
        $this->assertSame(['brand-note', 'login-brand-hint'], $this->describedByOf($select));
        $this->assertSame('false', $select->getAttribute('aria-invalid'), 'aria-invalid escrito à mão é respeitado.');
        $this->assertTrue($select->hasAttribute('required'));
    }

    public function test_wire_control_leaves_markup_without_a_control_untouched(): void
    {
        $html = '<div class="widget"><button type="button">Escolher</button></div>';
        $wiring = FormField::wireControl($html, 'campo', fn (string $id): array => [$id.'-hint'], false);

        $this->assertFalse($wiring['found']);
        $this->assertSame($html, $wiring['html']);
        $this->assertSame('campo', $wiring['id']);
    }

    public function test_wire_control_preserves_self_closing_tags_and_escapes_new_values(): void
    {
        $wiring = FormField::wireControl('<input name="a" />', 'a"b', fn (string $id): array => [], false);

        $this->assertSame('<input name="a" id="a&quot;b" />', $wiring['html']);
    }

    public function test_file_rules_describe_types_size_and_count_in_portuguese(): void
    {
        $this->assertSame('PDF, JPG ou PNG', FormField::acceptLabel('application/pdf, image/jpeg,.png,image/jpg'));
        $this->assertSame('Imagens', FormField::fileRules('image/*'));
        $this->assertSame('PDF · até 10 MB', FormField::fileRules('.pdf', 10));
        $this->assertSame('JPG ou PNG · até 2,5 MB · até 4 arquivos', FormField::fileRules('image/jpeg,image/png', 2.5, 4));
        $this->assertSame('Até 5 MB', FormField::fileRules(null, 5));
        $this->assertNull(FormField::fileRules(null));
        $this->assertSame('ZIP', FormField::acceptLabel('application/zip'));
    }
}
