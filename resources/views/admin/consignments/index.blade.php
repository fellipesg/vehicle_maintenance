@extends('layouts.admin')

@section('title', 'Consignações')

@php
    $adminBreadcrumbs = [['Cadastros'], ['Consignações']];
    $tabs = [
        ['value' => 'atencao', 'label' => 'Precisam de atenção', 'count' => $pendingCount + $disputedCount, 'href' => route('admin.consignments.index', ['filtro' => 'atencao'])],
        ['value' => 'contestadas', 'label' => 'Contestadas', 'count' => $disputedCount, 'href' => route('admin.consignments.index', ['filtro' => 'contestadas'])],
        ['value' => 'ativas', 'label' => 'Ativas', 'href' => route('admin.consignments.index', ['filtro' => 'ativas'])],
        ['value' => 'todas', 'label' => 'Todas', 'href' => route('admin.consignments.index', ['filtro' => 'todas'])],
    ];
@endphp

@section('content')
    <x-ui.page-header
        title="Consignações"
        description="O lojista registra manutenções assim que declara a consignação. Esta fila é para liberar o histórico que o veículo já tinha e para tratar as contestações do proprietário."
    />

    <x-ui.segmented label="Filtrar consignações" :options="$tabs" :value="$filter" class="mb-4" />

    <div class="space-y-4">
        @forelse($consignments as $consignment)
            <x-ui.card as="article" data-consignment-row="{{ $consignment->id }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-foreground">
                            {{ $consignment->vehicle->brand }} {{ $consignment->vehicle->model }}
                            <span class="font-mono tracking-wider text-muted-foreground">{{ $consignment->vehicle->license_plate }}</span>
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            Loja: <span class="font-medium text-foreground">{{ $consignment->garageUser->name }}</span>
                            · desde {{ $consignment->started_at->format('d/m/Y') }}
                        </p>
                        <p class="text-muted-foreground">
                            Proprietário declarado: <span class="font-medium text-foreground">{{ $consignment->owner_name }}</span>
                            @if($consignment->ownerUser)
                                <x-ui.badge variant="info">tem conta</x-ui.badge>
                            @elseif($consignment->owner_email)
                                · {{ $consignment->owner_email }}
                            @elseif($consignment->owner_phone)
                                · {{ $consignment->owner_phone }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Declaração aceita em {{ $consignment->declaration_accepted_at?->format('d/m/Y H:i') }}
                            @if($consignment->declaration_ip) · IP {{ $consignment->declaration_ip }} @endif
                            @if($consignment->owner_notified_at) · proprietário avisado em {{ $consignment->owner_notified_at->format('d/m/Y H:i') }} @endif
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-1 text-xs">
                        @if($consignment->isDisputed())
                            <x-ui.badge variant="danger" icon="exclamation-triangle">Contestada</x-ui.badge>
                        @endif
                        @unless($consignment->isActive())
                            <x-ui.badge>Encerrada · {{ $consignment->end_reason }}</x-ui.badge>
                        @endunless
                        <span class="text-muted-foreground">Histórico: {{ $consignment->history_access_status }}</span>
                        @if($consignment->history_approved_via)
                            <span class="text-muted-foreground">liberado por {{ $consignment->history_approved_via === 'owner' ? 'proprietário' : 'equipe' }}</span>
                        @endif
                    </div>
                </div>

                @if($consignment->isDisputed())
                    <x-ui.alert variant="danger" class="mt-3" :title="'Contestada em '.$consignment->owner_disputed_at->format('d/m/Y H:i')">
                        {{ $consignment->owner_dispute_note ?: 'O proprietário não deixou um motivo.' }}
                    </x-ui.alert>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if($consignment->power_of_attorney_path)
                        <x-ui.button variant="secondary" size="sm" icon="arrow-down-tray" :href="route('admin.consignments.power-of-attorney', $consignment)">
                            Baixar procuração
                        </x-ui.button>
                    @endif

                    @if($consignment->isHistoryReviewPending())
                        <form method="POST" action="{{ route('admin.consignments.approve', $consignment) }}">
                            @csrf
                            <x-ui.button size="sm" type="submit" loading-label="Liberando…">Liberar histórico</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('admin.consignments.reject', $consignment) }}" class="flex items-center gap-2">
                            @csrf
                            <x-ui.input name="review_notes" placeholder="Motivo (opcional)" maxlength="1000" class="text-sm" aria-label="Motivo da recusa" />
                            <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Recusando…">Recusar</x-ui.button>
                        </form>
                    @endif

                    @if($consignment->isDisputed() && $consignment->isActive())
                        <form method="POST" action="{{ route('admin.consignments.clear-dispute', $consignment) }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Arquivando…">Arquivar contestação</x-ui.button>
                        </form>
                    @endif

                    @if($consignment->isActive())
                        <form
                            method="POST"
                            action="{{ route('admin.consignments.revoke', $consignment) }}"
                            data-confirm="A loja perde o acesso ao veículo. As manutenções que ela registrou continuam no histórico."
                            data-confirm-title="Revogar a consignação deste veículo?"
                            data-confirm-action-label="Revogar consignação"
                            data-confirm-variant="danger"
                        >
                            @csrf
                            <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Revogando…">Revogar consignação</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state
                
                title="Nenhuma consignação nesta lista"
                description="Quando um lojista declarar um veículo em consignação, ele aparece aqui."
            />
        @endforelse
    </div>

    <div class="mt-6">{{ $consignments->links() }}</div>
@endsection
