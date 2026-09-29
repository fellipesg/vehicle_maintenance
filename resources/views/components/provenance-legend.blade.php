{{--
    Legenda dos marcadores de procedência (contrato de .ai/rules/theme.md). Os marcadores são
    decorativos (aria-hidden); o texto ao lado diz o que cada um é.

    Sempre os três: Selo da oficina, "PR" Declarada pelo proprietário e "LJ" Declarada pelo lojista.
    Cada card escolhe PR ou LJ pelo registro (x-provenance-marker), e qualquer lista pode ter os dois:
    o veículo do proprietário pode ter passado por uma loja, e o estoque do lojista traz o que os
    proprietários declararam.
--}}
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground'])->merge(['data-slot' => 'provenance-legend']) }}>
    <div class="flex items-center gap-2" data-provenance-legend-item="verified">
        <div class="prov-marker prov-marker--verified prov-marker--sm prov-verified" aria-hidden="true">OF</div>
        <span class="text-foreground">Selo da oficina <span class="text-muted-foreground">(verificada)</span></span>
    </div>
    <div class="flex items-center gap-2" data-provenance-legend-item="owner">
        <div class="prov-marker prov-marker--declared prov-marker--sm prov-declared" aria-hidden="true">PR</div>
        <span class="text-foreground">Declarada pelo proprietário <span class="text-muted-foreground">(não verificada)</span></span>
    </div>
    <div class="flex items-center gap-2" data-provenance-legend-item="garage">
        <div class="prov-marker prov-marker--declared prov-marker--sm prov-declared" aria-hidden="true">LJ</div>
        <span class="text-foreground">Declarada pelo lojista <span class="text-muted-foreground">(não verificada)</span></span>
    </div>
</div>
