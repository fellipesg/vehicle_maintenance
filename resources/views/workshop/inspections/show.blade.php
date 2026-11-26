{{--
    Laudo de vistoria cautelar como a oficina emissora o vê: inteiro, sempre — foi ela que escreveu a
    prosa e é ela quem corrige, mesmo sem vínculo com o veículo e mesmo com o laudo suprimido pela
    equipe (VehicleInspection::showsFullReportTo).

    O PDF nunca sai pelo caminho do storage: o link é route('inspections.report'), atrás da
    VehicleInspectionPolicy::viewReport (.ai/rules/views.md).

    O laudo inteiro é sempre do emissor; o **veículo** de terceiro não é. A placa de hoje depende de
    $showsFullVehicle, o mesmo mascaramento da etapa 1 (.ai/rules/public-lookup.md): sem vínculo ela
    não sai, senão emitir um laudo com uma placa antiga viraria oráculo de rotação de placa.

    $viewerIsWorkshop existe porque esta tela também abre para quem chegou pelo ramo de tenant da
    VehicleInspectionPolicy::view (conta de oficina ainda sem oficina cadastrada): a explicação da
    placa oculta não pode falar de "a sua oficina" para quem não tem uma.
--}}
@extends('layouts.app')

@section('title', 'Laudo '.$inspection->verification_code)

@php
    use App\Enums\InspectionFinding;
    use App\Support\Inspection\InspectionFindings;

    $vehicle = $inspection->vehicle;
    $vehicleName = $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : 'Veículo não identificado';
    $findings = InspectionFindings::normalize($inspection->findings);
@endphp

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header
            :title="'Laudo '.$inspection->verification_code"
            :description="$vehicleName.' · vistoria de '.$inspection->inspected_at->format('d/m/Y')"
            :breadcrumbs="[['Vistorias', route('workshop.inspections.index')], [$inspection->verification_code]]"
        >
            @if($canManage)
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="pencil-square" :href="route('workshop.inspections.edit', $inspection)">Editar</x-ui.button>
                    <form
                        method="POST"
                        action="{{ route('workshop.inspections.destroy', $inspection) }}"
                        data-confirm="O laudo {{ $inspection->verification_code }} sai do histórico do veículo e o código deixa de responder. Não é possível desfazer."
                        data-confirm-title="Excluir o laudo?"
                        data-confirm-action-label="Excluir laudo"
                        data-confirm-variant="danger"
                    >
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="ghost" icon="trash" class="text-danger hover:bg-danger-soft">Excluir</x-ui.button>
                    </form>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        <div class="space-y-6">
            @if($inspection->isDisputed())
                <x-ui.alert variant="warning" title="Contestado pelo proprietário" data-inspection-disputed>
                    O proprietário registrou que não reconhece este laudo. Ele continua no histórico, com o aviso: quem assinou responde pelo que afirmou.
                </x-ui.alert>
            @endif

            @if($inspection->isHiddenFromPublic())
                <x-ui.alert variant="info" title="Fora da consulta pública" data-inspection-hidden>
                    A equipe do RevisaLog tirou este laudo da consulta pública. Ele continua aqui, para a sua oficina.
                </x-ui.alert>
            @endif

            <x-ui.card as="section" title="Resultado" heading-level="h2" data-inspection-verdict>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge :variant="$inspection->verdict->badgeVariant()" size="lg">{{ $inspection->verdict->label() }}</x-ui.badge>
                    @if($inspection->isExpired())
                        <x-ui.badge variant="neutral">Vencido em {{ $inspection->valid_until->format('d/m/Y') }}</x-ui.badge>
                    @endif
                </div>

                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Veículo</dt>
                        <dd class="mt-0.5 text-foreground">
                            {{ $vehicleName }}
                            @if($vehicle && filled($vehicle->license_plate))
                                @if($showsFullVehicle)
                                    <span class="font-mono text-xs tracking-wider text-muted-foreground"><span class="sr-only">Placa </span>{{ $vehicle->license_plate }}</span>
                                @elseif($viewerIsWorkshop)
                                    <span class="text-xs text-muted-foreground" data-vehicle-plate-withheld>Placa não exibida: a sua oficina não tem OS deste veículo.</span>
                                @else
                                    <span class="text-xs text-muted-foreground" data-vehicle-plate-withheld>Placa não exibida: o veículo não está na sua conta.</span>
                                @endif
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Data da vistoria</dt>
                        <dd class="mt-0.5 text-foreground tabular-nums">
                            <time datetime="{{ $inspection->inspected_at->format('Y-m-d') }}">{{ $inspection->inspected_at->format('d/m/Y') }}</time>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Hodômetro lido</dt>
                        <dd class="mt-0.5 text-foreground tabular-nums" data-inspection-odometer>
                            @if($inspection->odometer_km === null)
                                <span class="text-muted-foreground ">Não informado</span>
                            @else
                                {{ number_format((int) $inspection->odometer_km, 0, ',', '.') }} km
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Validade</dt>
                        <dd class="mt-0.5 text-foreground tabular-nums">
                            @if($inspection->valid_until === null)
                                <span class="text-muted-foreground">Sem validade</span>
                            @else
                                <time datetime="{{ $inspection->valid_until->format('Y-m-d') }}">{{ $inspection->valid_until->format('d/m/Y') }}</time>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Emitido por</dt>
                        <dd class="mt-0.5 text-foreground">{{ $inspection->provenance_sublabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Código de conferência</dt>
                        <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm tracking-wider text-foreground">{{ $inspection->verification_code }}</span>
                            <x-ui.copy-button :value="$inspection->verification_code" label="Copiar o código do laudo" copied-label="Código copiado" size="sm" />
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card as="section" title="O que foi examinado" heading-level="h2"
                       description="Item por item, na ordem do laudo. “Não avaliado” é o que a vistoria não olhou." data-inspection-findings>
                <ul role="list" class="divide-y divide-border">
                    @foreach(InspectionFinding::cases() as $axis)
                        @php
                            $status = InspectionFindings::statusOf($findings, $axis);
                            $note = InspectionFindings::noteOf($findings, $axis);
                        @endphp
                        <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4" data-finding="{{ $axis->value }}">
                            <div class="min-w-0">
                                <p class="font-medium text-foreground">{{ $axis->label() }}</p>
                                @if(filled($note))
                                    <p class="mt-0.5 max-w-prose text-sm text-muted-foreground">{{ $note }}</p>
                                @endif
                            </div>
                            <x-ui.badge :variant="$status->badgeVariant()" class="shrink-0" data-finding-status>{{ $status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if(filled($inspection->summary) || filled($inspection->report_path))
                <x-ui.card as="section" title="Resumo e laudo em PDF" heading-level="h2" data-inspection-report>
                    @if(filled($inspection->summary))
                        <p class="max-w-prose text-sm whitespace-pre-line text-foreground">{{ $inspection->summary }}</p>
                    @endif

                    @if(filled($inspection->report_path))
                        <p class="mt-4">
                            <x-ui.link :href="route('inspections.report', $inspection)" icon="document-text" class="break-all">
                                {{ filled($inspection->report_original_name) ? $inspection->report_original_name : 'Abrir o laudo em PDF' }}
                            </x-ui.link>
                        </p>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </x-ui.container>
@endsection
