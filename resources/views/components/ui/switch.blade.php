{{--
    Liga/desliga que vale na hora ou no envio do formulário ("Ativa", "Receber avisos por e-mail").
    É um checkbox nativo com role="switch": funciona sem JS, pelo teclado (Espaço) e envia o valor
    como qualquer checkbox. aria-checked acompanha o estado por resources/js/ui/switch.js
    (initSwitches). Nunca use para aceite de termos: aí vai <x-ui.checkbox>.

    Props:
    - name: obrigatório fora de <x-ui.fieldset name>.
    - label: texto do rótulo (ou o slot).
    - description: texto abaixo do rótulo, ligado por aria-describedby.
    - checked: estado inicial; depois de um envio com erro vale o que o usuário enviou.
    - value: '1' por padrão.
    - uncheckedValue: '0' por padrão, num hidden com o mesmo name, para "desligado" chegar ao
      servidor. false tira o hidden.
    - id / bag / invalid / error: como no <x-ui.checkbox>.

    Os demais atributos (disabled, data-*) vão para o <input>. class vai para a moldura.

    Ex.: <x-ui.switch name="is_active" label="Modelo ativo" :checked="$template->is_active" />
--}}@aware([
    'name' => null,
    'bag' => null,
])
@props([
    'name' => null,
    'id' => null,
    'value' => '1',
    'label' => null,
    'description' => null,
    'checked' => false,
    'uncheckedValue' => '0',
    'bag' => null,
    'invalid' => false,
    'error' => null,
])
@php
    $switchValue = (string) $value;
    $switchId = $id ?? \App\Support\FormField::controlId($name);
    $switchError = $error === false ? null : (filled($error) ? (string) $error : \App\Support\FormField::error($errors ?? null, $name, $bag));
    $switchInvalid = (bool) $invalid || $switchError !== null;
    $switchChecked = \App\Support\FormField::isChecked($name, $switchValue, (bool) $checked);
    $switchHasDescription = filled($description);
    $switchDescribedBy = \App\Support\FormField::describedBy(
        $switchHasDescription ? $switchId.'-description' : null,
        $switchError !== null ? $switchId.'-error' : null,
    );
@endphp
<div {{ $attributes->only('class')->class(['grid min-w-0']) }} data-slot="switch">
    @if($uncheckedValue !== null && $uncheckedValue !== false && filled($name))
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif
    <label for="{{ $switchId }}" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-60">
        <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-input transition-colors duration-fast ease-smooth-out motion-reduce:transition-none has-checked:bg-accent-foreground has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring has-aria-invalid:ring-2 has-aria-invalid:ring-danger">
            <input {{ $attributes->except('class')->class(['peer sr-only'])->merge([
                'type' => 'checkbox',
                'role' => 'switch',
                'name' => $name,
                'id' => $switchId,
                'value' => $switchValue,
                'checked' => $switchChecked,
                'aria-checked' => $switchChecked ? 'true' : 'false',
                'aria-invalid' => $switchInvalid ? 'true' : null,
                'aria-describedby' => $switchDescribedBy,
                'data-slot' => 'control',
                'data-switch' => true,
            ]) }}>
            <span aria-hidden="true" class="pointer-events-none block size-5 translate-x-0.5 rounded-full bg-surface shadow-sm transition-transform duration-fast ease-smooth-out motion-reduce:transition-none peer-checked:translate-x-5.5"></span>
        </span>
        <span class="pt-0.5 font-medium text-foreground">{{ $label ?? $slot }}</span>
    </label>
    @if($switchHasDescription)
        <p id="{{ $switchId }}-description" class="-mt-1.5 pb-2 pl-14 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    @if($switchError !== null)
        <p id="{{ $switchId }}-error" class="flex items-start gap-1.5 pl-14 text-sm text-danger">
            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
            <span><span class="sr-only">Erro: </span>{{ $switchError }}</span>
        </p>
    @endif
</div>
