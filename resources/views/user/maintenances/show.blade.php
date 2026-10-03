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

        @include('maintenances._detail', [
            'maintenance' => $maintenance,
            'portal' => Portal::Owner,
            'vehicleUrl' => $canViewVehicle ? null : false,
        ])
    </x-ui.container>
@endsection
