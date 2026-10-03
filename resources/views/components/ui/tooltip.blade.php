{{--
    Dica curta ao passar o ponteiro ou focar um controle (role="tooltip"), feita em CSS; o JS de
    resources/js/ui/tooltip.js só fecha com Esc e desloca a dica que sairia da tela.

    Props:
    - text (obrigatório): o texto da dica.
    - placement: top (o padrão) | bottom | left | right.
    - id: id da dica (gerado quando omitido).

    Slot: o gatilho, um único elemento focável (botão ou link). O componente acrescenta
    aria-describedby="{id}" à primeira tag do slot, somando a um aria-describedby que já exista.
    Texto solto no slot vira um <span tabindex="0">.

    Regras (WCAG 1.4.13): a dica abre no hover e no foco, some com Esc e continua aberta enquanto o
    ponteiro passa do gatilho para ela. Não use a dica como a única fonte da informação nem para
    conteúdo interativo; botão só com ícone continua precisando de aria-label próprio.
    Fica em position absolute: dentro de contêiner com overflow-hidden, escolha um placement que
    caiba nele.

    Ex.:
    <x-ui.tooltip text="Usado em 3 OS: não pode ser excluído">
        <x-ui.button variant="secondary" aria-disabled="true">Excluir</x-ui.button>
    </x-ui.tooltip>
--}}
@props([
    'text',
    'placement' => 'top',
    'id' => null,
])
@php
    \App\Support\UiProps::required('x-ui.tooltip', 'text', $text);
    $tooltipPlacementClass = match (\App\Support\UiProps::oneOf('x-ui.tooltip', 'placement', $placement, ['top', 'bottom', 'left', 'right'], 'top')) {
        'top' => 'bottom-full left-1/2 mb-2 -translate-x-1/2 after:inset-x-0 after:top-full after:h-2',
        'bottom' => 'top-full left-1/2 mt-2 -translate-x-1/2 after:inset-x-0 after:bottom-full after:h-2',
        'left' => 'right-full top-1/2 mr-2 -translate-y-1/2 after:inset-y-0 after:left-full after:w-2',
        'right' => 'left-full top-1/2 ml-2 -translate-y-1/2 after:inset-y-0 after:right-full after:w-2',
    };
    $tooltipId = filled($id) ? (string) $id : 'dica-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $tooltipTrigger = trim((string) $slot);
    $tooltipTriggerTag = '/^<([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)(\s*\/?)>/';

    if (preg_match($tooltipTriggerTag, $tooltipTrigger) === 1) {
        $tooltipTrigger = preg_replace_callback($tooltipTriggerTag, function (array $match) use ($tooltipId): string {
            $tagAttributes = $match[2];

            if (preg_match('/\saria-describedby\s*=\s*"([^"]*)"/i', $tagAttributes, $describedBy) === 1) {
                $tagAttributes = str_replace(
                    $describedBy[0],
                    ' aria-describedby="'.trim($describedBy[1].' '.$tooltipId).'"',
                    $tagAttributes,
                );
            } else {
                $tagAttributes .= ' aria-describedby="'.$tooltipId.'"';
            }

            return '<'.$match[1].$tagAttributes.$match[3].'>';
        }, $tooltipTrigger, 1);
    } else {
        $tooltipTrigger = '<span tabindex="0" aria-describedby="'.$tooltipId.'" class="rounded-control underline decoration-dotted underline-offset-4">'.$tooltipTrigger.'</span>';
    }
@endphp
<span {{ $attributes->class('group/tooltip relative inline-flex') }} data-ui-tooltip>
    {!! $tooltipTrigger !!}
    <span
        id="{{ $tooltipId }}"
        role="tooltip"
        data-ui-tooltip-content
        @class([
            'pointer-events-none invisible absolute z-40 w-max max-w-[min(16rem,calc(100vw-2rem))] rounded-control bg-foreground px-2.5 py-1.5 text-left text-xs font-medium leading-snug text-background opacity-0 shadow-md',
            'after:absolute',
            $tooltipPlacementClass,
            'transition-[opacity,visibility] duration-fast ease-out motion-reduce:transition-none',
            'group-hover/tooltip:pointer-events-auto group-hover/tooltip:visible group-hover/tooltip:opacity-100 group-hover/tooltip:delay-300',
            'group-focus-within/tooltip:visible group-focus-within/tooltip:opacity-100',
            'group-data-[tooltip-dismissed]/tooltip:invisible! group-data-[tooltip-dismissed]/tooltip:opacity-0! group-data-[tooltip-dismissed]/tooltip:pointer-events-none!',
        ])
    >{{ $text }}</span>
</span>
