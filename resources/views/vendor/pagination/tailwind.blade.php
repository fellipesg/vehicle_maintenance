{{--
    Paginação padrão do app ($paginator->links()), publicada do framework e reescrita com os tokens
    do projeto. Textos fixos em pt-BR, sem variantes dark: e com alvos de 40px.
    Mobile: Anterior, "Página X de Y" e Próxima. A partir de sm: resumo e números de página em
    janela compacta (1 … 5 6 7 … 12).
--}}
@if ($paginator->hasPages())
    @php
        $itemClass = 'inline-flex h-10 min-w-10 items-center justify-center gap-1 rounded-lg border px-3 text-sm font-medium tabular-nums transition-colors duration-fast ease-smooth-out motion-reduce:transition-none';
        $linkClass = $itemClass.' border-automotive-200 bg-white text-automotive-800 hover:border-automotive-300 hover:bg-automotive-100';
        $disabledClass = $itemClass.' cursor-not-allowed border-automotive-200 bg-automotive-50 text-automotive-400';
        $currentClass = $itemClass.' border-automotive-900 bg-automotive-900 text-white';

        // Janela compacta (primeira, vizinhas da atual e última; null = reticências) no lugar da
        // janela do framework, que chega a 15 itens e estoura containers estreitos.
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $visiblePages = collect([1, $currentPage - 1, $currentPage, $currentPage + 1, $lastPage])
            ->filter(fn (int $page): bool => $page >= 1 && $page <= $lastPage)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $pageWindow = [];
        foreach ($visiblePages as $index => $page) {
            $previousPage = $visiblePages[$index - 1] ?? null;
            if ($previousPage !== null && $page - $previousPage === 2) {
                $pageWindow[] = $page - 1;
            } elseif ($previousPage !== null && $page - $previousPage > 2) {
                $pageWindow[] = null;
            }
            $pageWindow[] = $page;
        }
    @endphp

    <nav aria-label="Paginação" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <p class="hidden whitespace-nowrap text-sm text-automotive-600 tabular-nums sm:block">
            @if ($paginator->firstItem())
                Mostrando
                <span class="font-medium text-automotive-900">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                de
                <span class="font-medium text-automotive-900">{{ $paginator->total() }}</span>
            @else
                Mostrando {{ $paginator->count() }} de {{ $paginator->total() }}
            @endif
        </p>

        <ul class="flex w-full items-center justify-between gap-1 sm:w-auto sm:flex-wrap sm:justify-end">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabledClass }}" aria-disabled="true">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only sm:not-sr-only">Anterior</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClass }}">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only sm:not-sr-only">Anterior</span>
                    </a>
                @endif
            </li>

            <li class="px-2 text-sm text-automotive-600 tabular-nums sm:hidden">
                Página <span class="font-medium text-automotive-900">{{ $paginator->currentPage() }}</span>
                de <span class="font-medium text-automotive-900">{{ $paginator->lastPage() }}</span>
            </li>

            @foreach ($pageWindow as $page)
                @if ($page === null)
                    <li class="hidden sm:block" aria-hidden="true">
                        <span class="inline-flex h-10 min-w-8 items-center justify-center text-sm text-automotive-500">…</span>
                    </li>
                @else
                    <li class="hidden sm:block">
                        @if ($page === $paginator->currentPage())
                            <span class="{{ $currentClass }}" aria-current="page">
                                <span class="sr-only">Página</span>
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $paginator->url($page) }}" class="{{ $linkClass }}" aria-label="Ir para a página {{ $page }}">{{ $page }}</a>
                        @endif
                    </li>
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClass }}">
                        <span class="sr-only sm:not-sr-only">Próxima</span>
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span class="{{ $disabledClass }}" aria-disabled="true">
                        <span class="sr-only sm:not-sr-only">Próxima</span>
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
