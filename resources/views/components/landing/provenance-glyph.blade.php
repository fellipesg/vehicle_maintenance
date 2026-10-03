{{--
    Marcador de procedência dos mocks da landing, com glifo no lugar de letras ("S/D", "OS/D"):
    check no disco teal cheio para o Selo da oficina, lápis no anel âmbar tracejado para a
    declarada. Usa as classes .prov-marker do contrato (resources/css/provenance.css), então as
    cores são as mesmas do produto. É decorativo (aria-hidden): o texto ao lado diz a procedência.

    Props:
    - sealed: true para Selo da oficina (o padrão), false para declarada.
    - size: sm (24px, linha do tempo e PDF; o padrão) | lg (44px, seção Procedência).

    Ex.: <x-landing.provenance-glyph :sealed="false" size="lg" />
--}}
@props([
    'sealed' => true,
    'size' => 'sm',
])
@php
    $glyphSize = \App\Support\UiProps::oneOf('x-landing.provenance-glyph', 'size', $size, ['sm', 'lg'], 'sm');
    $glyphIsSealed = (bool) $sealed;
@endphp
<span {{ $attributes->class([$glyphIsSealed ? 'prov-verified' : 'prov-declared', 'inline-flex shrink-0'])->merge(['aria-hidden' => 'true', 'data-landing-glyph' => $glyphIsSealed ? 'sealed' : 'declared']) }}><span @class([
    'prov-marker',
    'prov-marker--sm' => $glyphSize === 'sm',
    'prov-marker--lg' => $glyphSize === 'lg',
    'prov-marker--verified' => $glyphIsSealed,
    'prov-marker--declared' => ! $glyphIsSealed,
])><x-ui.icon :name="$glyphIsSealed ? 'check' : 'pencil'" variant="solid" :class="$glyphSize === 'lg' ? 'size-5' : 'size-3.5'" /></span></span>@php
    // Sem quebra de linha depois do elemento: o fechamento deste bloco PHP engole a do fim do
    // arquivo, e o componente não ganha espaço sobrando quando fica no meio do texto.
@endphp
