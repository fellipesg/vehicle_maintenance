{{--
    Trilha de navegação (onde a página está na hierarquia). Obrigatória a partir do 2º nível.

    Props:
    - items: lista de itens, do mais alto ao atual. Cada item aceita
      ['Meus veículos', route('user.vehicles.index')] (posicional) ou
      ['label' => 'Meus veículos', 'url' => route(...)] (também 'href'). Texto sozinho vale como item
      sem link. O último é a página atual: sai sem link e com aria-current="page", mesmo que tenha
      URL. Itens com rótulo vazio são ignorados; lista vazia não desenha nada.

    Estrutura: <nav aria-label="Trilha"> com <ol>; o separador (chevron) é decorativo.
    Abaixo de sm mostra só o ancestral com link mais próximo, como "‹ Meus veículos", com alvo de
    40px; o título da página já diz onde se está. Grupo sem link (['Frota'], ['Cadastros']) nunca é
    o item do celular: sem nenhum ancestral com link, aparece a própria página atual.

    Ex.: <x-ui.breadcrumb :items="[['Meus veículos', route('user.vehicles.index')], [$vehicle->model]]" />
--}}
@props([
    'items' => [],
])
@php
    $breadcrumbItems = collect(is_iterable($items) ? $items : [])
        ->map(function (mixed $item): array {
            if (! is_array($item)) {
                return ['label' => trim((string) $item), 'url' => null];
            }

            $label = $item['label'] ?? $item[0] ?? '';
            $url = $item['url'] ?? $item['href'] ?? $item[1] ?? null;

            return ['label' => trim((string) $label), 'url' => filled($url) ? (string) $url : null];
        })
        ->filter(fn (array $item): bool => $item['label'] !== '')
        ->values();
    $breadcrumbLastIndex = $breadcrumbItems->count() - 1;
    $breadcrumbParentIndex = $breadcrumbItems->slice(0, -1)
        ->filter(fn (array $item): bool => $item['url'] !== null)
        ->keys()
        ->last() ?? $breadcrumbLastIndex;
@endphp
@if($breadcrumbItems->isNotEmpty())
    <nav {{ $attributes->merge(['aria-label' => 'Trilha', 'data-slot' => 'breadcrumb']) }}>
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-muted-foreground">
            @foreach($breadcrumbItems as $breadcrumbIndex => $breadcrumbItem)
                <li @class([
                    'inline-flex min-w-0 items-center gap-1.5',
                    'max-sm:hidden' => $breadcrumbLastIndex > 0 && $breadcrumbIndex !== $breadcrumbParentIndex,
                ])>
                    @if($breadcrumbIndex > 0)
                        <x-ui.icon name="chevron-right" variant="solid" class="size-4 text-subtle-foreground max-sm:hidden" />
                    @endif
                    @if($breadcrumbIndex === $breadcrumbLastIndex)
                        <span aria-current="page" class="font-medium break-words text-foreground">{{ $breadcrumbItem['label'] }}</span>
                    @elseif($breadcrumbItem['url'] !== null)
                        <a
                            href="{{ $breadcrumbItem['url'] }}"
                            class="inline-flex items-center gap-1 rounded-sm break-words underline-offset-4 transition-colors duration-fast ease-smooth-out hover:text-foreground hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none max-sm:min-h-10"
                        >@if($breadcrumbIndex === $breadcrumbParentIndex)<x-ui.icon name="chevron-left" variant="solid" class="size-4 sm:hidden" />@endif{{ $breadcrumbItem['label'] }}</a>
                    @else
                        <span class="break-words">{{ $breadcrumbItem['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
