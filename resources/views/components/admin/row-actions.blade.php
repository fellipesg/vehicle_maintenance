{{--
    Menu de ações de uma linha de tabela do admin: botão ⋯ (alvo de 40px) que abre um
    <x-ui.dropdown> com os itens da linha. O mesmo lugar e o mesmo gatilho em todas as tabelas:
    Ver, Editar e, por último, Excluir (variant="danger" e data-confirm no item).

    Props:
    - label (obrigatório): nome do botão para leitor de tela, com o objeto da linha
      ("Ações para a marca Fiat").
    - id: prefixo dos ids do gatilho e do menu (padrão: gerado).

    Slot: os <x-ui.dropdown-item>. Item que abre diálogo usa data-dialog-open="id"; o foco volta ao
    botão ⋯ quando o diálogo fecha.

    Ex.: <x-admin.row-actions :label="'Ações para a marca '.$brand->name" :id="'marca-'.$brand->id.'-acoes'">
             <x-ui.dropdown-item :href="route('admin.brands.show', $brand)" icon="eye">Ver modelos</x-ui.dropdown-item>
         </x-admin.row-actions>
--}}
@props([
    'label' => null,
    'id' => null,
])
@php
    \App\Support\UiProps::required('x-admin.row-actions', 'label', $label, 'O botão ⋯ só tem ícone: o label diz de que linha são as ações.');
@endphp
<x-ui.dropdown :id="$id" :label="$label" placement="bottom-end" {{ $attributes->merge(['data-slot' => 'row-actions']) }}>
    <x-slot:trigger class="relative inline-flex size-10 shrink-0 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast ease-smooth-out hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none">
        <x-ui.icon name="ellipsis-horizontal" class="size-5" />
    </x-slot:trigger>

    {{ $slot }}
</x-ui.dropdown>
