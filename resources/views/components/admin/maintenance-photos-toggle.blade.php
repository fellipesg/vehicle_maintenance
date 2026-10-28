{{--
    Liga ou desliga "Fotos das manutenções" (App\Features\MaintenancePhotos) em uma conta, oficina ou
    loja, pelo painel admin. Desligada é o padrão. O botão envia PUT com `enabled`; a página volta com
    o aviso de sucesso. Não é <x-ui.switch>: a página pode ter mais de um destes (conta e oficina) e o
    switch lê old() pelo nome do campo.

    Props:
    - action: rota PUT (admin.users|workshops|garages.maintenance-photos). Obrigatória.
    - enabled: valor só deste escopo (MaintenancePhotoAccess::isActiveFor).
    - subject: de quem é o interruptor, para o rótulo acessível ("a conta", "a oficina", "a loja").
    - inherited: o recurso está ativo por outro escopo da mesma conta (texto, ex.: "pela oficina").

    Ex.: <x-admin.maintenance-photos-toggle :action="route('admin.users.maintenance-photos', $user)" :enabled="$photos['user']" subject="a conta" />
--}}
@props([
    'action' => null,
    'enabled' => false,
    'subject' => 'a conta',
    'inherited' => null,
])
@php
    \App\Support\UiProps::required('x-admin.maintenance-photos-toggle', 'action', $action);

    $toggleLabel = 'Fotos das manutenções para '.$subject;
@endphp
<div {{ $attributes->class(['flex flex-wrap items-center justify-between gap-3']) }} data-slot="maintenance-photos-toggle" data-enabled="{{ $enabled ? 'true' : 'false' }}">
    <div class="min-w-0 text-sm">
        <p class="flex flex-wrap items-center gap-2 font-medium text-foreground">
            <span>Fotos das manutenções</span>
            @if($enabled)
                <x-ui.badge variant="success" size="sm" icon="check-circle">Ligadas</x-ui.badge>
            @elseif(filled($inherited))
                <x-ui.badge variant="info" size="sm">Ligadas {{ $inherited }}</x-ui.badge>
            @else
                <x-ui.badge variant="neutral" size="sm">Desligadas</x-ui.badge>
            @endif
        </p>
        <p class="mt-1 text-muted-foreground">Envio, visualização e fotos no PDF do histórico, para {{ $subject }}.</p>
    </div>

    <form method="POST" action="{{ $action }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
        <x-ui.button
            type="submit"
            size="sm"
            :variant="$enabled ? 'secondary' : 'primary'"
            :aria-label="($enabled ? 'Desligar ' : 'Ligar ').$toggleLabel"
            :loading-label="$enabled ? 'Desligando…' : 'Ligando…'"
        >{{ $enabled ? 'Desligar' : 'Ligar' }}</x-ui.button>
    </form>
</div>
