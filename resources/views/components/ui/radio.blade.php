{{--
    Opção de escolha única com rótulo clicável (alvo de 40px de altura) e descrição opcional. Use
    dentro de <x-ui.fieldset name="..." legend="...">: o name, o bag e o valor escolhido (selected)
    vêm do grupo por @aware, e o erro do grupo aparece uma vez, no fieldset. As setas do teclado
    trocam a opção, como em qualquer radio nativo.

    Props:
    - value: valor da opção (obrigatório).
    - label: texto do rótulo (ou o slot).
    - description: texto abaixo do rótulo, ligado por aria-describedby.
    - checked: estado inicial. Sem ele, marca quando value é o selected do fieldset. Depois de um
      envio com erro vale o que o usuário enviou.
    - name / id / bag / invalid: o id padrão é {name}_{value}.

    Quando a escolha importa, não deixe opção marcada por padrão.
    Os demais atributos (required, disabled, data-*) vão para o <input>. class vai para a moldura.

    Ex.:
    <x-ui.fieldset name="category" legend="Categoria do serviço" :selected="$maintenance->category">
        <x-ui.radio value="oil" label="Troca de óleo" />
        <x-ui.radio value="brakes" label="Freios" description="Pastilhas, discos e fluido." />
    </x-ui.fieldset>
--}}@aware([
    'name' => null,
    'bag' => null,
    'selected' => null,
])
@props([
    'name' => null,
    'id' => null,
    'value',
    'label' => null,
    'description' => null,
    'checked' => null,
    'bag' => null,
    'invalid' => false,
])
@php
    $radioValue = (string) $value;
    $radioId = $id ?? \App\Support\FormField::controlId($name, $radioValue);
    $radioGroupId = \App\Support\FormField::controlId($name);
    $radioError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $radioInvalid = (bool) $invalid || $radioError !== null;
    $radioChecked = \App\Support\FormField::isChecked(
        $name,
        $radioValue,
        $checked !== null ? (bool) $checked : \App\Support\FormField::isSelected($selected, $radioValue),
    );
    $radioHasDescription = filled($description);
    $radioDescribedBy = \App\Support\FormField::describedBy(
        $radioHasDescription ? $radioId.'-description' : null,
        $radioError !== null && $radioGroupId !== null ? $radioGroupId.'-error' : null,
    );
@endphp
<div {{ $attributes->only('class')->class(['grid min-w-0']) }} data-slot="radio">
    <label for="{{ $radioId }}" class="flex min-h-10 cursor-pointer items-start gap-3 py-2.5 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-60">
        <input {{ $attributes->except('class')->class([
            'mt-0.5 size-4 shrink-0 cursor-pointer rounded-full border-input text-accent-foreground not-checked:bg-surface',
            'transition-colors duration-fast ease-smooth-out motion-reduce:transition-none',
            'focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'aria-invalid:border-danger disabled:cursor-not-allowed',
        ])->merge([
            'type' => 'radio',
            'name' => $name,
            'id' => $radioId,
            'value' => $radioValue,
            'checked' => $radioChecked,
            'aria-invalid' => $radioInvalid ? 'true' : null,
            'aria-describedby' => $radioDescribedBy,
            'data-slot' => 'control',
        ]) }}>
        <span class="font-medium text-foreground">{{ $label ?? $slot }}</span>
    </label>
    @if($radioHasDescription)
        <p id="{{ $radioId }}-description" class="-mt-2 pb-2 pl-7 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
</div>
