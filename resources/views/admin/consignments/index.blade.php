@extends('layouts.admin')

@section('title', 'Consignações — Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="mb-2 text-2xl font-bold text-automotive-900">Consignações</h1>
    <p class="mb-6 text-sm text-automotive-600">
        A garagem registra manutenções assim que declara a consignação. Esta fila é só para liberar
        o <strong>histórico anterior</strong> do veículo e para tratar contestações do proprietário.
    </p>

    @php($tabs = [
        'atencao' => 'Precisam de atenção ('.($pendingCount + $disputedCount).')',
        'contestadas' => 'Contestadas ('.$disputedCount.')',
        'ativas' => 'Ativas',
        'todas' => 'Todas',
    ])
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($tabs as $key => $label)
            <a
                href="{{ route('admin.consignments.index', ['filtro' => $key]) }}"
                class="{{ $filter === $key ? 'btn-primary' : 'btn-secondary' }} !py-1.5 !text-xs"
            >{{ $label }}</a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse($consignments as $consignment)
            <div class="card @if($consignment->isDisputed()) border-l-4 border-red-400 @elseif($consignment->isHistoryReviewPending()) border-l-4 border-amber-400 @endif">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="text-sm">
                        <p class="font-semibold text-automotive-900">
                            {{ $consignment->vehicle->brand }} {{ $consignment->vehicle->model }}
                            <span class="font-mono text-automotive-500">{{ $consignment->vehicle->license_plate }}</span>
                        </p>
                        <p class="mt-1 text-automotive-600">
                            Garagem: <strong>{{ $consignment->garageUser->name }}</strong> ·
                            desde {{ $consignment->started_at->format('d/m/Y') }}
                        </p>
                        <p class="text-automotive-600">
                            Proprietário declarado: <strong>{{ $consignment->owner_name }}</strong>
                            @if($consignment->ownerUser)
                                <span class="badge badge-blue">tem conta</span>
                            @elseif($consignment->owner_email)
                                · {{ $consignment->owner_email }}
                            @elseif($consignment->owner_phone)
                                · {{ $consignment->owner_phone }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-automotive-500">
                            Declaração aceita em {{ $consignment->declaration_accepted_at?->format('d/m/Y H:i') }}
                            @if($consignment->declaration_ip) · IP {{ $consignment->declaration_ip }} @endif
                            @if($consignment->owner_notified_at) · proprietário avisado em {{ $consignment->owner_notified_at->format('d/m/Y H:i') }} @endif
                        </p>
                    </div>
                    <div class="text-right text-xs">
                        @if($consignment->isDisputed())
                            <span class="badge badge-orange">Contestada</span>
                        @endif
                        @if(! $consignment->isActive())
                            <span class="badge">Encerrada ({{ $consignment->end_reason }})</span>
                        @endif
                        <p class="mt-2 text-automotive-600">
                            Histórico: <strong>{{ $consignment->history_access_status }}</strong>
                            @if($consignment->history_approved_via)
                                <br>liberado por {{ $consignment->history_approved_via === 'owner' ? 'proprietário' : 'equipe' }}
                            @endif
                        </p>
                    </div>
                </div>

                @if($consignment->isDisputed())
                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                        <p class="font-medium">Contestada em {{ $consignment->owner_disputed_at->format('d/m/Y H:i') }}</p>
                        @if($consignment->owner_dispute_note)
                            <p class="mt-1">{{ $consignment->owner_dispute_note }}</p>
                        @endif
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if($consignment->power_of_attorney_path)
                        <a href="{{ route('admin.consignments.power-of-attorney', $consignment) }}" class="btn-secondary !py-1.5 !text-xs">
                            Baixar procuração
                        </a>
                    @endif

                    @if($consignment->isHistoryReviewPending())
                        <form method="POST" action="{{ route('admin.consignments.approve', $consignment) }}">
                            @csrf
                            <button type="submit" class="btn-primary !py-1.5 !text-xs">Liberar histórico</button>
                        </form>
                        <form method="POST" action="{{ route('admin.consignments.reject', $consignment) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="review_notes" placeholder="Motivo (opcional)" class="form-input !py-1.5 !text-xs" maxlength="1000">
                            <button type="submit" class="btn-secondary !py-1.5 !text-xs">Recusar</button>
                        </form>
                    @endif

                    @if($consignment->isDisputed() && $consignment->isActive())
                        <form method="POST" action="{{ route('admin.consignments.clear-dispute', $consignment) }}">
                            @csrf
                            <button type="submit" class="btn-secondary !py-1.5 !text-xs">Arquivar contestação</button>
                        </form>
                    @endif

                    @if($consignment->isActive())
                        <form
                            method="POST"
                            action="{{ route('admin.consignments.revoke', $consignment) }}"
                            onsubmit="return confirm('Revogar esta consignação? A garagem perde o acesso ao veículo.');"
                        >
                            @csrf
                            <button type="submit" class="btn-secondary !py-1.5 !text-xs">Revogar consignação</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card text-center text-automotive-500">Nenhuma consignação nesta lista.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $consignments->links() }}</div>
</div>
@endsection
