@props([])

<div {{ $attributes->class(['flex flex-wrap items-center gap-6 text-sm text-automotive-700']) }}>
    <div class="flex items-center gap-2">
        <div class="prov-marker prov-marker--verified prov-marker--sm prov-verified">OF</div>
        <span>Selo da oficina <span class="text-automotive-500">(verificada)</span></span>
    </div>
    <div class="flex items-center gap-2">
        <div class="prov-marker prov-marker--declared prov-marker--sm prov-declared">PR</div>
        <span>Declarada <span class="text-automotive-500">(não verificada)</span></span>
    </div>
</div>
