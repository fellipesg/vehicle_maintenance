{{--
    Seção numerada de um formulário longo ("Registrar manutenção" do Proprietário e do Lojista, Nova
    OS, perfil da oficina): um card que é também um <fieldset>, com o título (h2) dentro da
    <legend>. O leitor de tela anuncia o grupo pelo nome e a lista de títulos da página mostra as
    seções na ordem.

    Props:
    - title (obrigatório): nome da seção ("Serviço", "Peças e serviços").
    - number: número da etapa (1, 2...), num disco antes do título ("Etapa 2:" para leitor de tela).
    - description: frase de apoio, ligada ao grupo por aria-describedby.
    - id: âncora da seção (o título ganha "{id}-titulo").
    - as: fieldset (o padrão, dentro de <form>) | section (fora do formulário, como a etapa do
      veículo da Nova OS, que tem o próprio form GET, ou uma seção só de leitura).

    Slots: o padrão (os campos) e actions (abaixo da descrição, ex.: "Adicionar peça ou serviço").

    Ex.: <x-ui.form-section id="secao-servico" :number="2" title="Serviço">...</x-ui.form-section>
--}}
@props([
    'title' => null,
    'number' => null,
    'description' => null,
    'as' => 'fieldset',
])
@php
    \App\Support\UiProps::required('x-ui.form-section', 'title', $title, 'O title é o nome da seção, lido com o grupo.');

    $formSectionTag = \App\Support\UiProps::oneOf('x-ui.form-section', 'as', $as, ['fieldset', 'section'], 'fieldset');
    $formSectionId = $attributes->get('id') ?: 'secao-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $formSectionIsFieldset = $formSectionTag === 'fieldset';
    $formSectionHasDescription = filled($description);
    $formSectionHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp
<{{ $formSectionTag }} {{ $attributes->class([
    'min-w-0 scroll-mt-24 rounded-card border border-border bg-surface p-4 text-foreground shadow-sm sm:p-6',
])->merge([
    'id' => $formSectionId,
    'aria-labelledby' => $formSectionIsFieldset ? null : $formSectionId.'-titulo',
    'aria-describedby' => $formSectionHasDescription ? $formSectionId.'-descricao' : null,
    'data-slot' => 'form-section',
]) }}>
    @if($formSectionIsFieldset)<legend class="float-left w-full">@endif
    <h2 id="{{ $formSectionId }}-titulo" class="flex min-w-0 items-start gap-2.5 text-lg font-semibold text-foreground">
        @if(filled($number))
            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground tabular-nums" aria-hidden="true">{{ $number }}</span>
            <span class="sr-only">Etapa {{ $number }}:</span>
        @endif
        <span class="min-w-0 pt-px">{{ $title }}</span>
    </h2>
    @if($formSectionIsFieldset)</legend>@endif
    @if($formSectionHasDescription)
        <p id="{{ $formSectionId }}-descricao" class="clear-left pt-1 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    @if($formSectionHasActions)
        <div class="clear-left flex flex-wrap items-center gap-2 pt-3" data-slot="form-section-actions">{{ $actions }}</div>
    @endif
    <div class="clear-left space-y-4 pt-4" data-slot="form-section-content">
        {{ $slot }}
    </div>
</{{ $formSectionTag }}>
