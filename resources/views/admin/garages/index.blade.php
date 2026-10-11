@extends('layouts.admin')

@section('title', 'Lojas')

@php
    use App\Enums\GarageStatus;

    $adminBreadcrumbs = [['Cadastros'], ['Lojas']];
    $tabs = [
        ['value' => GarageStatus::Pending->value, 'label' => 'Em análise', 'count' => $counts[GarageStatus::Pending->value], 'href' => route('admin.garages.index', ['filtro' => GarageStatus::Pending->value])],
        ['value' => GarageStatus::Approved->value, 'label' => 'Aprovadas', 'count' => $counts[GarageStatus::Approved->value], 'href' => route('admin.garages.index', ['filtro' => GarageStatus::Approved->value])],
        ['value' => GarageStatus::Rejected->value, 'label' => 'Recusadas', 'count' => $counts[GarageStatus::Rejected->value], 'href' => route('admin.garages.index', ['filtro' => GarageStatus::Rejected->value])],
        ['value' => 'todas', 'label' => 'Todas', 'href' => route('admin.garages.index', ['filtro' => 'todas'])],
    ];
@endphp

@section('content')
    <x-ui.page-header
        title="Lojas"
        description="Uma loja escreve no histórico permanente de um chassi e manda e-mail para proprietários. Confira o CNPJ no registro público antes de liberar."
    />

    <x-ui.segmented label="Filtrar lojas" :options="$tabs" :value="$filter" class="mb-4" />

    <div class="space-y-4">
        @forelse($garages as $garage)
            <x-ui.card as="article" data-garage-row="{{ $garage->id }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-foreground">{{ $garage->name }}</p>
                        <p class="mt-1 text-muted-foreground">
                            CNPJ: <span class="font-medium tabular-nums text-foreground">{{ $garage->displayDocument() ?? 'não informado' }}</span>
                        </p>
                        <p class="text-muted-foreground">
                            Endereço: <span class="text-foreground">{{ $garage->addressLine() !== '' ? $garage->addressLine() : 'não informado' }}</span>
                        </p>
                        @if($garage->user)
                            <p class="mt-1 text-muted-foreground">
                                Conta:
                                <x-ui.link :href="route('admin.users.show', $garage->user)" variant="inline">{{ $garage->user->name }}</x-ui.link>
                                · {{ $garage->user->email }}
                            </p>
                        @endif
                        @if($garage->reviewed_at)
                            <p class="mt-1 text-xs text-muted-foreground">
                                Analisada em {{ $garage->reviewed_at->format('d/m/Y H:i') }}
                                @if($garage->reviewer) por {{ $garage->reviewer->name }} @endif
                            </p>
                        @endif
                    </div>

                    <x-ui.badge :variant="$garage->status->badgeVariant()">{{ $garage->status->label() }}</x-ui.badge>
                </div>

                @if(filled($garage->review_notes))
                    <x-ui.alert variant="info" class="mt-3" title="Observação da análise">{{ $garage->review_notes }}</x-ui.alert>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if($garage->status !== GarageStatus::Approved)
                        <form method="POST" action="{{ route('admin.garages.approve', $garage) }}">
                            @csrf
                            <x-ui.button size="sm" type="submit" icon="check" loading-label="Aprovando…">Aprovar loja</x-ui.button>
                        </form>
                    @endif

                    @if($garage->status !== GarageStatus::Rejected)
                        <form method="POST" action="{{ route('admin.garages.reject', $garage) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <x-ui.input name="review_notes" placeholder="Motivo (opcional)" maxlength="1000" class="text-sm" aria-label="Motivo da recusa" />
                            <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Recusando…">Recusar</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state
                icon="building-office-2"
                title="Nenhuma loja nesta lista"
                description="Quando uma conta de garagem preencher o cadastro, ela aparece aqui para análise."
            />
        @endforelse
    </div>

    <div class="mt-6">{{ $garages->links() }}</div>
@endsection
