@extends('layouts.admin')

@section('title', 'Marcas e modelos')

@php
    $adminBreadcrumbs = [['Catálogo'], ['Marcas e modelos']];
    $hasSearch = $search !== '';
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
@endphp

@section('content')
    <x-ui.page-header title="Marcas e modelos" description="Catálogo usado nos cadastros de veículos. Só marcas e modelos ativos aparecem nos formulários.">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('admin.brands.create')">Nova marca</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" data-admin-brands-summary>
            <span>
                @if($hasSearch)
                    {{ $formatCount($brands->total()) }} {{ $brands->total() === 1 ? 'marca' : 'marcas' }} para
                    <span class="font-medium text-foreground">“{{ $search }}”</span>
                @else
                    {{ $formatCount($brands->total()) }} {{ $brands->total() === 1 ? 'marca' : 'marcas' }}
                @endif
            </span>
            @if($hasSearch)
                <x-ui.link :href="route('admin.brands.index')" icon="x-mark">Limpar busca</x-ui.link>
            @endif
        </p>

        <form method="GET" action="{{ route('admin.brands.index') }}" role="search" aria-label="Buscar marcas" class="flex w-full gap-2 sm:w-auto" data-submit-busy="off">
            <label for="admin-brand-search" class="sr-only">Buscar marca pelo nome</label>
            <x-ui.input
                type="search"
                id="admin-brand-search"
                name="q"
                :value="$search"
                placeholder="Nome da marca"
                leading-icon="magnifying-glass"
                autocomplete="off"
                class="min-w-0 flex-1 sm:w-64"
            />
            <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
        </form>
    </div>

    <x-ui.table caption="Marcas cadastradas" stack :sort="$sort" :direction="$direction">
        <x-slot:head>
            <tr>
                <th data-sort="marca">Marca</th>
                <th class="text-right" data-sort="modelos" data-sort-default="desc">Modelos</th>
                <th>Situação</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($brands as $brand)
            @php
                $brandModelCount = (int) $brand->models_count;
                $brandDeletionMessage = match (true) {
                    $brandModelCount === 0 => "A marca {$brand->name} será excluída do catálogo.",
                    $brandModelCount === 1 => "A marca {$brand->name} e o modelo dela serão excluídos.",
                    default => "A marca {$brand->name} e os {$brandModelCount} modelos dela serão excluídos.",
                };
            @endphp
            <tr>
                <th scope="row" class="font-medium">
                    <x-ui.link :href="route('admin.brands.show', $brand)">{{ $brand->name }}</x-ui.link>
                </th>
                <td class="text-right">{{ $formatCount($brandModelCount) }}</td>
                <td>
                    @if($brand->is_active)
                        <x-ui.badge variant="success" dot>Ativa</x-ui.badge>
                    @else
                        <x-ui.badge dot>Inativa</x-ui.badge>
                    @endif
                </td>
                <td class="py-2 text-right">
                    <x-admin.row-actions :label="'Ações para a marca '.$brand->name" :id="'marca-'.$brand->id.'-acoes'">
                        <x-ui.dropdown-item :href="route('admin.brands.show', $brand)" icon="squares-2x2">Ver modelos</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('admin.brands.edit', $brand)" icon="pencil-square">Editar marca</x-ui.dropdown-item>
                        <x-ui.dropdown-item separator />
                        <x-ui.dropdown-item
                            :action="route('admin.brands.destroy', $brand)"
                            method="DELETE"
                            icon="trash"
                            variant="danger"
                            :data-confirm="$brandDeletionMessage.' Veículos já cadastrados não mudam. Não é possível desfazer.'"
                            :data-confirm-title="'Excluir a marca '.$brand->name.'?'"
                            data-confirm-action-label="Excluir marca"
                            data-confirm-variant="danger"
                        >Excluir marca</x-ui.dropdown-item>
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            @if($hasSearch)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    :title="'Nenhuma marca para “'.$search.'”'"
                    description="Confira o nome ou cadastre a marca."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.brands.index')">Limpar busca</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="squares-2x2"
                    title="Nenhuma marca cadastrada"
                    description="Cadastre a primeira marca para liberar os modelos nos formulários de veículo."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button icon="plus" :href="route('admin.brands.create')">Nova marca</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @endif
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $brands->links() }}
    </div>
@endsection
