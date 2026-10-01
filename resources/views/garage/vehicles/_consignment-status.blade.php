{{--
    Status da consignação de um veículo do estoque do lojista.
    $consignment: VehicleConsignment ativa deste lojista (null quando o veículo não está consignado aqui).
    $compact: true mostra só as badges; false soma a explicação, o motivo da recusa e a próxima ação.

    A consignação declarada já deixa registrar manutenções: o que estes estados contam é o acesso ao
    histórico que o veículo tinha antes dela. Liberar depende do proprietário (um clique no aviso que
    ele recebeu) ou da equipe, ao aprovar a procuração. "Contestada" trava tudo até a equipe decidir.
--}}
@php
    $compact = $compact ?? false;
    $consignmentStatus = match (true) {
        $consignment?->isDisputed() => [
            'key' => 'disputed',
            'label' => 'Contestada pelo proprietário',
            'variant' => 'danger',
            'icon' => 'exclamation-triangle',
            'message' => 'O proprietário contestou esta consignação. Novos registros estão bloqueados até a equipe RevisaLog analisar o caso.',
        ],
        $consignment?->history_access_status === \App\Models\VehicleConsignment::HISTORY_APPROVED => [
            'key' => 'approved',
            'label' => 'Histórico liberado',
            'variant' => 'success',
            'icon' => 'check-circle',
            'message' => $consignment->history_approved_via === 'owner'
                ? 'O proprietário liberou o histórico do veículo para consulta.'
                : 'A equipe aprovou a procuração e o histórico do veículo está liberado para consulta.',
        ],
        $consignment?->history_access_status === \App\Models\VehicleConsignment::HISTORY_PENDING => [
            'key' => 'pending',
            'label' => 'Histórico aguardando liberação',
            'variant' => 'warning',
            'icon' => 'clock',
            'message' => 'O pedido foi enviado. O histórico abre aqui quando o proprietário liberar ou a equipe aprovar a procuração.',
        ],
        $consignment?->history_access_status === \App\Models\VehicleConsignment::HISTORY_REJECTED => [
            'key' => 'rejected',
            'label' => 'Pedido de histórico recusado',
            'variant' => 'danger',
            'icon' => 'x-circle',
            'message' => 'O pedido de acesso ao histórico não foi aceito. Você continua registrando manutenções normalmente.',
        ],
        default => [
            'key' => 'missing',
            'label' => 'Histórico restrito ao proprietário',
            'variant' => 'warning',
            'icon' => 'lock-closed',
            'message' => 'Você vê apenas as manutenções registradas pela sua loja. Peça a liberação ao proprietário para ver o histórico anterior.',
        ],
    };
    $needsRequest = in_array($consignmentStatus['key'], ['rejected', 'missing'], true);
@endphp

<div data-consignment-status="{{ $consignmentStatus['key'] }}">
    <div class="flex flex-wrap items-center gap-1.5">
        <x-ui.badge variant="info">Consignação</x-ui.badge>
        <x-ui.badge :variant="$consignmentStatus['variant']" :icon="$consignmentStatus['icon']">{{ $consignmentStatus['label'] }}</x-ui.badge>
    </div>

    @unless ($compact)
        <p class="mt-2 text-sm text-muted-foreground">{{ $consignmentStatus['message'] }}</p>

        @if ($consignmentStatus['key'] === 'rejected' && filled($consignment?->review_notes))
            <p class="mt-1 text-sm text-foreground"><span class="font-medium">Motivo:</span> {{ $consignment->review_notes }}</p>
        @endif

        @if ($needsRequest && $consignment !== null)
            <x-ui.link :href="route('garage.vehicles.show', $consignment->vehicle_id)" class="relative z-10 mt-2 text-sm" arrow data-consignment-request>
                Pedir liberação ao proprietário
            </x-ui.link>
            <p class="mt-0.5 text-xs text-muted-foreground">Ele libera com um clique no aviso que recebe por e-mail.</p>
        @endif
    @endunless
</div>
