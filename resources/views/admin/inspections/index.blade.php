@extends('layouts.admin')

@section('title', 'Vistorias')

{{--
    Vistorias cautelares da plataforma. É a única tela que mostra o laudo suprimido: quem tirou de
    circulação precisa continuar vendo o que tirou para poder devolver (.ai/rules/admin.md).

    Suprimir e contestar são coisas diferentes e aparecem separadas de propósito: a contestação é do
    proprietário e só marca; a supressão é da equipe e tira o laudo do histórico, da ficha e da
    conferência pública.
--}}
@php
    $adminBreadcrumbs = [['Frota'], ['Vistorias']];
    $hasSearch = $search !== '';
    $hasFilter = $hasSearch || $onlyDisputed || $onlyHidden;
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $filterQuery = fn (array $overrides): array => array_filter(array_merge([
        'q' => $search !== '' ? $search : null,
        'contestadas' => $onlyDisputed ? '1' : null,
        'suprimidas' => $onlyHidden ? '1' : null,
    ], $overrides), fn ($value) => $value !== null);
@endphp

@section('content')
    <x-ui.page-header
        title="Vistorias"
        description="Laudos de vistoria cautelar emitidos pelas oficinas, com o que o proprietário contestou e o que a equipe suprimiu."
    >
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.inspections.index') }}" class="flex w-full gap-2 sm:w-auto" role="search" aria-label="Buscar laudo" data-submit-busy="off">
                @if($onlyDisputed)
                    <input type="hidden" name="contestadas" value="1">
                @endif
                @if($onlyHidden)
                    <input type="hidden" name="suprimidas" value="1">
                @endif
                <label for="admin-inspection-search" class="sr-only">Buscar laudo por código, placa, chassi ou emissor</label>
                <x-ui.input
                    type="search"
                    id="admin-inspection-search"
                    name="q"
                    :value="$search"
                    placeholder="Código, placa ou emissor"
                    leading-icon="magnifying-glass"
                    autocomplete="off"
                    class="min-w-0 flex-1 sm:w-72"
                />
                <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted-foreground">
        <span>{{ $formatCount($inspections->total()) }} {{ $inspections->total() === 1 ? 'laudo' : 'laudos' }}</span>

        <x-ui.link
            :href="route('admin.inspections.index', $filterQuery(['contestadas' => $onlyDisputed ? null : '1']))"
            :aria-pressed="$onlyDisputed ? 'true' : 'false'"
            data-admin-filter="contestadas"
        >{{ $onlyDisputed ? 'Mostrar todos' : 'Só contestados ('.$formatCount($disputedCount).')' }}</x-ui.link>

        <x-ui.link
            :href="route('admin.inspections.index', $filterQuery(['suprimidas' => $onlyHidden ? null : '1']))"
            :aria-pressed="$onlyHidden ? 'true' : 'false'"
            data-admin-filter="suprimidas"
        >{{ $onlyHidden ? 'Mostrar todos' : 'Só suprimidos ('.$formatCount($hiddenCount).')' }}</x-ui.link>

        @if($hasFilter)
            <x-ui.link :href="route('admin.inspections.index')" icon="x-mark">Limpar filtros</x-ui.link>
        @endif
    </div>

    <x-ui.table caption="Vistorias cautelares da plataforma" stack>
        <x-slot:head>
            <tr>
                <th class="min-w-40">Veículo</th>
                <th>Laudo</th>
                <th class="min-w-40">Emissor</th>
                <th>Data</th>
                <th class="min-w-64">Situação</th>
            </tr>
        </x-slot:head>

        @foreach($inspections as $inspection)
            @php
                $rowVehicle = $inspection->vehicle;
                $rowVehicleName = $rowVehicle === null ? 'Veículo removido' : trim($rowVehicle->brand.' '.$rowVehicle->model);
            @endphp
            <tr>
                <th scope="row" class="font-medium">
                    @if($rowVehicle)
                        <x-ui.link :href="route('admin.vehicles.show', $rowVehicle)">{{ $rowVehicleName }}</x-ui.link>
                        <span class="block font-mono text-xs font-normal tracking-wider text-muted-foreground">{{ $rowVehicle->license_plate ?? $rowVehicle->chassis }}</span>
                    @else
                        <span class="text-muted-foreground">{{ $rowVehicleName }}</span>
                    @endif
                </th>
                <td>
                    <x-ui.badge :variant="$inspection->verdict?->badgeVariant() ?? 'neutral'">{{ $inspection->verdict?->label() ?? 'Sem veredito' }}</x-ui.badge>
                    @if($inspection->verification_code)
                        <span class="mt-1 block font-mono text-xs tracking-wider text-muted-foreground">{{ $inspection->verification_code }}</span>
                    @endif
                </td>
                <td class="text-muted-foreground">{{ $inspection->provenance_sublabel }}</td>
                <td class="whitespace-nowrap">
                    {{ $inspection->inspected_at?->format('d/m/Y') ?? '—' }}
                    @if($inspection->isExpired())
                        <span class="block text-xs text-muted-foreground">Vencido</span>
                    @endif
                </td>
                <td>
                    @if($inspection->isDisputed())
                        <p data-admin-inspection-disputed="{{ $inspection->id }}">
                            <x-ui.badge variant="warning" size="sm">Contestado pelo proprietário</x-ui.badge>
                            <span class="mt-1 block text-xs text-muted-foreground">
                                @if($inspection->disputedBy){{ $inspection->disputedBy->name }}@endif
                                @if($inspection->owner_disputed_at) · {{ $inspection->owner_disputed_at->format('d/m/Y H:i') }}@endif
                            </span>
                            @if(filled($inspection->owner_dispute_reason))
                                <span class="mt-1 block text-xs text-muted-foreground">{{ $inspection->owner_dispute_reason }}</span>
                            @endif
                        </p>
                    @endif

                    @if($inspection->isHiddenFromPublic())
                        <p class="mt-1" data-admin-inspection-hidden="{{ $inspection->id }}">
                            <x-ui.badge variant="neutral" size="sm">Suprimido</x-ui.badge>
                            <span class="mt-1 block text-xs text-muted-foreground">
                                @if($inspection->hiddenBy){{ $inspection->hiddenBy->name }}@endif
                                @if($inspection->hidden_from_public_at) · {{ $inspection->hidden_from_public_at->format('d/m/Y H:i') }}@endif
                            </span>
                            @if(filled($inspection->hidden_reason))
                                <span class="mt-1 block text-xs text-muted-foreground">{{ $inspection->hidden_reason }}</span>
                            @endif
                        </p>
                    @endif

                    <x-admin.visibility-trail
                        :events="$inspection->visibilityEvents"
                        :label="'do laudo '.$inspection->verification_code"
                    />

                    <span class="mt-2 block">
                        @if($inspection->isHiddenFromPublic())
                            <form method="POST" action="{{ route('admin.inspections.restore', $inspection) }}">
                                @csrf
                                <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Publicando…">Devolver ao histórico</x-ui.button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.inspections.hide', $inspection) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <x-ui.input
                                    name="hidden_reason"
                                    placeholder="Motivo (opcional)"
                                    maxlength="500"
                                    class="text-sm"
                                    :aria-label="'Motivo para suprimir o laudo '.$inspection->verification_code"
                                />
                                <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Suprimindo…">Suprimir</x-ui.button>
                            </form>
                        @endif
                    </span>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            @if($hasFilter)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    title="Nenhum laudo encontrado"
                    description="Confira o código, a placa ou o emissor e busque de novo."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.inspections.index')">Limpar filtros</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="clipboard-document"
                    title="Nenhuma vistoria emitida"
                    description="Os laudos aparecem aqui quando uma oficina com a funcionalidade ligada emite a primeira vistoria cautelar."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                />
            @endif
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $inspections->links() }}
    </div>
@endsection
