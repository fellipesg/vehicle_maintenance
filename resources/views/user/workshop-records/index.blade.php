{{--
    "Registros de oficinas": serviços que oficinas registraram nos veículos da conta antes dela
    chegar ao RevisaLog. Nada entra no histórico sem a escolha do proprietário: vincular, aceitar
    notas e fotos (só com a propriedade confirmada pelo CRLV-e) e ocultar da consulta pública.
    Descrição livre, valores e arquivos não aparecem aqui antes do aceite.
--}}
@extends('layouts.app')

@section('title', 'Registros de oficinas')

@section('content')
    <x-ui.container padded data-owner-page="workshop-records">
        <x-ui.page-header
            title="Registros de oficinas"
            description="Serviços que oficinas registraram nos seus veículos antes de você chegar. Você escolhe o que entra no seu histórico."
            :breadcrumbs="[['Início', route('user.dashboard')], ['Registros de oficinas']]"
        >
            <x-slot:actions>
                @if ($showAll)
                    <x-ui.button variant="secondary" :href="route('user.workshop-records.index')">Só os pendentes</x-ui.button>
                @else
                    <x-ui.button variant="secondary" :href="route('user.workshop-records.index', ['status' => 'all'])">Ver todos</x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @error('decision')
            <x-ui.alert variant="danger" class="mb-6" title="Não foi possível salvar" data-decision-error>{{ $message }}</x-ui.alert>
        @enderror

        @if ($records->isEmpty())
            <x-ui.empty-state icon="clipboard-document" :title="$showAll ? 'Nenhum registro de oficina' : 'Nenhum registro pendente'"
                              description="Quando uma oficina registrar um serviço num veículo seu, ele aparece aqui para você decidir." />
        @else
            <div class="space-y-6">
                @foreach ($records as $record)
                    @php
                        $recordVehicle = $record->vehicle;
                        $recordWorkshop = $record->displayWorkshopName() ?? 'Oficina';
                        $canAttach = $decisions->canAcceptAttachments(auth()->user(), $record);
                        $canDecide = $decisions->canDecide(auth()->user(), $record);
                        $hasFiles = $record->attachments_status === \App\Models\Maintenance::ATTACHMENTS_PENDING;
                        $statusLabel = match ($record->owner_status) {
                            \App\Models\Maintenance::OWNER_LINKED => 'Vinculado ao seu histórico',
                            \App\Models\Maintenance::OWNER_DECLINED => 'Só no chassi, sem detalhes',
                            default => 'Aguardando a sua escolha',
                        };
                    @endphp
                    <x-ui.card as="section" heading-level="h2" :title="$record->maintenance_type"
                        :description="trim($recordVehicle->brand.' '.$recordVehicle->model.' '.$recordVehicle->year).' · '.$recordWorkshop.' · '.$record->maintenance_date?->format('d/m/Y').' · '.number_format((int) $record->kilometers, 0, ',', '.').' km'"
                        data-workshop-record="{{ $record->id }}">
                        <p class="mb-3 text-sm font-medium text-foreground" data-record-status>{{ $statusLabel }}@if ($record->isHiddenFromPublic()) · oculto da consulta pública @endif</p>

                        @if ($record->items->isNotEmpty())
                            <p class="text-sm text-muted-foreground">Itens: {{ $record->items->pluck('name')->implode(', ') }}.</p>
                        @endif
                        @if ($record->verification_code)
                            <p class="text-sm text-muted-foreground">Selo da oficina: <span class="font-mono">{{ $record->verification_code }}</span></p>
                        @endif
                        @if ($hasFiles)
                            <p class="text-sm text-muted-foreground" data-record-attachments>
                                A oficina anexou {{ $record->invoices->count() }} {{ $record->invoices->count() === 1 ? 'nota fiscal' : 'notas fiscais' }} e {{ $record->photos->count() }} {{ $record->photos->count() === 1 ? 'foto' : 'fotos' }}.
                                Eles podem conter dados pessoais, por isso só entram no seu histórico se você aceitar.
                                Sem aceite em {{ config('maintenance.pending_attachments_retention_days', 90) }} dias, são apagados.
                            </p>
                        @endif

                        @if (! $canDecide)
                            <div class="mt-4 space-y-3" data-record-needs-verification>
                                <p class="text-sm text-muted-foreground">{{ \App\Services\Maintenance\MaintenanceOwnerDecisionService::UNVERIFIED_DECISION_MESSAGE }}</p>
                                <x-ui.button variant="secondary" :href="route('user.vehicles.create')">Enviar o CRLV-e</x-ui.button>
                            </div>
                        @else
                        <form method="POST" action="{{ route('user.workshop-records.decide', $record) }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="status" value="{{ $showAll ? 'all' : '' }}">
                            <x-ui.checkbox name="link" :id="'link-'.$record->id" uncheckedValue="0" :checked="$record->owner_status !== \App\Models\Maintenance::OWNER_DECLINED"
                                label="Vincular este registro ao meu histórico"
                                description="O serviço passa a aparecer junto com as suas manutenções. Sem vincular, ele fica só no chassi, com data, quilometragem, serviço, peças e oficina." />
                            <x-ui.checkbox name="attach_files" :id="'attach-'.$record->id" uncheckedValue="0"
                                :checked="$record->attachments_status === \App\Models\Maintenance::ATTACHMENTS_ACCEPTED"
                                :disabled="! $canAttach && $record->attachments_status !== \App\Models\Maintenance::ATTACHMENTS_ACCEPTED"
                                label="Aceitar as notas fiscais e fotos da oficina"
                                :description="$canAttach || $record->attachments_status === \App\Models\Maintenance::ATTACHMENTS_ACCEPTED
                                    ? 'Os arquivos passam a fazer parte do histórico do veículo. Você pode revogar depois e eles são apagados.'
                                    : ($hasFiles ? 'Para aceitar, confirme que o veículo é seu enviando o CRLV-e.' : 'Esta oficina não anexou arquivos.')" />
                            <x-ui.checkbox name="hide_from_public" :id="'hide-'.$record->id" uncheckedValue="0"
                                :checked="$record->isHiddenFromPublic()"
                                label="Ocultar do histórico público"
                                description="Só a oficina que fez o serviço continua vendo este registro fora da sua conta." />
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Salvar escolha</x-ui.button>
                                @if ($hasFiles && ! $canAttach)
                                    <x-ui.button variant="secondary" :href="route('user.vehicles.create')">Enviar o CRLV-e</x-ui.button>
                                @endif
                            </div>
                        </form>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </x-ui.container>
@endsection
