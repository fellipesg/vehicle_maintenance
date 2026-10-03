{{--
    Card de veículo para listas (Meus veículos, Estoque): o card inteiro é um link para a ficha.

    Capa inteira (x-vehicle-cover variant="card", object-contain; nunca cortada), marca e modelo
    (o link, esticado sobre o card), ano e cor, placa em mono, km atual e o resumo de procedência
    em pontos ("2 com selo · 1 declarada" e um ponto por manutenção, sem link: o card já é o link).

    Props:
    - vehicle (obrigatório). Para não consultar por card: loadCount de maintenances e de
      'maintenances as verified_maintenances_count' (whereNotNull('verified_at')) e o eager load de
      provenanceStripMaintenances.
    - href (obrigatório): a ficha do veículo no portal.
    - as: article (o padrão) | li | div.
    - heading-level: h2 | h3 (o padrão) | h4.
    - add-cover-url: sem capa, mostra "Adicionar capa" (link próprio, clicável por cima do card).

    Slots:
    - badges: ao lado do título (ex.: <x-ui.badge variant="info">Consignação</x-ui.badge>).
    - actions: ações secundárias no rodapé (ex.: "Editar"), clicáveis por cima do link do card.
    - padrão: conteúdo extra abaixo da procedência.

    Ex.: <x-vehicle.card :vehicle="$vehicle" :href="route('garage.vehicles.show', $vehicle)" as="li" />
--}}
@props([
    'vehicle',
    'href' => null,
    'as' => 'article',
    'headingLevel' => 'h3',
    'addCoverUrl' => null,
])

@php
    \App\Support\UiProps::required('x-vehicle.card', 'href', $href, 'O card inteiro é o link para a ficha do veículo.');

    $vehicleCardTag = \App\Support\UiProps::oneOf('x-vehicle.card', 'as', $as, ['article', 'li', 'div'], 'article');
    $vehicleCardHeadingTag = \App\Support\UiProps::oneOf('x-vehicle.card', 'heading-level', $headingLevel, ['h2', 'h3', 'h4'], 'h3');
    $vehicleCardTitle = trim($vehicle->brand.' '.$vehicle->model);
    $vehicleCardMeta = collect([$vehicle->year, $vehicle->color])->filter(fn (mixed $part): bool => filled($part))->implode(' · ');
    $vehicleCardKm = $vehicle->current_kilometers !== null ? number_format((int) $vehicle->current_kilometers, 0, ',', '.').' km' : null;
    $vehicleCardTotal = $vehicle->maintenances_count
        ?? ($vehicle->relationLoaded('provenanceStripMaintenances') ? $vehicle->provenanceStripMaintenances->count() : null)
        ?? ($vehicle->relationLoaded('maintenances') ? $vehicle->maintenances->count() : null)
        ?? $vehicle->maintenances()->count();
    $vehicleCardTitleId = 'veiculo-'.$vehicle->id.'-titulo';
    $vehicleCardHasBadges = isset($badges) && ! \App\Support\UiProps::isBlank($badges);
    $vehicleCardHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
@endphp

<{{ $vehicleCardTag }} {{ $attributes->class([
    'group relative flex flex-col overflow-hidden rounded-card border border-border bg-surface text-foreground shadow-sm',
    'transition-[border-color,box-shadow,translate] duration-fast ease-smooth-out motion-reduce:transition-none hover:border-accent-border hover:shadow-md motion-safe:hover:-translate-y-0.5',
])->merge([
    'aria-labelledby' => $vehicleCardTag === 'article' ? $vehicleCardTitleId : null,
    'data-slot' => 'vehicle-card',
    'data-vehicle-id' => (string) $vehicle->id,
]) }}>
    <x-vehicle-cover :vehicle="$vehicle" variant="card" :add-cover-url="$addCoverUrl" />
    <div class="flex flex-1 flex-col gap-3 p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <{{ $vehicleCardHeadingTag }} id="{{ $vehicleCardTitleId }}" class="text-base leading-6 font-semibold text-foreground">
                    <a href="{{ $href }}" data-slot="vehicle-card-link" class="after:absolute after:inset-0 after:z-[1] after:rounded-card focus-visible:outline-hidden focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-ring">{{ $vehicleCardTitle }}</a>
                </{{ $vehicleCardHeadingTag }}>
                @if ($vehicleCardMeta !== '')
                    <p class="text-sm text-muted-foreground">{{ $vehicleCardMeta }}</p>
                @endif
            </div>
            @if ($vehicleCardHasBadges)
                <div class="flex shrink-0 flex-wrap justify-end gap-1.5">{{ $badges }}</div>
            @endif
        </div>

        @if (filled($vehicle->license_plate) || $vehicleCardKm !== null)
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                @if (filled($vehicle->license_plate))
                    <span class="rounded-md border border-border-strong px-1.5 py-px font-mono text-xs font-semibold tracking-wider text-foreground"><span class="sr-only">Placa </span>{{ $vehicle->license_plate }}</span>
                @endif
                @if (filled($vehicle->license_plate) && $vehicleCardKm !== null)
                    <span aria-hidden="true">·</span>
                @endif
                @if ($vehicleCardKm !== null)
                    <span class="tabular-nums"><span class="sr-only">Quilometragem atual: </span>{{ $vehicleCardKm }}</span>
                @endif
            </p>
        @endif

        @if ((int) $vehicleCardTotal > 0)
            <x-provenance-strip :vehicle="$vehicle" :dot-href="false" class="mb-0" label="Procedência das manutenções deste veículo, da mais antiga à mais recente" />
        @else
            <p class="text-sm text-muted-foreground">Sem manutenções registradas</p>
        @endif

        @if ($slot->hasActualContent())
            <div class="text-sm">{{ $slot }}</div>
        @endif

        @if ($vehicleCardHasActions)
            <div class="relative z-10 mt-auto flex flex-wrap items-center gap-2 pt-1">{{ $actions }}</div>
        @endif
    </div>
</{{ $vehicleCardTag }}>
