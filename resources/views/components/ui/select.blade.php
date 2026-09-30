{{--
    Lista de opções nativa, com a seta desenhada pelo componente (x-ui.icon) no lugar da do plugin.

    Props:
    - name / id / bag / invalid: como no <x-ui.input> (name e bag vêm do <x-ui.field> por @aware).
    - options: mapa valor => rótulo (também Collection, como pluck('name', 'id')), lista de
      ['value' => ..., 'label' => ..., 'disabled' => bool], grupos ['Grupo' => [valor => rótulo]]
      (viram <optgroup>) ou casos de BackedEnum. Sem options, o slot recebe os <option> prontos.
    - value: valor selecionado (ou lista, com multiple). Depois de um envio com erro vale old(name).
    - placeholder: primeira opção vazia ("Selecione a marca"). Com required no select, ela fica
      desabilitada para não voltar a ser escolhida.

    Os demais atributos (required, multiple, disabled, data-*) vão para o <select>. class vai para a
    moldura externa (largura, margem).

    Ex.: <x-ui.select name="brand_id" :options="$brands->pluck('name', 'id')" placeholder="Selecione a marca" />
--}}@aware([
    'name' => null,
    'bag' => null,
])
@props([
    'name' => null,
    'id' => null,
    'options' => null,
    'value' => null,
    'placeholder' => null,
    'bag' => null,
    'invalid' => false,
])
@php
    $selectId = $id ?? \App\Support\FormField::controlId($name);
    $selectError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $selectInvalid = (bool) $invalid || $selectError !== null;
    $selectSelected = \App\Support\FormField::old($name, $value);
    $selectMultiple = (bool) $attributes->get('multiple', false);
    $selectRequired = (bool) $attributes->get('required', false);
    $selectListBox = $selectMultiple || (int) $attributes->get('size', 0) > 1;
    $selectOptions = \App\Support\FormField::options($options);
    $selectNothingChosen = blank($selectSelected) || $selectSelected === [];
    $selectAttributes = $attributes->except('class')
        ->class([
            'form-select text-base sm:text-sm',
            'h-10 appearance-none bg-none' => ! $selectListBox,
            'pr-3' => $selectListBox,
            'transition-[border-color,box-shadow] duration-fast ease-smooth-out motion-reduce:transition-none',
            'aria-invalid:border-danger aria-invalid:focus:ring-danger/60',
            'in-data-[slot=input-group]:h-9.5 in-data-[slot=input-group]:min-w-0 in-data-[slot=input-group]:border-0 in-data-[slot=input-group]:bg-transparent in-data-[slot=input-group]:shadow-none in-data-[slot=input-group]:focus:ring-0',
        ])
        ->merge([
            'name' => $name,
            'id' => $selectId,
            'aria-invalid' => $selectInvalid ? 'true' : null,
            'data-slot' => 'control',
        ]);
@endphp
<div {{ $attributes->only('class')->class(['relative min-w-0', 'in-data-[slot=input-group]:flex-1']) }} data-slot="select">
    <select {{ $selectAttributes }}>
        @if(filled($placeholder))
            <option value="" @selected($selectNothingChosen) @disabled($selectRequired)>{{ $placeholder }}</option>
        @endif
        @forelse($selectOptions as $selectOption)
            @if($selectOption['type'] === 'group')
                <optgroup label="{{ $selectOption['label'] }}">
                    @foreach($selectOption['options'] as $selectGroupOption)
                        <option value="{{ $selectGroupOption['value'] }}" @selected(\App\Support\FormField::isSelected($selectSelected, $selectGroupOption['value'])) @disabled($selectGroupOption['disabled'])>{{ $selectGroupOption['label'] }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $selectOption['value'] }}" @selected(\App\Support\FormField::isSelected($selectSelected, $selectOption['value'])) @disabled($selectOption['disabled'])>{{ $selectOption['label'] }}</option>
            @endif
        @empty
            {{ $slot }}
        @endforelse
    </select>
    @unless($selectListBox)
        <x-ui.icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
    @endunless
</div>
