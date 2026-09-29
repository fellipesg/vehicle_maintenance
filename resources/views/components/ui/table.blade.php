{{--
    Tabela de dados com rolagem horizontal própria (a página nunca rola de lado), legenda para
    leitor de tela, estado vazio, ordenação por coluna (aria-sort) e modo empilhado no celular.

    Props:
    - caption: obrigatório. Nomeia a tabela (<caption>, sr-only por padrão) e a região rolável.
    - label: nome da região rolável, quando deve ser diferente da legenda.
    - show-caption: mostra a legenda acima da tabela.
    - empty: título do estado vazio ("Nenhuma oficina cadastrada"), mostrado numa linha com colspan
      quando o corpo não tem linhas. Para ações no vazio, use <x-slot:empty> com um x-ui.empty-state
      completo.
    - empty-description / empty-icon: descrição e ícone do estado vazio padrão.
    - columns: número de colunas para o colspan do vazio. Padrão: conta os <th> do slot head.
    - stack: abaixo de md cada linha vira um bloco empilhado: a célula <th scope="row"> vai para o
      topo como título (em qualquer coluna que esteja) e cada <td> ganha o rótulo da coluna antes do valor (data-label, lido do <th> do cabeçalho; coluna
      só com texto sr-only, como "Ações", fica sem rótulo). O cabeçalho continua para leitor de tela
      (sr-only) e a tabela recebe os papéis ARIA explícitos (table, row, cell), que o Safari tira
      quando as células deixam de ser display: table-cell.
    - sort / direction: coluna ordenada agora (a chave do data-sort) e o sentido, asc (o padrão) ou
      desc. Só esse <th> recebe aria-sort ("ascending" / "descending").
    - sort-url: Closure(string $coluna, string $sentido): string que monta o link de cada coluna
      ordenável. Padrão: a URL atual com ?ordenar={coluna}&direcao={asc|desc}, sem a página.

    Slots:
    - head: as linhas do <thead> (<tr><th>...</th></tr>). Todo <th> sem scope ganha scope="col".
      Coluna ordenável: <th data-sort="data">Data</th>; o texto vira um link que alterna o sentido
      (o primeiro clique usa data-sort-default="desc" quando a coluna começa do maior, como datas).
      Coluna de ações: <th><span class="sr-only">Ações</span></th>.
    - body (ou o slot padrão): as linhas do <tbody>. Célula de título de linha: <th scope="row">.
    - foot: linhas do <tfoot> (totais).

    Células: px-4 py-3 por padrão, com especificidade zero (:where), então uma classe na própria
    célula (text-right, px-2, whitespace-nowrap) sempre vence. Números em tabular-nums. A região
    rolável tem tabindex="0" para rolar pelo teclado (e ganha o contorno de foco).

    Ex.: <x-ui.table caption="Oficinas cadastradas" empty="Nenhuma oficina cadastrada" stack
                     :sort="$ordenar" :direction="$direcao">
             <x-slot:head><tr><th data-sort="nome">Nome</th><th>Cidade</th><th class="text-right" data-sort="os" data-sort-default="desc">OS</th></tr></x-slot:head>
             @foreach($workshops as $workshop)
                 <tr><th scope="row" class="font-medium">{{ $workshop->name }}</th><td>{{ $workshop->city }}</td><td class="text-right">{{ $workshop->maintenances_count }}</td></tr>
             @endforeach
         </x-ui.table>
--}}
@props([
    'caption' => null,
    'label' => null,
    'showCaption' => false,
    'empty' => null,
    'emptyDescription' => null,
    'emptyIcon' => null,
    'columns' => null,
    'stack' => false,
    'sort' => null,
    'direction' => 'asc',
    'sortUrl' => null,
])
@php
    \App\Support\UiProps::required('x-ui.table', 'caption', $caption, 'A legenda nomeia a tabela e a região rolável para leitor de tela.');

    $tableStacks = (bool) $stack;
    $tableSortKey = filled($sort) ? (string) $sort : null;
    $tableSortDirection = \App\Support\UiProps::oneOf('x-ui.table', 'direction', $direction ?? 'asc', ['asc', 'desc'], 'asc');
    $tableSortUrl = $sortUrl instanceof \Closure
        ? $sortUrl
        : fn (string $sortColumn, string $sortDirection): string => request()->fullUrlWithQuery(['ordenar' => $sortColumn, 'direcao' => $sortDirection, 'page' => null]);

    $tableHasHead = isset($head) && ! \App\Support\UiProps::isBlank($head);
    $tableHasFoot = isset($foot) && ! \App\Support\UiProps::isBlank($foot);
    $tableBody = isset($body) ? $body : $slot;
    $tableColumnLabels = [];

    $tableHeadHtml = $tableHasHead
        ? (string) preg_replace_callback(
            '/<th(?=[\s>])([^>]*)>(.*?)<\/th>/is',
            function (array $match) use (&$tableColumnLabels, $tableSortKey, $tableSortDirection, $tableSortUrl, $tableStacks): string {
                $cellAttributes = $match[1];
                $cellContent = $match[2];

                if (! preg_match('/\sscope=/i', $cellAttributes)) {
                    $cellAttributes = ' scope="col"'.$cellAttributes;
                }

                // Rótulo da coluna para o modo empilhado: o texto visível (sem o que é só sr-only).
                $visibleText = preg_replace('/<span[^>]*\bsr-only\b[^>]*>.*?<\/span>/is', '', $cellContent) ?? '';
                $columnLabel = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($visibleText), ENT_QUOTES | ENT_HTML5)));
                $columnSpan = preg_match('/\scolspan="(\d+)"/i', $cellAttributes, $spanMatch) ? max(1, (int) $spanMatch[1]) : 1;
                array_push($tableColumnLabels, ...array_fill(0, $columnSpan, $columnLabel));

                if (preg_match('/\sdata-sort="([^"]+)"/i', $cellAttributes, $sortMatch)) {
                    $columnKey = html_entity_decode($sortMatch[1], ENT_QUOTES | ENT_HTML5);
                    $columnIsSorted = $tableSortKey !== null && $columnKey === $tableSortKey;
                    $firstDirection = preg_match('/\sdata-sort-default="desc"/i', $cellAttributes) ? 'desc' : 'asc';
                    $nextDirection = $columnIsSorted ? ($tableSortDirection === 'asc' ? 'desc' : 'asc') : $firstDirection;

                    if ($columnIsSorted && ! preg_match('/\saria-sort=/i', $cellAttributes)) {
                        $cellAttributes .= ' aria-sort="'.($tableSortDirection === 'asc' ? 'ascending' : 'descending').'"';
                    }

                    $sortIcon = $columnIsSorted
                        ? \Illuminate\Support\Facades\Blade::render('<x-ui.icon :name="$iconName" variant="solid" class="size-4" />', ['iconName' => $tableSortDirection === 'asc' ? 'chevron-up' : 'chevron-down'])
                        : '';
                    $sortHint = $nextDirection === 'asc' ? 'ordenar em ordem crescente' : 'ordenar em ordem decrescente';

                    $cellContent = '<a href="'.e($tableSortUrl($columnKey, $nextDirection)).'" data-slot="table-sort"'
                        .' class="-mx-1 inline-flex items-center gap-1 rounded-control px-1 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">'
                        .$cellContent.$sortIcon.'<span class="sr-only">, '.$sortHint.'</span></a>';
                }

                if ($tableStacks && ! preg_match('/\srole=/i', $cellAttributes)) {
                    $cellAttributes .= ' role="columnheader"';
                }

                return '<th'.$cellAttributes.'>'.$cellContent.'</th>';
            },
            (string) $head,
        )
        : '';

    if ($tableStacks && $tableHeadHtml !== '') {
        $tableHeadHtml = (string) preg_replace('/<tr(?=[\s>])(?![^>]*\srole=)/i', '<tr role="row"', $tableHeadHtml);
    }

    $tableColumns = is_numeric($columns) && (int) $columns > 0
        ? (int) $columns
        : max(1, count($tableColumnLabels));
    $tableShowsEmpty = ! $tableBody->hasActualContent() && ! \App\Support\UiProps::isBlank($empty);
    $tableLabel = filled($label) ? (string) $label : trim(strip_tags((string) $caption));

    // Modo empilhado: papéis ARIA explícitos e o rótulo da coluna em cada <td> do corpo e do rodapé.
    $tableStackRows = function (string $rowsHtml) use ($tableColumnLabels): string {
        return (string) preg_replace_callback('/<tr(?=[\s>])([^>]*)>(.*?)<\/tr>/is', function (array $rowMatch) use ($tableColumnLabels): string {
            $columnIndex = 0;
            $rowAttributes = preg_match('/\srole=/i', $rowMatch[1]) ? $rowMatch[1] : $rowMatch[1].' role="row"';
            $rowCells = (string) preg_replace_callback('/<(td|th)(?=[\s>])([^>]*)>/i', function (array $cellMatch) use (&$columnIndex, $tableColumnLabels): string {
                $cellTag = strtolower($cellMatch[1]);
                $cellAttributes = $cellMatch[2];
                $columnLabel = $tableColumnLabels[$columnIndex] ?? '';
                $columnIndex += preg_match('/\scolspan="(\d+)"/i', $cellAttributes, $spanMatch) ? max(1, (int) $spanMatch[1]) : 1;

                if (! preg_match('/\srole=/i', $cellAttributes)) {
                    $cellAttributes .= $cellTag === 'th' ? ' role="rowheader"' : ' role="cell"';
                }

                if ($cellTag === 'td' && $columnLabel !== '' && ! preg_match('/\sdata-label=/i', $cellAttributes)) {
                    $cellAttributes .= ' data-label="'.e($columnLabel).'"';
                }

                return '<'.$cellTag.$cellAttributes.'>';
            }, $rowMatch[2]);

            return '<tr'.$rowAttributes.'>'.$rowCells.'</tr>';
        }, $rowsHtml);
    };

    $tableBodyHtml = $tableShowsEmpty ? '' : ($tableStacks ? $tableStackRows((string) $tableBody) : (string) $tableBody);
    $tableFootHtml = $tableHasFoot ? ($tableStacks ? $tableStackRows((string) $foot) : (string) $foot) : '';

    $tableStackClasses = $tableStacks ? [
        'max-md:block',
        'max-md:[&>tbody]:block',
        'max-md:[&>tbody>tr]:flex max-md:[&>tbody>tr]:flex-col max-md:[&>tbody>tr]:px-4 max-md:[&>tbody>tr]:py-3',
        'max-md:[&>tbody>tr>:is(td,th)]:px-0 max-md:[&>tbody>tr>:is(td,th)]:py-1',
        'max-md:[&>tbody>tr>td]:flex max-md:[&>tbody>tr>td]:items-baseline max-md:[&>tbody>tr>td]:justify-between max-md:[&>tbody>tr>td]:gap-4 max-md:[&>tbody>tr>td]:text-right',
        'max-md:[&>tbody>tr>th]:order-first max-md:[&>tbody>tr>th]:block max-md:[&>tbody>tr>th]:text-left max-md:[&>tbody>tr>th]:text-base',
        'max-md:[&>tbody>tr>td[data-label]]:before:shrink-0 max-md:[&>tbody>tr>td[data-label]]:before:text-left max-md:[&>tbody>tr>td[data-label]]:before:font-medium max-md:[&>tbody>tr>td[data-label]]:before:text-muted-foreground max-md:[&>tbody>tr>td[data-label]]:before:content-[attr(data-label)]',
        'max-md:[&>tbody>tr[data-slot=table-empty]>td]:block',
        'max-md:[&>tfoot]:block max-md:[&>tfoot>tr]:flex max-md:[&>tfoot>tr]:items-baseline max-md:[&>tfoot>tr]:justify-between max-md:[&>tfoot>tr]:gap-4 max-md:[&>tfoot>tr]:px-4 max-md:[&>tfoot>tr]:py-3 max-md:[&>tfoot>tr>:is(td,th)]:p-0',
    ] : [];
@endphp
<div {{ $attributes->class([
    'relative w-full overflow-x-auto rounded-card border border-border bg-surface',
])->merge([
    'role' => 'region',
    'aria-label' => $tableLabel,
    'tabindex' => '0',
    'data-slot' => 'table',
    'data-stack' => $tableStacks ? 'md' : null,
]) }}>
    <table @if($tableStacks) role="table" @endif @class([
        'w-full text-left text-sm text-foreground tabular-nums [:where(&)_:is(th,td)]:px-4 [:where(&)_:is(th,td)]:py-3 [:where(&)_:is(th,td)]:align-middle [:where(&)_thead_th]:whitespace-nowrap',
        ...$tableStackClasses,
    ])>
        <caption @class([
            'sr-only' => ! (bool) $showCaption,
            'border-b border-border px-4 py-3 text-left text-sm font-semibold text-foreground' => (bool) $showCaption,
        ])>{{ $caption }}</caption>
        @if($tableHasHead)
            <thead data-slot="table-head" @if($tableStacks) role="rowgroup" @endif @class([
                'border-b border-border bg-surface-muted/60 text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                'max-md:sr-only' => $tableStacks,
            ])>{!! $tableHeadHtml !!}</thead>
        @endif
        <tbody data-slot="table-body" @if($tableStacks) role="rowgroup" @endif class="divide-y divide-border [&>tr]:transition-colors [&>tr]:duration-fast [&>tr]:ease-smooth-out motion-reduce:[&>tr]:transition-none [&>tr:not([data-slot=table-empty]):hover]:bg-surface-muted/50">
            @if($tableShowsEmpty)
                <tr data-slot="table-empty" @if($tableStacks) role="row" @endif>
                    <td colspan="{{ $tableColumns }}" @if($tableStacks) role="cell" @endif>
                        @if($empty instanceof \Illuminate\View\ComponentSlot)
                            {{ $empty }}
                        @else
                            <x-ui.empty-state :title="$empty" :description="$emptyDescription" :icon="$emptyIcon" variant="plain" size="sm" heading-level="p" />
                        @endif
                    </td>
                </tr>
            @else
                {!! $tableBodyHtml !!}
            @endif
        </tbody>
        @if($tableHasFoot)
            <tfoot data-slot="table-foot" @if($tableStacks) role="rowgroup" @endif class="border-t border-border bg-surface-muted/40 font-medium">{!! $tableFootHtml !!}</tfoot>
        @endif
    </table>
</div>
