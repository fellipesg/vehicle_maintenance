{{--
    Paleta de comandos (Ctrl K, ⌘K no Mac): um campo que filtra os destinos da área, as ações
    principais e a busca de veículo por placa, chassi ou RENAVAM. <dialog> nativo aberto por
    showModal() (resources/js/ui/dialog.js: fundo inerte, Esc, foco devolvido) e
    resources/js/ui/command.js para o atalho, o filtro e o teclado.

    Acessibilidade (padrão combobox + listbox do APG): o campo tem role="combobox",
    aria-controls na lista e aria-activedescendant na opção ativa; o foco fica sempre no campo.
    Setas para cima e para baixo trocam a opção, Enter abre, Esc fecha. As opções ficam em grupos
    (role="group" com rótulo). A região role="status" diz quantas opções sobraram.

    Enter na opção "Buscar veículo" abre a busca que já existe (sem rota nova) com o texto no
    parâmetro searchParam (GET). O campo não tem name: a página que já tem um campo de busca
    continua com um só. Quando o texto parece placa, chassi ou RENAVAM, essa opção sobe para o topo
    e já vem ativa.

    Props:
    - id: id do diálogo (o padrão é 'comandos'). Gatilhos: data-command-open="{id}" em botão ou
      link (sem JS o link segue o href).
    - destinations: destinos no formato de <x-ui.nav :items> (itens ou grupos), ex.:
      Portal::Admin->navigationItems(). O grupo vira palavra-chave e aparece à direita.
    - actions: ações principais [['label' => 'Nova OS', 'href' => ..., 'icon' => 'plus']].
    - searchAction (obrigatório): URL da busca de veículo (GET).
    - searchParam: nome do parâmetro da busca (o padrão é 'identifier'; no admin, 'search').
    - title: nome do diálogo e do campo (o padrão é 'Buscar ou ir para').

    Movimento: entra com opacidade e escala (200ms, ease-smooth-out) e sai em 150ms; com
    prefers-reduced-motion, só a opacidade, sem transição.

    Ex.: <x-ui.command
             :destinations="\App\Enums\Portal::Admin->navigationItems()"
             :actions="[['label' => 'Novo artigo', 'href' => route('admin.blog.create'), 'icon' => 'plus']]"
             :search-action="route('admin.vehicles.index')"
             search-param="search"
         />
--}}
@props([
    'id' => 'comandos',
    'destinations' => [],
    'actions' => [],
    'searchAction' => null,
    'searchParam' => 'identifier',
    'title' => 'Buscar ou ir para',
])
@php
    \App\Support\UiProps::required('x-ui.command', 'id', $id);
    \App\Support\UiProps::required('x-ui.command', 'searchAction', $searchAction, 'É a URL da busca de veículo que o Enter envia.');
    \App\Support\UiProps::required('x-ui.command', 'searchParam', $searchParam);

    $commandNormalize = fn (array $item, ?string $group = null): ?array => filled($item['label'] ?? null) && filled($item['href'] ?? null)
        ? [
            'label' => (string) $item['label'],
            'href' => (string) $item['href'],
            'icon' => filled($item['icon'] ?? null) ? (string) $item['icon'] : 'arrow-right',
            'group' => $group,
            'active' => (bool) ($item['active'] ?? false),
            'keywords' => trim(($group ?? '').' '.($item['keywords'] ?? '')),
        ]
        : null;

    $commandDestinations = collect(is_iterable($destinations) ? $destinations : [])
        ->flatMap(fn (array $entry): array => isset($entry['items'])
            ? array_map(fn (array $item): ?array => $commandNormalize($item, (string) $entry['label']), $entry['items'])
            : [$commandNormalize($entry)])
        ->filter()
        ->values();
    $commandActions = collect(is_iterable($actions) ? $actions : [])
        ->map(fn (array $action): ?array => $commandNormalize($action))
        ->filter()
        ->values();

    $commandTitleId = $id.'-titulo';
    $commandHintId = $id.'-dica';
    $commandInputId = $id.'-campo';
    $commandListId = $id.'-opcoes';
    $commandSearchLabel = 'Buscar veículo por placa, chassi ou RENAVAM';
    $commandOptionIndex = 0;
    $commandOptionClass = 'group/option flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-3 py-2 text-sm text-foreground select-none aria-selected:bg-accent aria-selected:text-accent-foreground';
    $commandGroups = array_values(array_filter([
        ['key' => 'destinos', 'label' => 'Ir para', 'items' => $commandDestinations],
        ['key' => 'acoes', 'label' => 'Ações', 'items' => $commandActions],
    ], fn (array $group): bool => $group['items']->isNotEmpty()));
@endphp
<dialog
    id="{{ $id }}"
    {{ $attributes->class([
        'theme-default mx-auto mt-[min(12dvh,6rem)] mb-auto w-[calc(100%-2rem)] max-w-xl max-h-[calc(100dvh-2rem)] overflow-hidden rounded-overlay border border-border bg-surface p-0 text-left text-foreground shadow-xl',
        'backdrop:bg-overlay',
        'opacity-0 open:opacity-100 starting:open:opacity-0',
        'motion-safe:scale-[.96] motion-safe:open:scale-100 motion-safe:starting:open:scale-[.96]',
        'transition-[opacity,scale,overlay,display] transition-discrete duration-fast ease-smooth-out open:duration-base',
        'backdrop:opacity-0 open:backdrop:opacity-100 starting:open:backdrop:opacity-0',
        'backdrop:transition-[opacity,overlay,display] backdrop:transition-discrete backdrop:duration-fast open:backdrop:duration-base',
        'motion-reduce:transition-none motion-reduce:backdrop:transition-none',
    ])->merge([
        'aria-labelledby' => $commandTitleId,
        'aria-describedby' => $commandHintId,
        'data-ui-dialog' => '',
        'data-ui-command' => '',
        'data-dismissible' => 'true',
        'data-close-on-backdrop' => 'true',
    ]) }}
>
    <h2 id="{{ $commandTitleId }}" class="sr-only">{{ $title }}</h2>
    <p id="{{ $commandHintId }}" class="sr-only">Digite para filtrar. Use as setas para escolher e Enter para abrir.</p>

    <div data-command-body>
        <div class="flex items-center gap-3 border-b border-border px-4 transition-[border-color,box-shadow] duration-fast ease-smooth-out focus-within:border-ring focus-within:shadow-[inset_0_-1px_0_var(--color-ring)] motion-reduce:transition-none">
            <x-ui.icon name="magnifying-glass" class="size-5 text-muted-foreground" />
            <label for="{{ $commandInputId }}" class="sr-only">{{ $title }}</label>
            <input
                type="text"
                id="{{ $commandInputId }}"
                role="combobox"
                aria-expanded="true"
                aria-controls="{{ $commandListId }}"
                aria-autocomplete="list"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                enterkeyhint="go"
                placeholder="Placa, chassi ou página"
                class="h-14 w-full min-w-0 border-0 bg-transparent px-0 text-base text-foreground shadow-none ring-0 outline-hidden placeholder:text-subtle-foreground sm:text-sm"
                data-command-input
                data-dialog-initial-focus
            >
            <button
                type="button"
                data-dialog-close
                data-dialog-close-button
                aria-label="Fechar"
                class="inline-flex h-7 shrink-0 items-center justify-center rounded-control border border-border bg-surface-muted px-2 text-xs font-medium text-muted-foreground transition-colors duration-fast ease-smooth-out hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
            >
                <span aria-hidden="true" class="max-sm:hidden">Esc</span>
                <x-ui.icon name="x-mark" class="size-4 sm:hidden" />
            </button>
        </div>

        <div id="{{ $commandListId }}" role="listbox" aria-label="Opções" class="max-h-[min(24rem,60dvh)] overflow-y-auto overscroll-contain p-2" data-command-list>
            @foreach($commandGroups as $commandGroup)
                <div role="presentation" class="pb-1" data-command-group="{{ $commandGroup['key'] }}">
                    <div id="{{ $id }}-grupo-{{ $commandGroup['key'] }}" aria-hidden="true" class="px-3 pt-2 pb-1 text-xs font-medium text-muted-foreground">{{ $commandGroup['label'] }}</div>
                    <div role="group" aria-labelledby="{{ $id }}-grupo-{{ $commandGroup['key'] }}">
                        @foreach($commandGroup['items'] as $commandItem)
                            <div
                                id="{{ $id }}-opcao-{{ $commandOptionIndex++ }}"
                                role="option"
                                aria-selected="false"
                                class="{{ $commandOptionClass }}"
                                data-command-option
                                data-command-url="{{ $commandItem['href'] }}"
                                data-command-keywords="{{ $commandItem['keywords'] }}"
                            >
                                <x-ui.icon :name="$commandItem['icon']" class="size-5 text-muted-foreground group-aria-selected/option:text-accent-foreground" />
                                <span class="min-w-0 flex-1 truncate" data-command-label>{{ $commandItem['label'] }}</span>
                                @if($commandItem['active'])
                                    <span class="shrink-0 text-xs text-muted-foreground group-aria-selected/option:text-accent-foreground">Página atual</span>
                                @elseif($commandItem['group'])
                                    <span class="shrink-0 text-xs text-muted-foreground group-aria-selected/option:text-accent-foreground" aria-hidden="true">{{ $commandItem['group'] }}</span>
                                @endif
                                <x-ui.icon name="arrow-right" class="hidden size-4 group-aria-selected/option:block" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div role="presentation" class="pb-1" data-command-group="busca">
                <div id="{{ $id }}-grupo-busca" aria-hidden="true" class="px-3 pt-2 pb-1 text-xs font-medium text-muted-foreground">Buscar</div>
                <div role="group" aria-labelledby="{{ $id }}-grupo-busca">
                    <div
                        id="{{ $id }}-opcao-busca"
                        role="option"
                        aria-selected="false"
                        class="{{ $commandOptionClass }}"
                        data-command-option
                        data-command-search
                        data-command-url="{{ $searchAction }}"
                        data-command-param="{{ $searchParam }}"
                        data-command-keywords="{{ $commandSearchLabel }} veículo placa chassi renavam"
                    >
                        <x-ui.icon name="magnifying-glass" class="size-5 text-muted-foreground group-aria-selected/option:text-accent-foreground" />
                        <span class="min-w-0 flex-1">
                            <span data-command-label>{{ $commandSearchLabel }}</span>
                            <span class="font-semibold" data-command-search-query></span>
                        </span>
                        <x-ui.icon name="arrow-right" class="hidden size-4 group-aria-selected/option:block" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-4 border-t border-border bg-surface-muted/60 px-4 py-2 text-xs text-muted-foreground max-sm:hidden" aria-hidden="true">
        <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-border bg-surface px-1.5 font-sans">↑</kbd><kbd class="rounded border border-border bg-surface px-1.5 font-sans">↓</kbd> escolher</span>
        <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-border bg-surface px-1.5 font-sans">Enter</kbd> abrir</span>
        <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-border bg-surface px-1.5 font-sans">Esc</kbd> fechar</span>
    </div>

    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-command-status></p>
</dialog>
