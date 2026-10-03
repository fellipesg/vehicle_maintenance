{{--
    Controle segmentado (toggle-group de escolha única): filtros curtos e alternador de visão. Trilho
    com borda; a opção ativa ganha fundo de destaque, negrito e ícone (check ou o ícone da opção),
    então o estado não depende só da cor.

    Props:
    - label (obrigatório): nome do grupo para leitor de tela (aria-label), ex.: "Filtrar por procedência".
    - options: lista de opções, na ordem. Cada uma:
      ['value' => '1', 'label' => 'Selo da oficina', 'href' => route(...), 'icon' => null,
       'attributes' => ['data-maintenance-filter' => '1']].
      href é obrigatório no modo links. icon troca o check por um ícone fixo (Lista, Mapa).
      attributes vão para o <a>/<button> (ganchos de JS, data-*).
    - value: valor da opção ativa, comparado como texto (null vale '').
    - mode: links (o padrão): <nav> com <a>; cada opção é uma URL e a ativa leva aria-current="page".
      buttons: <div role="group"> com <button type="button" aria-pressed>, para o JS trocar a opção
      sem recarregar; o visual segue só o aria-pressed (variantes aria-pressed: e group-aria-pressed:).
    - equal: true dá a mesma largura a todas as opções (alternador Lista | Mapa).
    - name (só no modo buttons): os botões viram type="submit" name="{name}" value="{value}". Dentro
      de um <form method="get"> o filtro funciona sem JS (a página recarrega com ?{name}=valor); com
      JS, o script da tela intercepta o clique, troca o aria-pressed e filtra no lugar.
    - controls (só no modo buttons): id do elemento que o grupo filtra (aria-controls nos botões).

    Cada opção também aceita 'count' (número já contado, ex.: 4): aparece depois do rótulo, em
    tabular-nums ("Selo da oficina 4"), a partir de sm (no celular as três opções precisam caber
    numa linha; mostre o total também fora do controle, como "Mostrando 2 de 4").

    Cada opção tem alvo de 40px (min-h-10); com o p-0.5 do trilho, o controle fica com 44px.

    Ex.: <x-ui.segmented label="Filtrar por procedência" :value="$verified" :options="[
             ['value' => '', 'label' => 'Todas', 'href' => route('admin.vehicles.show', $vehicle)],
             ['value' => '1', 'label' => 'Selo da oficina', 'href' => route('admin.vehicles.show', [$vehicle, 'verified' => '1'])],
             ['value' => '0', 'label' => 'Declaradas', 'href' => route('admin.vehicles.show', [$vehicle, 'verified' => '0'])],
         ]" />
--}}
@props([
    'label' => null,
    'options' => [],
    'value' => null,
    'mode' => 'links',
    'equal' => false,
    'name' => null,
    'controls' => null,
])
@php
    \App\Support\UiProps::required('x-ui.segmented', 'label', $label, 'O label é o nome acessível do grupo de opções.');
    $segmentedMode = \App\Support\UiProps::oneOf('x-ui.segmented', 'mode', $mode, ['links', 'buttons'], 'links');
    $segmentedValue = (string) ($value ?? '');
    $segmentedOptions = collect(is_iterable($options) ? $options : [])
        ->map(function (mixed $option) use ($segmentedMode, $segmentedValue): array {
            $option = is_array($option) ? $option : [];
            $optionLabel = trim((string) ($option['label'] ?? ''));

            \App\Support\UiProps::required('x-ui.segmented', 'label em cada opção', $optionLabel);

            if ($segmentedMode === 'links') {
                \App\Support\UiProps::required('x-ui.segmented', "href na opção \"{$optionLabel}\"", $option['href'] ?? null, 'No modo links cada opção é uma URL.');
            }

            return [
                'label' => $optionLabel,
                'value' => (string) ($option['value'] ?? ''),
                'count' => isset($option['count']) && is_numeric($option['count']) ? number_format((float) $option['count'], 0, ',', '.') : null,
                'href' => (string) ($option['href'] ?? ''),
                'icon' => filled($option['icon'] ?? null) ? (string) $option['icon'] : null,
                'active' => (string) ($option['value'] ?? '') === $segmentedValue,
                // Atributos extras entram na tag sem o escape automático do Blade: escapar aqui.
                'attributes' => new \Illuminate\View\ComponentAttributeBag(array_map(
                    fn (mixed $attributeValue): mixed => is_string($attributeValue) ? e($attributeValue) : $attributeValue,
                    (array) ($option['attributes'] ?? []),
                )),
            ];
        })
        ->values();
    $segmentedSubmitName = $segmentedMode === 'buttons' && filled($name) ? (string) $name : null;
    $segmentedControls = $segmentedMode === 'buttons' && filled($controls) ? (string) $controls : null;
    $segmentedTrackClass = 'inline-flex max-w-full flex-wrap rounded-control border border-border-strong bg-surface p-0.5';
    // A contagem some abaixo de sm para as três opções caberem numa linha no celular (a mesma
    // informação fica no contador da lista e no selo da aba).
    $segmentedCountClass = 'hidden min-w-5 items-center justify-center rounded-full bg-surface-muted px-1.5 text-xs font-medium tabular-nums text-muted-foreground sm:inline-flex';
    $segmentedItemClass = [
        'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-[calc(var(--radius-control)-2px)] px-2.5 text-sm sm:px-3',
        'transition-colors duration-fast ease-smooth-out motion-reduce:transition-none',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        'flex-1' => (bool) $equal,
    ];
@endphp
@if($segmentedMode === 'links')
    <nav {{ $attributes->class($segmentedTrackClass)->merge(['aria-label' => $label, 'data-slot' => 'segmented']) }}>
        @foreach($segmentedOptions as $segmentedOption)
            <a
                href="{{ $segmentedOption['href'] }}"
                {{ $segmentedOption['attributes']->class([
                    ...$segmentedItemClass,
                    'bg-accent font-semibold text-accent-foreground' => $segmentedOption['active'],
                    'text-muted-foreground hover:bg-surface-muted hover:text-foreground' => ! $segmentedOption['active'],
                ]) }}
                @if($segmentedOption['active']) aria-current="page" @endif
            >
                @if($segmentedOption['icon'] !== null)
                    <x-ui.icon :name="$segmentedOption['icon']" class="size-4" />
                @elseif($segmentedOption['active'])
                    <x-ui.icon name="check" class="size-4" />
                @endif
                {{ $segmentedOption['label'] }}
                @if($segmentedOption['count'] !== null)
                    <span data-slot="segmented-count" class="{{ $segmentedCountClass }}">{{ $segmentedOption['count'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>
@else
    <div role="group" {{ $attributes->class($segmentedTrackClass)->merge(['aria-label' => $label, 'data-slot' => 'segmented']) }}>
        @foreach($segmentedOptions as $segmentedOption)
            <button
                @if($segmentedSubmitName !== null)
                    type="submit"
                    name="{{ $segmentedSubmitName }}"
                    value="{{ $segmentedOption['value'] }}"
                @else
                    type="button"
                @endif
                {{ $segmentedOption['attributes']->class([
                    ...$segmentedItemClass,
                    'group text-muted-foreground hover:bg-surface-muted hover:text-foreground',
                    'aria-pressed:bg-accent aria-pressed:font-semibold aria-pressed:text-accent-foreground',
                ]) }}
                @if($segmentedControls !== null) aria-controls="{{ $segmentedControls }}" @endif
                aria-pressed="{{ $segmentedOption['active'] ? 'true' : 'false' }}"
            >
                @if($segmentedOption['icon'] !== null)
                    <x-ui.icon :name="$segmentedOption['icon']" class="size-4" />
                @else
                    <x-ui.icon name="check" class="hidden size-4 group-aria-pressed:block" />
                @endif
                {{ $segmentedOption['label'] }}
                @if($segmentedOption['count'] !== null)
                    <span data-slot="segmented-count" class="{{ $segmentedCountClass }}">{{ $segmentedOption['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>
@endif
