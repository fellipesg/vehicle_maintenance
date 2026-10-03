{{--
    Moldura de campo com complementos dentro da borda: ícone, "R$", "km", botão. O controle vai no
    slot padrão e precisa ser um <x-ui.input> ou <x-ui.select> (data-slot="control"): dentro do
    grupo eles perdem a borda própria e a moldura assume borda, foco e erro.

    Slots:
    - leading: antes do valor (ícone de busca, "R$").
    - trailing: depois do valor (unidade "km", botão). Um <button> no complemento encosta na borda
      e ganha alvo de 40px.

    O texto dos complementos não entra no nome do campo: ponha a unidade também no rótulo
    ("Quilometragem (km)").

    Ex.:
    <x-ui.field name="price" label="Preço (R$)">
        <x-ui.input-group>
            <x-slot:leading>R$</x-slot:leading>
            <x-ui.input inputmode="decimal" />
        </x-ui.input-group>
    </x-ui.field>
--}}@props([])
@php
    $groupHasLeading = isset($leading) && filled($leading);
    $groupHasTrailing = isset($trailing) && filled($trailing);
@endphp
<div {{ $attributes->class([
    'flex w-full min-w-0 items-center rounded-control border border-input bg-surface text-foreground shadow-sm',
    'transition-[border-color,box-shadow] duration-fast ease-smooth-out motion-reduce:transition-none',
    'has-[[data-slot=control]:focus:not([aria-invalid=true])]:border-ring has-[[data-slot=control]:focus]:ring-2 has-[[data-slot=control]:focus:not([aria-invalid=true])]:ring-ring/30',
    'has-[[data-slot=control][aria-invalid=true]]:border-danger has-[[data-slot=control][aria-invalid=true]:focus]:ring-danger/60',
    'has-[[data-slot=control]:disabled]:cursor-not-allowed has-[[data-slot=control]:disabled]:bg-surface-muted',
    '[&_[data-slot=control]]:pl-2' => $groupHasLeading,
    '[&_input[data-slot=control]]:pr-2' => $groupHasTrailing,
])->merge(['data-slot' => 'input-group']) }}>
    @if($groupHasLeading)
        <span data-slot="input-group-addon" data-align="leading" class="flex shrink-0 items-center gap-2 pl-3 text-sm text-muted-foreground select-none has-[>button]:pl-0">{{ $leading }}</span>
    @endif
    {{ $slot }}
    @if($groupHasTrailing)
        <span data-slot="input-group-addon" data-align="trailing" class="flex shrink-0 items-center gap-2 pr-3 text-sm text-muted-foreground select-none has-[>button]:pr-0">{{ $trailing }}</span>
    @endif
</div>
