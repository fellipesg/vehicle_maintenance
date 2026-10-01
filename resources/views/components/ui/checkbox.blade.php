{{--
    Caixa de marcação com rótulo clicável (alvo de 40px de altura) e descrição opcional.

    Props:
    - name: fora de um <x-ui.fieldset name> é obrigatório. "services[]" marca uma lista.
    - value: '1' por padrão.
    - label: texto do rótulo (ou o slot, que aceita link).
    - description: texto abaixo do rótulo, ligado por aria-describedby.
    - checked: estado inicial. Sem ele, vale o selected do <x-ui.fieldset> (valor ou lista). Depois
      de um envio com erro vale o que o usuário enviou.
    - uncheckedValue: com valor ('0'), um hidden com o mesmo name envia esse valor quando a caixa
      fica desmarcada.
    - id: o padrão sai do name (e do value, em lista).
    - bag / invalid: como no <x-ui.input>.
    - error: mensagem que substitui a de $errors; false não desenha o erro aqui. Em lista
      ("services[]") o erro nunca é desenhado aqui: fica no <x-ui.fieldset name="services">, e a
      caixa aponta para ele.

    Nunca marcado e desabilitado ao mesmo tempo para travar uma escolha: explique na descrição.
    Os demais atributos (required, disabled, data-*) vão para o <input>. class vai para a moldura.

    Ex.: <x-ui.checkbox name="remember" label="Lembrar de mim neste aparelho" />
--}}@aware([
    'name' => null,
    'bag' => null,
    'selected' => null,
])
@props([
    'name' => null,
    'id' => null,
    'value' => '1',
    'label' => null,
    'description' => null,
    'checked' => null,
    'uncheckedValue' => null,
    'bag' => null,
    'invalid' => false,
    'error' => null,
])
@php
    $checkboxValue = (string) $value;
    $checkboxInList = \App\Support\FormField::isArrayName($name);
    $checkboxId = $id ?? \App\Support\FormField::controlId($name, $checkboxInList ? $checkboxValue : null);
    $checkboxGroupId = \App\Support\FormField::controlId($name);
    $checkboxError = $error === false ? null : (filled($error) ? (string) $error : \App\Support\FormField::error($errors ?? null, $name, $bag));
    $checkboxShowsError = $checkboxError !== null && $error !== false && ! $checkboxInList;
    $checkboxInvalid = (bool) $invalid || $checkboxError !== null;
    $checkboxChecked = \App\Support\FormField::isChecked(
        $name,
        $checkboxValue,
        $checked !== null ? (bool) $checked : \App\Support\FormField::isSelected($selected, $checkboxValue),
    );
    $checkboxHasDescription = filled($description);
    $checkboxDescribedBy = \App\Support\FormField::describedBy(
        $checkboxHasDescription ? $checkboxId.'-description' : null,
        $checkboxShowsError ? $checkboxId.'-error' : null,
        $checkboxError !== null && $checkboxInList && $checkboxGroupId !== null ? $checkboxGroupId.'-error' : null,
    );
@endphp
<div {{ $attributes->only('class')->class(['grid min-w-0']) }} data-slot="checkbox">
    @if($uncheckedValue !== null && $uncheckedValue !== false && filled($name))
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif
    <label for="{{ $checkboxId }}" class="flex min-h-10 cursor-pointer items-start gap-3 py-2.5 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-60">
        <input {{ $attributes->except('class')->class([
            'mt-0.5 size-4 shrink-0 cursor-pointer rounded border-input text-accent-foreground not-checked:bg-surface',
            'transition-colors duration-fast ease-smooth-out motion-reduce:transition-none',
            'focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
            'aria-invalid:border-danger disabled:cursor-not-allowed',
        ])->merge([
            'type' => 'checkbox',
            'name' => $name,
            'id' => $checkboxId,
            'value' => $checkboxValue,
            'checked' => $checkboxChecked,
            'aria-invalid' => $checkboxInvalid ? 'true' : null,
            'aria-describedby' => $checkboxDescribedBy,
            'data-slot' => 'control',
        ]) }}>
        <span class="font-medium text-foreground">{{ $label ?? $slot }}</span>
    </label>
    @if($checkboxHasDescription)
        <p id="{{ $checkboxId }}-description" class="-mt-2 pb-2 pl-7 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    @if($checkboxShowsError)
        <p id="{{ $checkboxId }}-error" class="flex items-start gap-1.5 pl-7 text-sm text-danger">
            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
            <span><span class="sr-only">Erro: </span>{{ $checkboxError }}</span>
        </p>
    @endif
</div>
