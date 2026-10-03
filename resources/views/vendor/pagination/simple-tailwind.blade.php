{{--
    Paginação simples ($paginator->simplePaginate()->links()), publicada do framework e reescrita com
    os tokens do projeto. Textos fixos em pt-BR, sem variantes dark: e com alvos de 40px.
--}}
@if ($paginator->hasPages())
    @php
        $itemClass = 'inline-flex h-10 min-w-10 items-center justify-center gap-1 rounded-lg border px-3 text-sm font-medium transition-colors duration-fast ease-smooth-out motion-reduce:transition-none';
        $linkClass = $itemClass.' border-automotive-200 bg-white text-automotive-800 hover:border-automotive-300 hover:bg-automotive-100';
        $disabledClass = $itemClass.' cursor-not-allowed border-automotive-200 bg-automotive-50 text-automotive-400';
    @endphp

    <nav aria-label="Paginação" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="{{ $disabledClass }}" aria-disabled="true">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                Anterior
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClass }}">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                Anterior
            </a>
        @endif

        {{-- O CursorPaginator também usa esta view e não tem número de página. --}}
        @if (method_exists($paginator, 'currentPage'))
            <p class="whitespace-nowrap text-sm text-automotive-600 tabular-nums">
                Página <span class="font-medium text-automotive-900">{{ $paginator->currentPage() }}</span>
            </p>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClass }}">
                Próxima
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </a>
        @else
            <span class="{{ $disabledClass }}" aria-disabled="true">
                Próxima
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </span>
        @endif
    </nav>
@endif
