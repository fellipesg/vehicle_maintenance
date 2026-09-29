{{--
    Status da consignação de um veículo do estoque do lojista.
    $grant: VehicleAccessGrant de consignação deste lojista (null quando a procuração não chegou).
    $compact: true mostra só as badges; false soma a explicação, o motivo da recusa e a próxima ação.
    Reenviar a procuração passa pelo assistente "Adicionar ao estoque" (garage.vehicles.create; /vincular só
    redireciona para ele): o CRLV-e é lido de novo e o fluxo pede a nova procuração.
--}}
@php
    $compact = $compact ?? false;
    $consignmentStatus = match ($grant?->status) {
        'approved' => [
            'key' => 'approved',
            'label' => 'Procuração aprovada',
            'variant' => 'success',
            'icon' => 'check-circle',
            'message' => 'A equipe aprovou a procuração e o histórico do veículo está liberado para consulta.',
        ],
        'pending' => [
            'key' => 'pending',
            'label' => 'Procuração em análise',
            'variant' => 'warning',
            'icon' => 'clock',
            'message' => 'Recebemos a procuração. O histórico do veículo abre aqui quando a análise for concluída.',
        ],
        null => [
            'key' => 'missing',
            'label' => 'Procuração não enviada',
            'variant' => 'warning',
            'icon' => 'exclamation-circle',
            'message' => 'Envie a procuração do proprietário para liberar o histórico do veículo.',
        ],
        default => [
            'key' => 'rejected',
            'label' => 'Procuração recusada',
            'variant' => 'danger',
            'icon' => 'x-circle',
            'message' => 'A procuração não foi aceita. Envie uma nova para liberar o histórico do veículo.',
        ],
    };
    $needsResend = in_array($consignmentStatus['key'], ['rejected', 'missing'], true);
@endphp

<div data-consignment-status="{{ $consignmentStatus['key'] }}">
    <div class="flex flex-wrap items-center gap-1.5">
        <x-ui.badge variant="info">Consignação</x-ui.badge>
        <x-ui.badge :variant="$consignmentStatus['variant']" :icon="$consignmentStatus['icon']">{{ $consignmentStatus['label'] }}</x-ui.badge>
    </div>

    @unless ($compact)
        <p class="mt-2 text-sm text-muted-foreground">{{ $consignmentStatus['message'] }}</p>

        @if ($consignmentStatus['key'] === 'rejected' && filled($grant?->review_notes))
            <p class="mt-1 text-sm text-foreground"><span class="font-medium">Motivo:</span> {{ $grant->review_notes }}</p>
        @endif

        @if ($needsResend)
            <x-ui.link :href="route('garage.vehicles.create')" class="relative z-10 mt-2 text-sm" arrow data-consignment-resend>
                {{ $consignmentStatus['key'] === 'missing' ? 'Enviar procuração' : 'Reenviar procuração' }}
            </x-ui.link>
            <p class="mt-0.5 text-xs text-muted-foreground">Você envia o CRLV-e de novo e, em seguida, a procuração.</p>
        @endif
    @endunless
</div>
