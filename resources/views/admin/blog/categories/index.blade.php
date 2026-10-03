@extends('layouts.admin')

@section('title', 'Categorias do blog')

@php
    use App\Http\Controllers\Web\Admin\BlogCategoryController;

    $adminBreadcrumbs = [['Conteúdo'], ['Categorias do blog']];

    /*
     * Criar e editar acontecem em diálogos, cada formulário com o seu error bag (createCategory,
     * updateCategory{id}): o erro e o texto digitado voltam só no diálogo enviado, que reabre na
     * carga (data-dialog-open-on-load). Os campos são nativos porque x-ui.input/x-ui.textarea
     * leriam o old() de qualquer formulário da página.
     */
    $createBag = $errors->getBag(BlogCategoryController::CREATE_BAG);
    $createFailed = $createBag->isNotEmpty();
    $failedCategory = $categories->first(fn ($category) => $errors->getBag(BlogCategoryController::updateBag($category))->isNotEmpty());
    $failedDialogId = $createFailed ? 'nova-categoria' : ($failedCategory ? 'categoria-'.$failedCategory->id.'-dialogo' : null);
    $failedBag = $createFailed ? $createBag : ($failedCategory ? $errors->getBag(BlogCategoryController::updateBag($failedCategory)) : null);
    $checkboxClass = 'mt-0.5 size-4 shrink-0 cursor-pointer rounded border-input text-accent-foreground not-checked:bg-surface transition-colors duration-fast ease-smooth-out motion-reduce:transition-none focus:ring-0 focus:ring-offset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';
    $inputClass = 'form-input h-10 text-base sm:text-sm aria-invalid:border-danger aria-invalid:focus:ring-danger/60';
    $textareaClass = 'form-input min-h-20 text-base sm:text-sm aria-invalid:border-danger aria-invalid:focus:ring-danger/60';
@endphp

@section('content')
    <x-ui.page-header title="Categorias do blog" description="Agrupam os artigos na listagem pública do blog. Categoria inativa some do blog, e os artigos dela continuam publicados.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="newspaper" :href="route('admin.blog.index')">Artigos do blog</x-ui.button>
            <x-ui.button icon="plus" data-dialog-open="nova-categoria">Nova categoria</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-4">
        @if($failedDialogId)
            <x-ui.alert variant="danger" :title="$createFailed ? 'A categoria não foi criada.' : 'A categoria '.$failedCategory->name.' não foi salva.'" data-admin-dialog-error>
                {{ $failedBag->first() }}
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" icon="pencil-square" :data-dialog-open="$failedDialogId">Corrigir</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
        @endif

        <x-ui.table caption="Categorias do blog" stack>
            <x-slot:head>
                <tr>
                    <th class="min-w-56">Categoria</th>
                    <th class="min-w-60">Descrição</th>
                    <th class="text-right">Artigos</th>
                    <th>Situação</th>
                    <th class="text-right"><span class="sr-only">Ações</span></th>
                </tr>
            </x-slot:head>

            @foreach($categories as $category)
                @php
                    $categoryPostsLabel = $category->posts_count === 1 ? 'o artigo dela fica' : 'os '.$category->posts_count.' artigos dela ficam';
                @endphp
                <tr>
                    <th scope="row" class="font-medium">
                        {{ $category->name }}
                        <span class="block font-mono text-xs font-normal text-muted-foreground">/blog/categoria/{{ $category->slug }}</span>
                    </th>
                    <td class="text-muted-foreground">{{ $category->description ?: '—' }}</td>
                    <td class="text-right">{{ $category->posts_count }}</td>
                    <td>
                        @if($category->is_active)
                            <x-ui.badge variant="success" dot>Ativa</x-ui.badge>
                        @else
                            <x-ui.badge dot>Inativa</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2 text-right">
                        <x-admin.row-actions :label="'Ações para a categoria '.$category->name" :id="'categoria-'.$category->id.'-acoes'">
                            <x-ui.dropdown-item icon="pencil-square" :data-dialog-open="'categoria-'.$category->id.'-dialogo'">Editar categoria</x-ui.dropdown-item>
                            @if($category->is_active)
                                <x-ui.dropdown-item :href="route('blog.category', $category)" icon="arrow-top-right-on-square" target="_blank" rel="noopener">Ver no blog <span class="sr-only">(abre em nova aba)</span></x-ui.dropdown-item>
                            @endif
                            <x-ui.dropdown-item separator />
                            <x-ui.dropdown-item
                                :action="route('admin.blog.categories.destroy', $category)"
                                method="DELETE"
                                icon="trash"
                                variant="danger"
                                :data-confirm="($category->posts_count === 0 ? 'A categoria '.$category->name.' sai do blog.' : 'A categoria '.$category->name.' sai do blog e '.$categoryPostsLabel.' sem categoria.').' Não é possível desfazer.'"
                                :data-confirm-title="'Excluir a categoria '.$category->name.'?'"
                                data-confirm-action-label="Excluir categoria"
                                data-confirm-variant="danger"
                            >Excluir categoria</x-ui.dropdown-item>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state icon="tag" title="Nenhuma categoria cadastrada" description="Crie a primeira categoria para agrupar os artigos no blog." variant="plain" size="sm" heading-level="p">
                    <x-slot:actions>
                        <x-ui.button icon="plus" data-dialog-open="nova-categoria">Nova categoria</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.table>
    </div>

    {{-- Diálogo "Nova categoria" (error bag createCategory). --}}
    <x-ui.dialog id="nova-categoria" title="Nova categoria" description="O endereço público é gerado a partir do nome." :data-dialog-open-on-load="$createFailed ? 'true' : null">
        <form method="POST" action="{{ route('admin.blog.categories.store') }}" id="nova-categoria-form" class="grid gap-4">
            @csrf
            <x-ui.field label="Nome" hint="Ex.: Manutenção" :error="$createBag->first('name') ?: null" required>
                <input type="text" name="name" id="nova-categoria-nome" required maxlength="80" autocomplete="off"
                       value="{{ $createFailed ? old('name') : '' }}" class="{{ $inputClass }}">
            </x-ui.field>
            <x-ui.field label="Descrição" hint="Aparece no topo da página da categoria." :error="$createBag->first('description') ?: null" optional>
                <textarea name="description" id="nova-categoria-descricao" rows="2" maxlength="255" class="{{ $textareaClass }}">{{ $createFailed ? old('description') : '' }}</textarea>
            </x-ui.field>
            <div>
                <input type="hidden" name="is_active" value="0">
                <label for="nova-categoria-ativa" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" id="nova-categoria-ativa" class="{{ $checkboxClass }}"
                           aria-describedby="nova-categoria-ativa-descricao" @checked($createFailed ? (bool) old('is_active') : true)>
                    <span>
                        <span class="font-medium text-foreground">Categoria ativa</span>
                        <span id="nova-categoria-ativa-descricao" class="block text-muted-foreground">Categoria inativa some da listagem pública do blog.</span>
                    </span>
                </label>
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
            <x-ui.button type="submit" form="nova-categoria-form" icon="plus" loading-label="Criando…">Criar categoria</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    {{-- Um diálogo de edição por categoria (error bag updateCategory{id}). --}}
    @foreach($categories as $category)
        @php
            $categoryBag = $errors->getBag(BlogCategoryController::updateBag($category));
            $categoryFailed = $categoryBag->isNotEmpty();
            $categoryFormId = 'categoria-'.$category->id;
            $keepSlugChecked = $categoryFailed ? (bool) old('keep_slug') : true;
        @endphp
        <x-ui.dialog :id="$categoryFormId.'-dialogo'" :title="'Editar categoria '.$category->name" :data-dialog-open-on-load="$categoryFailed ? 'true' : null">
            <form method="POST" action="{{ route('admin.blog.categories.update', $category) }}" id="{{ $categoryFormId }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <x-ui.field label="Nome" :error="$categoryBag->first('name') ?: null" required>
                    <input type="text" name="name" id="{{ $categoryFormId }}-nome" required maxlength="80" autocomplete="off"
                           value="{{ $categoryFailed ? old('name', $category->name) : $category->name }}" class="{{ $inputClass }}">
                </x-ui.field>
                <x-ui.field label="Descrição" hint="Aparece no topo da página da categoria." :error="$categoryBag->first('description') ?: null" optional>
                    <textarea name="description" id="{{ $categoryFormId }}-descricao" rows="2" maxlength="255" class="{{ $textareaClass }}">{{ $categoryFailed ? old('description', $category->description) : $category->description }}</textarea>
                </x-ui.field>
                <div class="grid gap-1">
                    <input type="hidden" name="is_active" value="0">
                    <label for="{{ $categoryFormId }}-ativa" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" id="{{ $categoryFormId }}-ativa" class="{{ $checkboxClass }}"
                               aria-describedby="{{ $categoryFormId }}-ativa-descricao" @checked($categoryFailed ? (bool) old('is_active') : (bool) $category->is_active)>
                        <span>
                            <span class="font-medium text-foreground">Categoria ativa</span>
                            <span id="{{ $categoryFormId }}-ativa-descricao" class="block text-muted-foreground">Inativa some do blog; os artigos dela continuam publicados.</span>
                        </span>
                    </label>
                    <label for="{{ $categoryFormId }}-manter-endereco" class="flex min-h-10 cursor-pointer items-start gap-3 py-2 text-sm">
                        <input type="checkbox" name="keep_slug" value="1" id="{{ $categoryFormId }}-manter-endereco" class="{{ $checkboxClass }}"
                               aria-describedby="{{ $categoryFormId }}-manter-endereco-descricao" @checked($keepSlugChecked)>
                        <span>
                            <span class="font-medium text-foreground">Manter o endereço atual</span>
                            <span id="{{ $categoryFormId }}-manter-endereco-descricao" class="block text-muted-foreground">
                                <span class="font-mono text-xs">/blog/categoria/{{ $category->slug }}</span>. Desmarque para gerar um endereço novo a partir do nome; links já compartilhados deixam de funcionar.
                            </span>
                        </span>
                    </label>
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" data-dialog-close>Cancelar</x-ui.button>
                <x-ui.button type="submit" :form="$categoryFormId" icon="check" loading-label="Salvando…">Salvar categoria</x-ui.button>
            </x-slot:footer>
        </x-ui.dialog>
    @endforeach
@endsection
