{{--
    Lista de navegação (barra, sidebar ou menu mobile). Renderiza um <ul> de <x-ui.nav-item>, dentro
    de <nav aria-label> quando label é informado.

    Props:
    - items: lista de itens ou de grupos.
      Item: ['label' => 'Meus veículos', 'href' => route(...), 'active' => bool, 'icon' => 'truck',
             'badge' => 3, 'badgeLabel' => 'não lidas', 'itemClass' => 'max-md:hidden',
             'attributes' => ['data-x' => '...']]. Só label e href são obrigatórios.
      Grupo (só na vertical): ['label' => 'Cadastros', 'items' => [...itens]]. O rótulo nomeia a
      sublista (aria-labelledby) e não é título.
    - variant: horizontal (o padrão; h-10) | vertical (min-h-11, alvo de 44px).
    - label: aria-label do <nav>. Sem label, sai só o <ul>, para quando a página já tem o <nav>.

    Slot: sem items, o slot recebe <x-ui.nav-item> escritos à mão.

    O item ativo recebe aria-current="page" e um indicador que não depende só de cor: barra inferior
    (horizontal) ou barra lateral com fundo (vertical), mais o texto em negrito. As cores seguem os
    papéis semânticos: funcionam na navbar escura (.theme-inverse) e em página clara.

    Ex.: <x-ui.nav label="Principal" :items="$portalItems" />
         <x-ui.nav variant="vertical" :items="$adminGroups" label="Administração" />
--}}
@props([
    'items' => [],
    'variant' => 'horizontal',
    'label' => null,
])
@php
    $variant = \App\Support\UiProps::oneOf('x-ui.nav', 'variant', $variant, ['horizontal', 'vertical'], 'horizontal');
    $navListClass = match ($variant) {
        'horizontal' => 'flex flex-wrap items-center gap-1',
        'vertical' => 'flex flex-col gap-1',
    };
    $navInstanceId = 'nav-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
    $navRenderItem = fn (array $item): array => [
        'label' => $item['label'],
        'href' => $item['href'],
        'active' => (bool) ($item['active'] ?? false),
        'icon' => $item['icon'] ?? null,
        'badge' => $item['badge'] ?? null,
        'badgeLabel' => $item['badgeLabel'] ?? null,
        'itemClass' => $item['itemClass'] ?? null,
        // Atributos extras entram no <a> sem o escape automático do Blade: escapar aqui.
        'attributes' => new \Illuminate\View\ComponentAttributeBag(array_map(
            fn (mixed $value): mixed => is_string($value) ? e($value) : $value,
            $item['attributes'] ?? [],
        )),
    ];
@endphp
@if(filled($label))
<nav aria-label="{{ $label }}" {{ $attributes }}>
@endif
    <ul role="list" @if(filled($label)) class="{{ $navListClass }}" @else {{ $attributes->class($navListClass) }} @endif>
        @if($items === [] || $items === null)
            {{ $slot }}
        @else
            @foreach($items as $navEntry)
                @if(isset($navEntry['items']))
                    @php($navGroupId = $navInstanceId.'-grupo-'.$loop->index)
                    <li @class(['mt-4' => ! $loop->first])>
                        <p id="{{ $navGroupId }}" class="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-subtle-foreground">{{ $navEntry['label'] }}</p>
                        <ul role="list" aria-labelledby="{{ $navGroupId }}" class="flex flex-col gap-1">
                            @foreach($navEntry['items'] as $navGroupItem)
                                @php($navItem = $navRenderItem($navGroupItem))
                                <x-ui.nav-item
                                    :variant="$variant"
                                    :href="$navItem['href']"
                                    :active="$navItem['active']"
                                    :icon="$navItem['icon']"
                                    :badge="$navItem['badge']"
                                    :badge-label="$navItem['badgeLabel']"
                                    :item-class="$navItem['itemClass']"
                                    :attributes="$navItem['attributes']"
                                >{{ $navItem['label'] }}</x-ui.nav-item>
                            @endforeach
                        </ul>
                    </li>
                @else
                    @php($navItem = $navRenderItem($navEntry))
                    <x-ui.nav-item
                        :variant="$variant"
                        :href="$navItem['href']"
                        :active="$navItem['active']"
                        :icon="$navItem['icon']"
                        :badge="$navItem['badge']"
                        :badge-label="$navItem['badgeLabel']"
                        :item-class="$navItem['itemClass']"
                        :attributes="$navItem['attributes']"
                    >{{ $navItem['label'] }}</x-ui.nav-item>
                @endif
            @endforeach
        @endif
    </ul>
@if(filled($label))
</nav>
@endif
