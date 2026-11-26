{{--
    Laudos de vistoria cautelar emitidos pela oficina. A oficina alcança o próprio laudo por aqui,
    mesmo sem vínculo com o veículo — a listagem do veículo fecha por tenant para quem não passa em
    viewFullHistory (.ai/rules/inspections.md).

    Sem a funcionalidade na conta ($mayIssue falso) a tela não oferece o que ela não deixa fazer: o
    botão "Nova vistoria" sai, como manda .ai/rules/features.md. Os laudos já emitidos continuam
    visíveis — desligar a funcionalidade não apaga laudo nenhum.
--}}
@extends('layouts.app')

@section('title', 'Vistorias')

@section('content')
    <x-ui.container padded>
        <x-ui.page-header
            title="Vistorias cautelares"
            description="Os laudos que a sua oficina assinou. Cada um tem um código de conferência que o comprador confere."
        >
            @if($workshop && $mayIssue)
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('workshop.inspections.create')">Nova vistoria</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" title="Cadastre sua oficina para emitir laudos"
                              description="O nome da oficina fica no laudo: é ela que assina o que a vistoria encontrou.">
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @elseif($inspections->isEmpty())
            @if($mayIssue)
                <x-ui.empty-state icon="document-text" title="Nenhum laudo emitido ainda"
                                  description="Comece pela placa ou pelo chassi do veículo: o laudo registra o veredito, o que foi examinado e o hodômetro lido." data-inspections-empty="all">
                    <x-slot:actions>
                        <x-ui.button icon="plus" :href="route('workshop.inspections.create')">Nova vistoria</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="document-text" title="A vistoria cautelar não está ativa nesta conta"
                                  description="Emitir laudo é liberado conta a conta pela equipe do RevisaLog, porque o laudo afirma a condição de um carro de terceiro." data-inspections-empty="feature-off">
                    <x-slot:actions>
                        <x-ui.button :href="route('contact.show')">Falar com a equipe</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @endif
        @else
            <div class="space-y-4">
                <p class="text-sm text-muted-foreground" data-inspections-total>
                    {{ $inspections->total() === 1 ? '1 laudo' : number_format($inspections->total(), 0, ',', '.').' laudos' }}
                </p>

                <x-ui.table caption="Vistorias cautelares" stack data-workshop-inspections-table>
                    <x-slot:head>
                        <tr>
                            <th>Data</th>
                            <th>Veículo</th>
                            <th>Resultado</th>
                            <th>Validade</th>
                            <th>Código</th>
                        </tr>
                    </x-slot:head>
                    @foreach($inspections as $inspection)
                        @php
                            $rowVehicle = $inspection->vehicle;
                            $rowVerdict = $inspection->verdict;
                            // A placa atual é do mascaramento, não de ter emitido o laudo: emitir
                            // não cria OS. Vínculo de oficina **ou** posse de hoje, a mesma conta do
                            // detalhe (ver o docblock do InspectionController).
                            $rowShowsPlate = $rowVehicle && in_array($rowVehicle->id, $fullVehicleIds, true);
                        @endphp
                        <tr data-inspection-row="{{ $inspection->id }}">
                            <td class="whitespace-nowrap">
                                <span class="flex flex-col items-end md:items-start">
                                    <time datetime="{{ $inspection->inspected_at->format('Y-m-d') }}" class="tabular-nums">{{ $inspection->inspected_at->format('d/m/Y') }}</time>
                                    @if($inspection->odometer_km !== null)
                                        <span class="text-xs text-muted-foreground tabular-nums">{{ number_format((int) $inspection->odometer_km, 0, ',', '.') }} km</span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if($rowVehicle)
                                    <span class="flex flex-col items-end md:items-start">
                                        <span class="text-foreground">{{ trim($rowVehicle->brand.' '.$rowVehicle->model) }}</span>
                                        @if($rowShowsPlate && filled($rowVehicle->license_plate))
                                            <span class="font-mono text-xs tracking-wider text-muted-foreground"><span class="sr-only">Placa </span>{{ $rowVehicle->license_plate }}</span>
                                        @endif
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <th scope="row" class="font-medium">
                                <a href="{{ route('workshop.inspections.show', $inspection) }}" class="link">{{ $rowVerdict->label() }}</a>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal">
                                    @if($inspection->failingFindings() !== [])
                                        <span class="text-muted-foreground">{{ count($inspection->failingFindings()) === 1 ? '1 item reprovado' : count($inspection->failingFindings()).' itens reprovados' }}</span>
                                    @endif
                                    @if($inspection->isDisputed())
                                        <x-ui.badge variant="warning" size="sm">Contestado pelo proprietário</x-ui.badge>
                                    @endif
                                    @if($inspection->isHiddenFromPublic())
                                        <x-ui.badge variant="neutral" size="sm" icon="eye-slash">Fora da consulta pública</x-ui.badge>
                                    @endif
                                </span>
                            </th>
                            <td class="whitespace-nowrap">
                                @if($inspection->valid_until === null)
                                    <span class="text-muted-foreground">Sem validade</span>
                                @elseif($inspection->isExpired())
                                    <x-ui.badge variant="neutral" size="sm">Vencido em {{ $inspection->valid_until->format('d/m/Y') }}</x-ui.badge>
                                @else
                                    <time datetime="{{ $inspection->valid_until->format('Y-m-d') }}" class="tabular-nums">{{ $inspection->valid_until->format('d/m/Y') }}</time>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="font-mono text-xs tracking-wider text-foreground">{{ $inspection->verification_code }}</span>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>

                @if($inspections->hasPages())
                    <div class="pt-2">{{ $inspections->links() }}</div>
                @endif
            </div>
        @endif
    </x-ui.container>
@endsection
