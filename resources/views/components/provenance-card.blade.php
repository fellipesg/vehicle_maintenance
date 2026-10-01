{{--
    Card de uma manutenção com a procedência (contrato de .ai/rules/theme.md): trilho sólido e
    marcador com logo para o Selo da oficina; trilho tracejado e anel para as declaradas.

    Linha 1: o serviço (título) e a data do serviço (dd/mm/aaaa, tabular-nums).
    Linha 2: veículo (modelo + placa em mono, com show-vehicle), km, categoria e, nas declaradas, a
    oficina informada por quem declarou (com Selo da oficina, a oficina já está na linha 3).
    Linha 3: a procedência em texto ("Selo da oficina · Oficina X", "Declarada pelo proprietário").
    Linha 4: a evidência (código do selo; nas declaradas, NF-e e fotos), no lugar de "não verificada".

    Props:
    - maintenance (obrigatório). Carregue vehicle (show-vehicle), workshop, verifiedWorkshop e user;
      invoices/photos (ou withCount) para a evidência sem consulta extra.
    - href: o card inteiro vira link (o título é o link, esticado sobre o card).
    - as: div (o padrão, com mb-3 como antes) | li | article.
    - heading-level: p (o padrão) | h2 | h3 | h4, para a lista entrar na navegação por títulos.
    - show-vehicle: mostra "Marca Modelo" e a placa (listas com vários veículos).
    - vehicle-href: link do veículo (fica clicável por cima do link do card).
    - show-date / show-km / show-category / show-workshop: ligam as partes da linha 1 e 2 (padrão:
      todas). show-workshop só vale para as declaradas.
    - anchor: id="manutencao-{id}" (o padrão), destino dos pontos da faixa e de "Ir para a mais
      recente". Passe :anchor="false" quando a mesma manutenção aparece duas vezes na página.
    - expandable: sem href, mostra <details> "Ver serviços" com os itens e as fotos do depois
      (busca pública, onde não há página de detalhe). Usa só as relações já carregadas.

    O card leva data-maintenance-card e data-verified="1|0" para o filtro de procedência no lugar
    (resources/js/provenance-ui.js).

    Ex.: <x-provenance-card :maintenance="$maintenance" as="li" heading-level="h3" show-vehicle
             :href="route('user.maintenances.show', $maintenance)" />
--}}
@props([
    'maintenance',
    'href' => null,
    'as' => 'div',
    'headingLevel' => 'p',
    'showVehicle' => false,
    'vehicleHref' => null,
    'showDate' => true,
    'showKm' => true,
    'showCategory' => true,
    'showWorkshop' => true,
    'anchor' => true,
    'expandable' => false,
])

@php
    $cardTag = \App\Support\UiProps::oneOf('x-provenance-card', 'as', $as, ['div', 'li', 'article'], 'div');
    $cardHeadingTag = \App\Support\UiProps::oneOf('x-provenance-card', 'heading-level', $headingLevel, ['p', 'h2', 'h3', 'h4'], 'p');
    $verified = $maintenance->isVerified();
    $rootClass = $verified ? 'prov-verified' : 'prov-declared';
    $railClass = $verified ? 'prov-rail' : 'prov-rail prov-rail--declared';
    $cardClass = $verified ? 'prov-card' : 'prov-card prov-card--declared';
    $titleClass = $verified ? 'font-semibold text-foreground' : 'font-medium text-foreground';
    $cardIsLink = filled($href);

    // Sem eager load, busca o veículo direto (preventLazyLoading barra o acesso lazy em listas).
    $vehicle = ! $showVehicle ? null : ($maintenance->relationLoaded('vehicle') ? $maintenance->vehicle : $maintenance->vehicle()->first());
    $vehicleName = $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : null;
    $vehiclePlate = $vehicle?->license_plate;
    $serviceDate = $showDate ? $maintenance->maintenance_date : null;
    $kilometers = $showKm && $maintenance->kilometers !== null ? number_format((int) $maintenance->kilometers, 0, ',', '.').' km' : null;
    $categoryLabel = $showCategory ? \App\Enums\ServiceCategory::labelFor($maintenance->service_category) : null;
    // Declarada: a oficina que quem declarou informou (cadastrada ou texto livre). LIVE-07.
    $declaredWorkshopName = $showWorkshop && ! $verified ? $maintenance->displayWorkshopName() : null;

    $invoiceCount = $maintenance->invoices_count
        ?? ($maintenance->relationLoaded('invoices') ? $maintenance->invoices->count() : null);
    if ($invoiceCount === null && ! $verified && $maintenance->exists) {
        $invoiceCount = $maintenance->invoices()->count();
    }
    $photoCount = $maintenance->photos_count
        ?? ($maintenance->relationLoaded('photos') ? $maintenance->photos->count() : null);

    $evidence = [];
    if ($verified && filled($maintenance->verification_code)) {
        $evidence[] = 'Código '.$maintenance->verification_code;
    }
    if ((int) $invoiceCount > 0) {
        $evidence[] = (int) $invoiceCount === 1 ? 'NF-e anexada' : $invoiceCount.' NF-e anexadas';
    }
    if ((int) $photoCount > 0) {
        $evidence[] = (int) $photoCount === 1 ? '1 foto' : $photoCount.' fotos';
    }
    if (! $verified && $evidence === []) {
        $evidence[] = 'Sem nota fiscal nem fotos';
    }

    $details = [];
    if ($vehicleName !== null) {
        $details[] = 'vehicle';
    }
    if ($kilometers !== null) {
        $details[] = 'km';
    }
    if ($categoryLabel !== null) {
        $details[] = 'category';
    }
    if (filled($declaredWorkshopName)) {
        $details[] = 'workshop';
    }

    $expandItems = $expandable && ! $cardIsLink && $maintenance->relationLoaded('items') ? $maintenance->items : collect();
    $expandPhotos = $expandable && ! $cardIsLink && $maintenance->relationLoaded('photos') ? $maintenance->photos : collect();
    $cardExpands = $expandItems->isNotEmpty() || $expandPhotos->isNotEmpty();
@endphp

<{{ $cardTag }} {{ $attributes->class([
    $cardClass,
    $rootClass,
    'mb-3' => $cardTag === 'div',
    'relative transition-[box-shadow,opacity] duration-fast ease-smooth-out motion-reduce:transition-none hover:shadow-md' => $cardIsLink,
])->merge([
    'id' => $anchor ? 'manutencao-'.$maintenance->id : null,
    'data-maintenance-card' => (string) $maintenance->id,
    'data-verified' => $verified ? '1' : '0',
]) }}>
    <div class="{{ $railClass }} {{ $rootClass }}" aria-hidden="true"></div>
    <x-provenance-marker :maintenance="$maintenance" />
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
            <{{ $cardHeadingTag }} class="{{ $titleClass }} min-w-0 text-base leading-6" data-slot="provenance-card-title">
                @if ($cardIsLink)
                    <a href="{{ $href }}" class="after:absolute after:inset-0 after:rounded-lg hover:underline focus-visible:outline-hidden focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-ring">{{ $maintenance->maintenance_type }}</a>
                @else
                    {{ $maintenance->maintenance_type }}
                @endif
            </{{ $cardHeadingTag }}>
            @if ($serviceDate)
                <p class="shrink-0 text-sm text-muted-foreground tabular-nums"><time datetime="{{ $serviceDate->format('Y-m-d') }}">{{ $serviceDate->format('d/m/Y') }}</time></p>
            @endif
        </div>

        @if ($details !== [])
            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground" data-slot="provenance-card-details">
                @foreach ($details as $detailIndex => $detail)
                    @if ($detailIndex > 0)
                        <span aria-hidden="true">·</span>
                    @endif
                    @if ($detail === 'vehicle')
                        <span class="inline-flex flex-wrap items-center gap-x-2">
                            @if (filled($vehicleHref))
                                <a href="{{ $vehicleHref }}" class="link relative z-10 font-medium">{{ $vehicleName }}</a>
                            @else
                                <span class="font-medium text-foreground">{{ $vehicleName }}</span>
                            @endif
                            @if (filled($vehiclePlate))
                                <span class="rounded-md border border-border-strong bg-surface px-1.5 py-px font-mono text-xs tracking-wider text-foreground"><span class="sr-only">Placa </span>{{ $vehiclePlate }}</span>
                            @endif
                        </span>
                    @elseif ($detail === 'km')
                        <span class="tabular-nums">{{ $kilometers }}</span>
                    @elseif ($detail === 'workshop')
                        <span class="inline-flex min-w-0 items-center gap-1" data-slot="provenance-card-workshop">
                            <x-ui.icon name="wrench-screwdriver" class="size-4 shrink-0" />
                            <span class="sr-only">Oficina: </span>{{ $declaredWorkshopName }}
                        </span>
                    @else
                        <x-ui.badge size="sm"><span class="sr-only">Categoria: </span>{{ $categoryLabel }}</x-ui.badge>
                    @endif
                @endforeach
            </p>
        @endif

        <p class="mt-1 text-sm font-medium text-[color:var(--prov-ink)]" data-slot="provenance-card-label">{{ $maintenance->provenance_card_label }}</p>
        @if ($evidence !== [])
            <p class="text-xs text-muted-foreground" data-slot="provenance-card-evidence">
                @foreach ($evidence as $evidenceIndex => $evidenceText)
                    @if ($evidenceIndex > 0)
                        <span aria-hidden="true"> · </span>
                    @endif
                    <span @class(['font-mono' => str_starts_with($evidenceText, 'Código ')])>{{ $evidenceText }}</span>
                @endforeach
            </p>
        @endif

        @if ($cardExpands)
            <details class="group mt-3" data-slot="provenance-card-expand">
                <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-1 rounded-control text-sm font-medium text-link hover:text-link-hover [&::-webkit-details-marker]:hidden">
                    <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-fast motion-reduce:transition-none group-open:rotate-180" />
                    Ver serviços
                    @if ($expandItems->isNotEmpty())
                        <span class="tabular-nums text-muted-foreground">({{ $expandItems->count() }})</span>
                    @endif
                </summary>
                @if ($expandItems->isNotEmpty())
                    <ul role="list" class="mt-2 divide-y divide-border rounded-control border border-border bg-surface text-sm">
                        @foreach ($expandItems as $expandItem)
                            <li class="flex items-baseline justify-between gap-4 px-3 py-2">
                                <span class="min-w-0 text-foreground">{{ $expandItem->name }}</span>
                                <span class="shrink-0 text-muted-foreground tabular-nums">{{ number_format((float) $expandItem->quantity, 0, ',', '.') }}x</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($expandPhotos->isNotEmpty())
                    <ul role="list" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4" aria-label="Fotos do serviço">
                        @foreach ($expandPhotos as $expandPhoto)
                            <li>
                                <a href="{{ $expandPhoto->url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-control border border-border bg-surface-muted">
                                    <img src="{{ $expandPhoto->url }}" alt="Foto do serviço {{ $loop->iteration }}" loading="lazy" class="h-20 w-full object-contain">
                                    <span class="sr-only">(abre em nova aba)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </details>
        @endif
    </div>
</{{ $cardTag }}>
