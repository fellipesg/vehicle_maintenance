{{--
    "Registros de oficinas": serviços que oficinas registraram nos veículos da conta antes dela
    chegar ao RevisaLog. Nada entra no histórico sem a escolha do proprietário: vincular e aceitar
    notas e fotos (só com a propriedade confirmada pelo CRLV-e). Ocultar saiu — no lugar dele há
    "contestar", que marca o registro sem tirá-lo da consulta pública e não pede o CRLV-e.
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
                        $canDispute = $decisions->canDispute(auth()->user(), $record);
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
                        {{-- Diretivas coladas (@endif@if) não compilam: o Blade deixa a segunda como texto. --}}
                        <p class="mb-3 text-sm font-medium text-foreground" data-record-status>{{ $statusLabel }}@if ($record->isHiddenFromPublic()) · oculto pela equipe @endif
                            @if ($record->isDisputed()) · contestado por você @endif
                        </p>

                        @if ($record->isDisputed())
                            <x-ui.alert variant="warning" role="status" class="mb-4" title="Você contestou este registro" data-record-disputed>
                                O registro continua aparecendo na consulta pública e no histórico do veículo, agora com o aviso de que você contesta — quem for comprar o carro vê as duas versões. Avisamos a oficina, que é quem pode corrigir ou excluir a OS, e a nossa equipe analisa.
                                @if (filled($record->owner_dispute_reason))
                                    <span class="mt-1 block">O que você informou: {{ $record->owner_dispute_reason }}</span>
                                @endif
                                <x-slot:actions>
                                    <form method="POST" action="{{ route('user.workshop-records.dispute.withdraw', $record) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="status" value="{{ $showAll ? 'all' : '' }}">
                                        <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Retirando…">Retirar contestação</x-ui.button>
                                    </form>
                                </x-slot:actions>
                            </x-ui.alert>
                        @elseif ($canDispute)
                            <details class="mb-4 rounded-lg border border-border p-3" data-record-dispute-form>
                                <summary class="cursor-pointer text-sm font-medium text-foreground">Este serviço não foi feito no meu carro</summary>
                                <form method="POST" action="{{ route('user.workshop-records.dispute', $record) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $showAll ? 'all' : '' }}">
                                    <p class="text-sm text-muted-foreground">
                                        O registro <strong>não sai</strong> do histórico do chassi: ele continua visível para quem consultar o veículo, marcado como contestado por você. Essa é a diferença em relação a esconder — o histórico é o que dá valor ao carro, e suprimi-lo deixaria quem vai comprar sem saber que houve contestação. Avisamos a oficina, que pode corrigir ou excluir, e a nossa equipe analisa. Você não precisa do CRLV-e para isso.
                                    </p>
                                    <x-ui.textarea name="reason" :id="'reason-'.$record->id" rows="2" maxlength="500"
                                        label="O que aconteceu (opcional)"
                                        placeholder="Ex.: nunca levei o carro nesta oficina." />
                                    <x-ui.button variant="secondary" type="submit" loading-label="Contestando…">Contestar registro</x-ui.button>
                                </form>
                            </details>
                        @endif

                        @if ($record->items->isNotEmpty())
                            <p class="text-sm text-muted-foreground">Itens: {{ $record->items->pluck('name')->implode(', ') }}.</p>
                        @endif
                        @if ($record->verification_code)
                            <p class="text-sm text-muted-foreground">Selo da oficina: <span class="font-mono">{{ $record->verification_code }}</span></p>
                        @endif
                        @if ($hasFiles)
                            <p class="text-sm text-muted-foreground" data-record-attachments>
                                A oficina anexou {{ $record->invoices->count() }} {{ $record->invoices->count() === 1 ? 'nota fiscal' : 'notas fiscais' }}@if(\App\Support\Maintenance\MaintenancePhotoAccess::allows(auth()->user())) e {{ $record->photos->count() }} {{ $record->photos->count() === 1 ? 'foto' : 'fotos' }}@endif.
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
                            <x-ui.checkbox name="link" :id="'link-'.$record->id" uncheckedValue="0" :checked="$record->owner_status === \App\Models\Maintenance::OWNER_LINKED"
                                label="Vincular este registro ao meu histórico"
                                description="O serviço passa a aparecer junto com as suas manutenções. Sem vincular, ele fica só no chassi, com data, quilometragem, serviço, peças e oficina." />
                            <x-ui.checkbox name="attach_files" :id="'attach-'.$record->id" uncheckedValue="0"
                                :checked="$record->attachments_status === \App\Models\Maintenance::ATTACHMENTS_ACCEPTED"
                                :disabled="! $canAttach && $record->attachments_status !== \App\Models\Maintenance::ATTACHMENTS_ACCEPTED"
                                label="Aceitar as notas fiscais e fotos da oficina"
                                :description="$canAttach || $record->attachments_status === \App\Models\Maintenance::ATTACHMENTS_ACCEPTED
                                    ? 'Os arquivos passam a fazer parte do histórico do veículo. Você pode revogar depois e eles são apagados.'
                                    : ($hasFiles ? 'Para aceitar, confirme que o veículo é seu enviando o CRLV-e.' : 'Esta oficina não anexou arquivos.')" />
                            @if ($hasFiles)
                                <p class="-mt-1 pl-7 text-sm font-medium text-warning" data-record-attach-warning>Se você não aceitar, as notas fiscais e fotos da oficina são apagadas ao salvar.</p>
                            @endif
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
