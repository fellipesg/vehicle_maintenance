@extends('layouts.admin')

@section('title', $brand->name)

@php
    use App\Http\Controllers\Web\Admin\VehicleModelController;

    $adminBreadcrumbs = [['Catálogo'], ['Marcas e modelos', route('admin.brands.index')], [$brand->name]];
    $brandModelCount = (int) $brand->models_count;
    $hasSearch = $search !== '';

    /*
     * Criar e editar modelo acontecem em diálogos. Cada formulário tem o seu error bag
     * (createModel, updateModel{id}): o erro e o texto digitado voltam só no diálogo que foi
     * enviado, e esse diálogo reabre na carga (data-dialog-open-on-load, initDialogs de resources/js/ui/dialog.js).
     * Os campos são nativos porque x-ui.input/x-ui.switch leriam o old() de qualquer formulário.
     */
    $createBag = $errors->getBag(VehicleModelController::CREATE_BAG);
    $createFailed = $createBag->isNotEmpty();
    $failedModel = $brand->models->first(fn ($model) => $errors->getBag(VehicleModelController::updateBag($model))->isNotEmpty());
    $failedDialogId = $createFailed ? 'novo-modelo' : ($failedModel ? 'modelo-'.$failedModel->id.'-dialogo' : null);
    $failedMessage = $createFailed
        ? $createBag->first('name')
        : ($failedModel ? $errors->getBag(VehicleModelController::updateBag($failedModel))->first('name') : null);
    $checkboxClass = 'size-4 shrink-0 cursor-pointer rounded border-input text-accent-foreground not-checked:bg-surface transition-colors duration-fast ease-smooth-out motion-reduce:transition-none focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';
    $inputClass = 'form-input h-10 text-base sm:text-sm aria-invalid:border-danger aria-invalid:focus:ring-danger/60';
@endphp

@section('content')
    <x-ui.page-header :title="$brand->name" :description="$brandModelCount.' '.($brandModelCount === 1 ? 'modelo cadastrado' : 'modelos cadastrados')">
        @unless($brand->is_active)
            <x-ui.badge variant="warning" icon="exclamation-triangle">Marca inativa: não aparece nos formulários</x-ui.badge>
        @endunless

        <x-slot:actions>
            <x-ui.button variant="secondary" icon="pencil-square" :href="route('admin.brands.edit', $brand)">Editar marca</x-ui.button>
            <x-ui.button icon="plus" data-dialog-open="novo-modelo">Novo modelo</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-4">
        @if($failedDialogId)
            <x-ui.alert variant="danger" :title="$createFailed ? 'O modelo não foi cadastrado.' : 'O modelo '.$failedModel->name.' não foi salvo.'" data-admin-dialog-error>
                {{ $failedMessage }}
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="pencil-square" :data-dialog-open="$failedDialogId">Corrigir</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" data-admin-models-summary>
                <span>
                    @if($hasSearch)
                        {{ $brand->models->count() }} {{ $brand->models->count() === 1 ? 'modelo' : 'modelos' }} para
                        <span class="font-medium text-foreground">“{{ $search }}”</span>
                    @else
                        Só modelos ativos aparecem nos formulários de veículo.
                    @endif
                </span>
                @if($hasSearch)
                    <x-ui.link :href="route('admin.brands.show', $brand)" icon="x-mark">Limpar filtro</x-ui.link>
                @endif
            </p>

            @if($brandModelCount > 0)
                <form method="GET" action="{{ route('admin.brands.show', $brand) }}" role="search" aria-label="Filtrar modelos" class="flex w-full gap-2 sm:w-auto" data-submit-busy="off">
                    <label for="filtro-modelos" class="sr-only">Filtrar modelos pelo nome</label>
                    <x-ui.input type="search" id="filtro-modelos" name="q" :value="$search" placeholder="Nome do modelo" leading-icon="magnifying-glass" autocomplete="off" class="min-w-0 flex-1 sm:w-64" />
                    <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
                </form>
            @endif
        </div>

        <x-ui.table :caption="'Modelos da marca '.$brand->name" stack>
            <x-slot:head>
                <tr>
                    <th>Modelo</th>
                    <th>Situação</th>
                    <th class="text-right"><span class="sr-only">Ações</span></th>
                </tr>
            </x-slot:head>

            @foreach($brand->models as $model)
                <tr>
                    <th scope="row" class="font-medium">{{ $model->name }}</th>
                    <td>
                        @if($model->is_active)
                            <x-ui.badge variant="success" dot>Ativo</x-ui.badge>
                        @else
                            <x-ui.badge dot>Inativo</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2 text-right">
                        <x-admin.row-actions :label="'Ações para o modelo '.$model->name" :id="'modelo-'.$model->id.'-acoes'">
                            <x-ui.dropdown-item icon="pencil-square" :data-dialog-open="'modelo-'.$model->id.'-dialogo'">Editar modelo</x-ui.dropdown-item>
                            <x-ui.dropdown-item separator />
                            <x-ui.dropdown-item
                                :action="route('admin.models.destroy', $model)"
                                method="DELETE"
                                icon="trash"
                                variant="danger"
                                :data-confirm="'O modelo '.$model->name.' sai do catálogo da marca '.$brand->name.'. Veículos já cadastrados não mudam. Não é possível desfazer.'"
                                :data-confirm-title="'Excluir o modelo '.$model->name.'?'"
                                data-confirm-action-label="Excluir modelo"
                                data-confirm-variant="danger"
                            >Excluir modelo</x-ui.dropdown-item>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if($hasSearch)
                    <x-ui.empty-state icon="magnifying-glass" :title="'Nenhum modelo para “'.$search.'”'" description="Confira o nome ou cadastre o modelo." variant="plain" size="sm" heading-level="p">
                        <x-slot:actions>
                            <x-ui.button variant="secondary" :href="route('admin.brands.show', $brand)">Limpar filtro</x-ui.button>
                        </x-slot:actions>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="squares-2x2" title="Nenhum modelo cadastrado" description="Cadastre o primeiro modelo desta marca para ele aparecer nos formulários de veículo." variant="plain" size="sm" heading-level="p">
                        <x-slot:actions>
                            <x-ui.button icon="plus" data-dialog-open="novo-modelo">Novo modelo</x-ui.button>
                        </x-slot:actions>
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </div>

    {{-- Diálogo "Novo modelo" (error bag createModel). --}}
    <x-ui.dialog id="novo-modelo" title="Novo modelo" :description="'Entra no catálogo da marca '.$brand->name.'.'" :data-dialog-open-on-load="$createFailed ? 'true' : null">
        <form method="POST" action="{{ route('admin.brands.models.store', $brand) }}" id="novo-modelo-form" class="grid gap-4">
            @csrf
            <x-ui.field label="Nome do modelo" hint="Ex.: C 180" :error="$createBag->first('name') ?: null" required>
                <input type="text" name="name" id="novo-modelo-nome" required maxlength="100" autocomplete="off"
                       value="{{ $createFailed ? old('name') : '' }}" class="{{ $inputClass }}">
            </x-ui.field>
            <div>
                <input type="hidden" name="is_active" value="0">
                <label for="novo-modelo-ativo" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" id="novo-modelo-ativo" class="{{ $checkboxClass }} mt-0.5"
                           aria-describedby="novo-modelo-ativo-descricao" @checked($createFailed ? (bool) old('is_active') : true)>
                    <span>
                        <span class="font-medium text-foreground">Modelo ativo</span>
                        <span id="novo-modelo-ativo-descricao" class="block text-muted-foreground">Aparece nos formulários de veículo.</span>
                    </span>
                </label>
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" form="novo-modelo-form" icon="plus" loading-label="Adicionando…">Adicionar modelo</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    {{-- Um diálogo de edição por modelo (error bag updateModel{id}). --}}
    @foreach($brand->models as $model)
        @php
            $modelBag = $errors->getBag(VehicleModelController::updateBag($model));
            $modelFailed = $modelBag->isNotEmpty();
            $modelFormId = 'modelo-'.$model->id;
        @endphp
        <x-ui.dialog :id="$modelFormId.'-dialogo'" :title="'Editar modelo '.$model->name" :data-dialog-open-on-load="$modelFailed ? 'true' : null">
            <form method="POST" action="{{ route('admin.models.update', $model) }}" id="{{ $modelFormId }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <x-ui.field label="Nome do modelo" :error="$modelBag->first('name') ?: null" required>
                    <input type="text" name="name" id="{{ $modelFormId }}-nome" required maxlength="100" autocomplete="off"
                           value="{{ $modelFailed ? old('name', $model->name) : $model->name }}" class="{{ $inputClass }}">
                </x-ui.field>
                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label for="{{ $modelFormId }}-ativo" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" id="{{ $modelFormId }}-ativo" class="{{ $checkboxClass }} mt-0.5"
                               aria-describedby="{{ $modelFormId }}-ativo-descricao" @checked($modelFailed ? (bool) old('is_active') : (bool) $model->is_active)>
                        <span>
                            <span class="font-medium text-foreground">Modelo ativo</span>
                            <span id="{{ $modelFormId }}-ativo-descricao" class="block text-muted-foreground">Inativo some dos formulários de veículo e continua nos cadastros antigos.</span>
                        </span>
                    </label>
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
                <x-ui.button type="submit" :form="$modelFormId" icon="check" loading-label="Salvando…">Salvar modelo</x-ui.button>
            </x-slot:footer>
        </x-ui.dialog>
    @endforeach
@endsection
