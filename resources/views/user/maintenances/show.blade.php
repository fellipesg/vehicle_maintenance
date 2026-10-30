{{--
    Detalhe da manutenção (proprietário): o cabeçalho com o único H1 (o serviço), a trilha até o
    veículo e as ações; o corpo é o partial compartilhado maintenances._detail (procedência,
    detalhes, Garantia do serviço, peças e serviços, notas fiscais e fotos).

    Editar e Excluir só nas declaradas desta conta (MaintenancePolicy) e com o veículo ainda na conta
    (quem vendeu o carro não altera o histórico do comprador). Excluir fica no menu "Mais ações" e
    confirma com data-confirm (<x-ui.confirm-dialog>). Sem ação, uma nota explica o porquê.
--}}
@extends('layouts.app')

@section('title', $maintenance->maintenance_type)

@php
    use App\Enums\Portal;

    $showVehicle = $maintenance->vehicle;
    $showVehicleName = $showVehicle !== null ? trim($showVehicle->brand.' '.$showVehicle->model) : null;
    $showBreadcrumbs = $canViewVehicle && $showVehicle !== null
        ? [
            ['Meus veículos', route('user.vehicles.index')],
            [$showVehicleName, route('user.vehicles.show', $showVehicle)],
            [$maintenance->maintenance_type],
        ]
        : [['Manutenções', route('user.maintenances.index')], [$maintenance->maintenance_type]];
    $showDescription = collect([
        $showVehicleName,
        $showVehicle?->license_plate,
        $maintenance->maintenance_date?->format('d/m/Y'),
    ])->filter(fn ($part): bool => filled($part))->implode(' · ');
    $showLockedNote = match (true) {
        $canUpdate || $canDelete => null,
        $maintenance->isVerified() => 'Registro com Selo da oficina: só a oficina que aplicou o selo pode alterar.',
        $vehicleLeftAccount ?? false => 'O veículo não está mais na sua conta: o histórico fica com o proprietário atual e este registro não pode mais ser alterado.',
        default => 'Registrada em outra conta: só quem registrou pode alterar.',
    };
    $showDeleteConsequence = 'A manutenção sai do histórico'.($showVehicleName ? ' do '.$showVehicleName : ' do veículo')
        .', com as fotos e as notas fiscais, e a quilometragem atual do veículo é recalculada. Não é possível desfazer.';
@endphp

@section('content')
    <x-ui.container size="lg" padded data-owner-page="maintenance">
        <x-ui.page-header
            :title="$maintenance->maintenance_type"
            :description="$showDescription"
            :breadcrumbs="$showBreadcrumbs"
        >
            @if ($showLockedNote !== null)
                <p class="flex items-start gap-1.5 text-sm text-muted-foreground" data-maintenance-locked>
                    <x-ui.icon name="lock-closed" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ $showLockedNote }}</span>
                </p>
            @endif

            @if ($canUpdate || $canDelete)
                <x-slot:actions>
                    @if ($canDelete)
                        <x-ui.dropdown label="Mais ações" placement="bottom-end">
                            <x-slot:trigger class="inline-flex min-h-10 items-center gap-1.5 rounded-control border border-border-strong bg-surface px-3 text-sm font-semibold text-foreground shadow-xs transition-colors duration-fast hover:bg-surface-muted motion-reduce:transition-none">
                                <x-ui.icon name="ellipsis-horizontal" class="size-5" />
                                <span>Mais ações</span>
                            </x-slot:trigger>
                            <x-ui.dropdown-item
                                :action="route('user.maintenances.destroy', $maintenance)"
                                method="DELETE"
                                icon="trash"
                                variant="danger"
                                :data-confirm="$showDeleteConsequence"
                                data-confirm-title="Excluir esta manutenção?"
                                data-confirm-action-label="Excluir manutenção"
                                data-confirm-variant="danger"
                            >Excluir manutenção</x-ui.dropdown-item>
                        </x-ui.dropdown>
                    @endif
                    @if ($canUpdate)
                        <x-ui.button icon="pencil-square" :href="route('user.maintenances.edit', $maintenance)">Editar</x-ui.button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($maintenance->isDisputed())
            <x-ui.alert variant="warning" role="status" class="mb-6" title="Você contestou este registro" data-maintenance-disputed>
                Ele continua no histórico do veículo, com o Selo da oficina e com a marca de que você contesta — quem consultar o carro vê as duas versões. A oficina foi avisada e a nossa equipe está analisando.
                @if (filled($maintenance->owner_dispute_reason))
                    <span class="mt-1 block">O que você informou: {{ $maintenance->owner_dispute_reason }}</span>
                @endif
                <x-slot:actions>
                    <form method="POST" action="{{ route('user.workshop-records.dispute.withdraw', $maintenance) }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Retirando…">Retirar contestação</x-ui.button>
                    </form>
                </x-slot:actions>
            </x-ui.alert>
        @elseif ($canDispute ?? false)
            <details class="mb-6 rounded-lg border border-border p-4" data-maintenance-dispute-form>
                <summary class="cursor-pointer text-sm font-medium text-foreground">Este serviço não foi feito no meu carro</summary>
                <form method="POST" action="{{ route('user.workshop-records.dispute', $maintenance) }}" class="mt-3 space-y-3">
                    @csrf
                    <p class="text-sm text-muted-foreground">
                        O registro <strong>não sai</strong> do histórico: ele continua visível para quem consultar o veículo, marcado como contestado por você. O histórico é o que dá valor ao carro, e suprimi-lo deixaria quem vai comprar sem saber que houve contestação. Avisamos a oficina, que pode corrigir ou excluir, e a nossa equipe analisa.
                    </p>
                    <x-ui.textarea name="reason" id="maintenance-dispute-reason" rows="2" maxlength="500"
                        label="O que aconteceu (opcional)"
                        placeholder="Ex.: nunca levei o carro nesta oficina." />
                    <x-ui.button variant="secondary" type="submit" loading-label="Contestando…">Contestar registro</x-ui.button>
                </form>
            </details>
        @endif

        @include('maintenances._detail', [
            'maintenance' => $maintenance,
            'portal' => Portal::Owner,
            'vehicleUrl' => $canViewVehicle ? null : false,
        ])
    </x-ui.container>
@endsection
