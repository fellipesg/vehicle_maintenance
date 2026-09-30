{{--
    Seção de página: título (h2), descrição, ações à direita e conteúdo. O <section> é rotulado
    pelo próprio título (aria-labelledby), então aparece na lista de regiões do leitor de tela.

    Props:
    - title: obrigatório. Também aceita <x-slot:title>.
    - description: frase de apoio (opcional). Também aceita <x-slot:description>.
    - heading-level: h2 (o padrão) | h3 | h4, para seção dentro de seção.
    - id: opcional; o título ganha "{id}-titulo". Sem id, um é gerado.

    Slots: actions (ex.: x-ui.link "Ver todos" ou um x-ui.button secundário) e o padrão (conteúdo).

    Ex.: <x-ui.section title="Últimas manutenções" description="As 5 mais recentes dos seus veículos.">
             <x-slot:actions><x-ui.link :href="route('user.maintenances.index')" arrow>Ver todas</x-ui.link></x-slot:actions>
             ...
         </x-ui.section>
--}}
@props([
    'title' => null,
    'description' => null,
    'headingLevel' => 'h2',
])
@php
    \App\Support\UiProps::required('x-ui.section', 'title', $title);

    $sectionHeadingTag = \App\Support\UiProps::oneOf('x-ui.section', 'heading-level', $headingLevel, ['h2', 'h3', 'h4'], 'h2');
    $sectionTitleId = ($attributes->get('id') ?: 'secao-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8))).'-titulo';
    $sectionHasDescription = ! \App\Support\UiProps::isBlank($description);
    $sectionHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp
<section {{ $attributes->class(['space-y-4'])->merge(['aria-labelledby' => $sectionTitleId, 'data-slot' => 'section']) }}>
    <div data-slot="section-header" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <{{ $sectionHeadingTag }} id="{{ $sectionTitleId }}" data-slot="section-title" class="text-lg font-semibold text-foreground">{{ $title }}</{{ $sectionHeadingTag }}>
            @if($sectionHasDescription)
                <p data-slot="section-description" class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
            @endif
        </div>
        @if($sectionHasActions)
            <div data-slot="section-actions" class="flex flex-wrap items-center gap-2 sm:shrink-0">{{ $actions }}</div>
        @endif
    </div>
    @if($slot->hasActualContent())
        <div data-slot="section-content">{{ $slot }}</div>
    @endif
</section>
