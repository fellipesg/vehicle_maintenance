{{--
    Escolha única em cartões (status do artigo, tipo de conta, plano): cada opção é um cartão com
    rótulo, descrição e ícone opcionais, e o radio nativo por dentro (fora da tela, mas focável).
    As setas do teclado trocam a opção, como em qualquer grupo de radios. Use dentro de
    <x-ui.fieldset name="..." legend="...">: name, bag e o valor escolhido (selected) vêm do grupo
    por @aware, e o erro do grupo aparece uma vez, no fieldset.

    O cartão marcado não depende só da cor: a borda engrossa e o círculo à direita ganha o check.
    Foco pelo teclado desenha o contorno --color-ring no cartão inteiro.

    Props:
    - options (obrigatório): [valor => rótulo] ou [valor => ['label' => ..., 'description' => ...,
      'icon' => nome do x-ui.icon, 'disabled' => bool]]. Numa lista, cada item traz 'value'.
    - columns: 1 | 2 (o padrão, a partir de sm) | 3 (2 a partir de sm e 3 a partir de lg).
    - selected: valor marcado (vem do fieldset quando omitido). Depois de um envio com erro vale o
      que o usuário enviou. Quando a escolha importa, não marque nada por padrão.
    - name / bag / invalid: como no <x-ui.radio>. O id de cada opção é {name}_{valor}.
    - required: required nos radios (validação do navegador).

    Os demais atributos (data-*) vão para a grade. class também.

    Ex.:
    <x-ui.fieldset name="status" legend="Publicação" required>
        <x-ui.radio-cards :columns="3" :options="[
            'draft' => ['label' => 'Rascunho', 'description' => 'Só o admin vê.', 'icon' => 'pencil-square'],
            'published' => ['label' => 'Publicar agora', 'icon' => 'globe-alt'],
            'scheduled' => ['label' => 'Agendar', 'icon' => 'calendar-days'],
        ]" />
    </x-ui.fieldset>
--}}@aware([
    'name' => null,
    'bag' => null,
    'selected' => null,
])
@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'columns' => 2,
    'bag' => null,
    'invalid' => false,
    'required' => false,
])
@php
    $radioCardsOptions = collect(is_iterable($options) ? $options : [])
        ->map(function (mixed $option, int|string $key): array {
            $option = is_array($option) ? $option : ['label' => $option];

            return [
                'value' => (string) ($option['value'] ?? $key),
                'label' => trim((string) ($option['label'] ?? '')),
                'description' => trim((string) ($option['description'] ?? '')),
                'icon' => filled($option['icon'] ?? null) ? (string) $option['icon'] : null,
                'disabled' => (bool) ($option['disabled'] ?? false),
            ];
        })
        ->filter(fn (array $option): bool => $option['label'] !== '')
        ->values();

    \App\Support\UiProps::required('x-ui.radio-cards', 'options', $radioCardsOptions->isEmpty() ? null : 'ok', 'Passe ao menos uma opção com rótulo.');
    \App\Support\UiProps::required('x-ui.radio-cards', 'name', $name, 'Passe name ou use dentro de <x-ui.fieldset name>.');

    $radioCardsColumnsClass = match (\App\Support\UiProps::oneOf('x-ui.radio-cards', 'columns', $columns, ['1', '2', '3'], '2')) {
        '1' => '',
        '2' => 'sm:grid-cols-2',
        '3' => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $radioCardsGroupId = \App\Support\FormField::controlId($name);
    $radioCardsError = \App\Support\FormField::error($errors ?? null, $name, $bag);
    $radioCardsInvalid = (bool) $invalid || $radioCardsError !== null;
@endphp
<div {{ $attributes->class(['grid gap-3', $radioCardsColumnsClass])->merge(['data-slot' => 'radio-cards']) }}>
    @foreach($radioCardsOptions as $radioCard)
        @php
            $radioCardId = \App\Support\FormField::controlId($name, $radioCard['value']);
            $radioCardChecked = \App\Support\FormField::isChecked($name, $radioCard['value'], \App\Support\FormField::isSelected($selected, $radioCard['value']));
            $radioCardDescribedBy = \App\Support\FormField::describedBy(
                $radioCard['description'] !== '' ? $radioCardId.'-description' : null,
                $radioCardsError !== null && $radioCardsGroupId !== null ? $radioCardsGroupId.'-error' : null,
            );
        @endphp
        <label
            for="{{ $radioCardId }}"
            @class([
                'relative flex min-h-11 cursor-pointer items-start gap-3 rounded-card border bg-surface p-4 text-sm',
                'transition-[border-color,background-color,box-shadow] duration-fast ease-smooth-out motion-reduce:transition-none',
                'hover:border-border-strong has-checked:border-ring has-checked:bg-accent has-checked:shadow-[inset_0_0_0_1px_var(--color-ring)]',
                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                'has-disabled:cursor-not-allowed has-disabled:opacity-60',
                'border-border' => ! $radioCardsInvalid,
                'border-danger' => $radioCardsInvalid,
            ])
            data-slot="radio-card"
        >
            <input
                type="radio"
                name="{{ $name }}"
                id="{{ $radioCardId }}"
                value="{{ $radioCard['value'] }}"
                class="peer sr-only"
                data-slot="control"
                @checked($radioCardChecked)
                @disabled($radioCard['disabled'])
                @required($required)
                @if($radioCardsInvalid) aria-invalid="true" @endif
                @if($radioCardDescribedBy) aria-describedby="{{ $radioCardDescribedBy }}" @endif
            >
            @if($radioCard['icon'])
                <x-ui.icon :name="$radioCard['icon']" class="mt-0.5 size-5 text-muted-foreground peer-checked:text-accent-foreground" />
            @endif
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-foreground">{{ $radioCard['label'] }}</span>
                @if($radioCard['description'] !== '')
                    <span id="{{ $radioCardId }}-description" class="mt-0.5 block text-muted-foreground">{{ $radioCard['description'] }}</span>
                @endif
            </span>
            <span
                aria-hidden="true"
                data-slot="radio-card-indicator"
                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border border-input bg-surface text-surface transition-colors duration-fast ease-smooth-out motion-reduce:transition-none peer-checked:border-ring peer-checked:bg-ring [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100"
            >
                <x-ui.icon name="check" variant="solid" class="size-3.5" />
            </span>
        </label>
    @endforeach
</div>
