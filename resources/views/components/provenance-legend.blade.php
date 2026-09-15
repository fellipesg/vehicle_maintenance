@props([])

<div {{ $attributes->class(['flex flex-wrap items-center gap-6 text-sm text-automotive-700']) }}>
    <div class="flex items-center gap-2">
        <div class="prov-marker prov-marker--verified prov-verified w-6 h-6 text-[10px]">OF</div>
        <span>Selo da oficina <span class="text-automotive-500">(verificada)</span></span>
    </div>
    <div class="flex items-center gap-2">
        <div class="prov-marker prov-marker--declared prov-declared w-6 h-6 text-[10px]">PR</div>
        <span>Declarada <span class="text-automotive-500">(não verificada)</span></span>
    </div>
</div>
