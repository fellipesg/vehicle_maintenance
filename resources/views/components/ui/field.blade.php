{{--
    Campo de formulário: rótulo, controle (slot), dica e erro, ligados por id.

    Props:
    - name: name do controle. Dá o id (items[0][price] vira items_0_price), a chave do erro em
      $errors e o name dos <x-ui.input>, <x-ui.select>, <x-ui.textarea> e <x-ui.file-input> do slot,
      que o recebem por @aware e não precisam repetir.
    - label: texto do rótulo (ou <x-slot:label> com HTML).
    - hint: dica abaixo do controle (ou <x-slot:hint> com link). Fica em {id}-hint.
    - required: asterisco visual + "(obrigatório)" para leitor de tela. Não põe required no
      controle; ponha no controle só quando quiser também a validação do navegador.
    - optional: "(opcional)" ao lado do rótulo.
    - labelSrOnly: rótulo só para leitor de tela (busca, linha de tabela).
    - bag: error bag nomeado (validateWithBag); o padrão é o default.
    - error: mensagem que substitui a de $errors (erro vindo de outra chave, por exemplo).

    Slots: o padrão recebe o controle; aside fica à direita do rótulo (link "Esqueci minha senha",
    contador).

    Ligação: o campo procura no slot o primeiro controle rotulável (input, select ou textarea). Se
    ele não tem id, recebe o do name; o rótulo aponta para esse id; a dica e o erro entram em
    aria-describedby e, com erro, o controle ganha aria-invalid="true". Funciona com os x-ui.* e com
    controle legado escrito à mão. Para dois formulários com o mesmo name na página, dê um id
    próprio ao controle (<x-ui.input id="login-email" />): o campo usa o id que achar.

    Checkbox, radio e switch têm rótulo e erro próprios: não vão dentro do campo. Para agrupá-los,
    use <x-ui.fieldset>.

    Ex.:
    <x-ui.field name="license_plate" label="Placa" hint="Ex.: ABC1D23" required>
        <x-ui.input autocomplete="off" data-mask="plate" />
    </x-ui.field>
--}}@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'labelSrOnly' => false,
    'bag' => null,
    'error' => null,
])
@php
    $fieldError = filled($error) ? (string) $error : \App\Support\FormField::error($errors ?? null, $name, $bag);
    $fieldHasHint = filled($hint);
    $fieldWiring = \App\Support\FormField::wireControl(
        $slot->toHtml(),
        \App\Support\FormField::controlId($name) ?? 'campo-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
        fn (string $controlId): array => [
            $fieldHasHint ? $controlId.'-hint' : null,
            $fieldError !== null ? $controlId.'-error' : null,
        ],
        $fieldError !== null,
    );
    $fieldControlId = $fieldWiring['id'];
    $fieldHasLabel = filled($label);
    $fieldHasAside = isset($aside) && filled($aside);
@endphp
<div {{ $attributes->class(['grid min-w-0 gap-1.5'])->merge(['data-slot' => 'field', 'data-invalid' => $fieldError !== null ? 'true' : null]) }}>
    @if($fieldHasLabel && $fieldHasAside)
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <x-ui.label :for="$fieldControlId" :required="$required" :optional="$optional" :sr-only="$labelSrOnly">{{ $label }}</x-ui.label>
            <div class="text-sm">{{ $aside }}</div>
        </div>
    @elseif($fieldHasLabel)
        <x-ui.label :for="$fieldControlId" :required="$required" :optional="$optional" :sr-only="$labelSrOnly">{{ $label }}</x-ui.label>
    @endif
    {!! $fieldWiring['html'] !!}
    @if($fieldHasHint)
        <p id="{{ $fieldControlId }}-hint" data-slot="field-hint" class="text-sm text-muted-foreground">{{ $hint }}</p>
    @endif
    @if($fieldError !== null)
        <p id="{{ $fieldControlId }}-error" data-slot="field-error" class="flex items-start gap-1.5 text-sm text-danger">
            <x-ui.icon name="exclamation-circle" variant="solid" class="mt-0.5 size-4" />
            <span><span class="sr-only">Erro: </span>{{ $fieldError }}</span>
        </p>
    @endif
</div>
