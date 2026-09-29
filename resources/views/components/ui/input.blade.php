{{--
    Campo de texto de uma linha (text, email, tel, number, date, search, password...).

    Props:
    - name: dentro de <x-ui.field> vem do campo por @aware; fora dele, passe aqui.
    - id: o padrão sai do name (items[0][price] vira items_0_price).
    - type: text (padrão). password ganha o botão "Mostrar senha" (resources/js/ui/password-toggle.js):
      nome fixo e estado em aria-pressed (pressionado = senha visível).
    - value: valor inicial. Depois de um envio com erro vale old(name); password e file nunca
      voltam preenchidos.
    - bag: error bag nomeado (também vem do <x-ui.field>).
    - invalid: força aria-invalid="true" sem erro em $errors.
    - leadingIcon / trailingIcon: nome de ícone Heroicons dentro da borda (vira <x-ui.input-group>).
    - revealable: false tira o botão "Mostrar senha" do type=password.

    Os demais atributos (placeholder, autocomplete, inputmode, required, data-mask...) vão para o
    <input>. class vai para o elemento mais externo: o <input>, ou a moldura quando há ícone ou
    botão de senha.

    Ex.: <x-ui.input name="email" type="email" autocomplete="email" leading-icon="envelope" />
--}}@aware([
    'name' => null,
    'bag' => null,
])
@props([
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'bag' => null,
    'invalid' => false,
    'leadingIcon' => null,
    'trailingIcon' => null,
    'revealable' => true,
])
@php
    $inputType = strtolower((string) $type);
    $inputId = $id ?? \App\Support\FormField::controlId($name);
    $inputError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $inputInvalid = (bool) $invalid || $inputError !== null;
    $inputValue = in_array($inputType, ['password', 'file'], true) ? null : \App\Support\FormField::old($name, $value);
    $inputHasToggle = $inputType === 'password' && $revealable;
    $inputGrouped = filled($leadingIcon) || filled($trailingIcon) || $inputHasToggle;

    if ($inputHasToggle && blank($inputId)) {
        $inputId = 'senha-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    }

    $inputAttributes = ($inputGrouped ? $attributes->except('class') : $attributes)
        ->class([
            'form-input h-10 text-base sm:text-sm',
            'transition-[border-color,box-shadow] duration-fast ease-smooth-out motion-reduce:transition-none',
            'aria-invalid:border-danger aria-invalid:focus:ring-danger/60',
            'in-data-[slot=input-group]:h-9.5 in-data-[slot=input-group]:min-w-0 in-data-[slot=input-group]:flex-1 in-data-[slot=input-group]:border-0 in-data-[slot=input-group]:bg-transparent in-data-[slot=input-group]:shadow-none in-data-[slot=input-group]:focus:ring-0',
        ])
        ->merge([
            'type' => $inputType,
            'name' => $name,
            'id' => $inputId,
            'value' => $inputValue,
            'aria-invalid' => $inputInvalid ? 'true' : null,
            'data-slot' => 'control',
        ]);
@endphp
@if($inputGrouped)
    <x-ui.input-group :class="$attributes->get('class')">
        @if(filled($leadingIcon))
            <x-slot:leading><x-ui.icon :name="$leadingIcon" /></x-slot:leading>
        @endif
        <input {{ $inputAttributes }}>
        @if($inputHasToggle || filled($trailingIcon))
            <x-slot:trailing>
                @if(filled($trailingIcon))<x-ui.icon :name="$trailingIcon" />@endif
                @if($inputHasToggle)
                    <button type="button"
                            class="-my-px -mr-px flex size-10 shrink-0 items-center justify-center rounded-r-control text-muted-foreground transition-colors duration-fast ease-smooth-out hover:text-foreground focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                            aria-controls="{{ $inputId }}"
                            aria-pressed="false"
                            aria-label="Mostrar senha"
                            data-password-toggle
                            hidden>
                        <x-ui.icon name="eye" data-password-toggle-icon="show" />
                        <x-ui.icon name="eye-slash" data-password-toggle-icon="hide" hidden />
                    </button>
                @endif
            </x-slot:trailing>
        @endif
    </x-ui.input-group>
@else
    <input {{ $inputAttributes }}>
@endif
