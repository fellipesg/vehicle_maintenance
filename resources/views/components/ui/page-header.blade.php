{{--
    Cabeçalho da página: trilha, título (o único H1 da página), descrição e ações.

    Props:
    - title: obrigatório. Sem emoji, em caixa de frase e igual ao @section('title') da view.
      Também aceita <x-slot:title>.
    - description: uma frase sobre a página (opcional). Também aceita <x-slot:description>.
    - eyebrow: contexto curto acima do título, só quando agrega ("Selo da oficina", "Blog").
      Nunca repete o nome do portal.
    - breadcrumbs: itens para x-ui.breadcrumb (mesmo formato de lá).

    Slots:
    - breadcrumb: trilha própria no lugar da prop breadcrumbs.
    - actions: botões da página, com o primário por último (fica à direita). No celular as ações
      quebram linha e crescem para ocupar a largura.
    - padrão: conteúdo extra abaixo da descrição (ex.: badges de status).

    Ex.: <x-ui.page-header title="Estoque" description="Veículos da sua loja, com o histórico de procedência."
             :breadcrumbs="[['Início', route('garage.dashboard')], ['Estoque']]">
             <x-slot:actions><x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button></x-slot:actions>
         </x-ui.page-header>
--}}
@props([
    'title' => null,
    'description' => null,
    'eyebrow' => null,
    'breadcrumbs' => [],
])
@php
    \App\Support\UiProps::required('x-ui.page-header', 'title', $title, 'O page-header desenha o H1 da página.');

    $pageHeaderHasBreadcrumbSlot = isset($breadcrumb) && ! \App\Support\UiProps::isBlank($breadcrumb);
    $pageHeaderHasDescription = ! \App\Support\UiProps::isBlank($description);
    $pageHeaderHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp
<header {{ $attributes->class(['mb-6 space-y-3'])->merge(['data-slot' => 'page-header']) }}>
    @if($pageHeaderHasBreadcrumbSlot)
        {{ $breadcrumb }}
    @elseif(! empty($breadcrumbs))
        <x-ui.breadcrumb :items="$breadcrumbs" />
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            @if(filled($eyebrow))
                <p data-slot="page-header-eyebrow" class="mb-1 text-xs font-semibold tracking-[0.2em] text-accent-foreground uppercase">{{ $eyebrow }}</p>
            @endif
            <h1 data-slot="page-header-title" class="text-2xl font-bold tracking-tight text-balance text-foreground sm:text-3xl">{{ $title }}</h1>
            @if($pageHeaderHasDescription)
                <p data-slot="page-header-description" class="mt-1 max-w-prose text-sm text-muted-foreground">{{ $description }}</p>
            @endif
            @if($slot->hasActualContent())
                <div class="mt-3">{{ $slot }}</div>
            @endif
        </div>
        @if($pageHeaderHasActions)
            <div data-slot="page-header-actions" class="flex flex-wrap items-center gap-2 max-sm:*:grow sm:shrink-0 sm:justify-end">{{ $actions }}</div>
        @endif
    </div>
</header>
