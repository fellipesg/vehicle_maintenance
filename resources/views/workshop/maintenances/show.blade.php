@extends('layouts.app')

@section('title', $maintenance->maintenance_type)

@php
    $vehicle = $maintenance->vehicle;
    $plate = $vehicle?->license_plate;
    $vehicleName = $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : null;
    $headerDescription = collect([$vehicleName, $plate, $maintenance->maintenance_date?->format('d/m/Y')])
        ->filter(fn ($part) => filled($part))
        ->implode(' · ');
    $breadcrumbLabel = $maintenance->maintenance_type.($plate ? ' · '.$plate : '');

    $photoCount = $maintenance->photos->count();
    $invoiceCount = $maintenance->invoices->count();
    $attachments = collect([
        $photoCount > 0 ? ($photoCount === 1 ? '1 foto' : "{$photoCount} fotos") : null,
        $invoiceCount > 0 ? ($invoiceCount === 1 ? '1 nota fiscal' : "{$invoiceCount} notas fiscais") : null,
    ])->filter()->implode(' e ');
    $deleteMessage = collect([
        $plate ? "A OS sai do histórico do veículo {$plate}." : 'A OS sai do histórico do veículo.',
        $attachments !== '' ? "Os anexos também são apagados: {$attachments}." : null,
        $maintenance->verification_code ? "O código {$maintenance->verification_code} deixa de valer e o proprietário perde este registro com Selo da oficina." : null,
        'Não é possível desfazer.',
    ])->filter()->implode(' ');
    $declaredBy = $maintenance->registered_by_type === 'garage' ? 'pelo lojista' : 'pelo proprietário';
    // Horário de Brasília, o mesmo do /v/{código} e do PDF (App\Support\DisplayTime).
    $localUpdatedAt = \App\Support\DisplayTime::local($maintenance->updated_at);
@endphp

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header
            :title="$maintenance->maintenance_type"
            :description="$headerDescription"
            :breadcrumbs="[['Ordens de serviço', route('workshop.maintenances.index')], [$breadcrumbLabel]]"
        >
            @if($updatedAfterSeal)
                <p class="text-sm text-muted-foreground" data-updated-after-seal>
                    Atualizada em <time datetime="{{ $localUpdatedAt->toIso8601String() }}" class="tabular-nums">{{ $localUpdatedAt->format('d/m/Y') }} às {{ $localUpdatedAt->format('H:i') }}</time>, depois da emissão do selo.
                </p>
            @endif

            @if($canManage)
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="pencil-square" :href="route('workshop.maintenances.edit', $maintenance)">Editar OS</x-ui.button>
                    <x-ui.dropdown label="Mais ações" width="md">
                        <x-slot:trigger class="inline-flex size-10 items-center justify-center rounded-control border border-border-strong bg-surface text-foreground shadow-sm transition-colors duration-fast ease-smooth-out hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none">
                            <x-ui.icon name="ellipsis-horizontal" class="size-5" />
                        </x-slot:trigger>
                        @if($plate)
                            <x-ui.dropdown-item :href="route('workshop.maintenances.create', ['license_plate' => $plate])" icon="plus">Nova OS para este veículo</x-ui.dropdown-item>
                            <x-ui.dropdown-item separator />
                        @endif
                        <x-ui.dropdown-item
                            :action="route('workshop.maintenances.destroy', $maintenance)"
                            method="DELETE"
                            icon="trash"
                            variant="danger"
                            :data-confirm="$deleteMessage"
                            :data-confirm-title="$plate ? 'Excluir a OS de '.$plate.'?' : 'Excluir esta OS?'"
                            data-confirm-action-label="Excluir OS"
                            data-confirm-variant="danger"
                        >Excluir OS</x-ui.dropdown-item>
                    </x-ui.dropdown>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @unless($canManage)
            <x-ui.alert variant="info" role="status" class="mb-6" title="Declarada {{ $declaredBy }}, sem o Selo da sua oficina" data-declared-notice>
                Quem cuida do veículo registrou este serviço e citou a sua oficina. Você pode consultar o registro, mas só quem declarou pode alterar ou excluir.
            </x-ui.alert>
        @endunless

        @if($maintenance->isOwnerlessRecord())
            <x-ui.alert variant="info" role="status" class="mb-6" title="Registro sem proprietário no RevisaLog" data-ownerless-notice>
                O carro ainda não tem proprietário. Notas fiscais e fotos desta OS ficam só com a sua oficina até o proprietário aceitar; sem resposta em {{ config('maintenance.pending_attachments_retention_days', 90) }} dias, elas são apagadas. O histórico público mostra apenas data, quilometragem, serviço, peças e o nome da oficina.
            </x-ui.alert>
        @endif

        @if($canInvite)
            @include('workshop.maintenances._invite-card', ['maintenance' => $maintenance, 'invite' => $invite])
        @endif

        @include('maintenances._detail', [
            'maintenance' => $maintenance,
            'portal' => \App\Enums\Portal::Workshop,
            'vehicleUrl' => false,
        ])
    </x-ui.container>
@endsection
