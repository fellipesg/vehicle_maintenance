@extends('layouts.app')

@section('title', 'Templates de garantia')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="badge badge-orange mb-2">🔧 Oficina</span>
            <h1 class="text-3xl font-bold">Templates de garantia</h1>
        </div>
        <a href="{{ route('workshop.warranty-templates.create') }}" class="btn-primary">Novo template</a>
    </div>

    @if($templatesLocked)
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            Há garantias vigentes emitidas por esta oficina. Templates existentes estão bloqueados para edição de conteúdo; crie um novo template para alterar os termos das próximas OS.
        </div>
    @endif

    @if($workshop->logoUrl())
        <div class="card mb-4 flex items-center gap-3">
            <img src="{{ $workshop->logoUrl() }}" alt="Logo da oficina" class="h-14 w-auto object-contain">
            <p class="text-sm text-automotive-600">Logo da oficina exibida nos termos e PDFs.</p>
        </div>
    @endif

    @forelse($templates as $template)
        <div class="card mb-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $template->name }}</p>
                    <p class="text-sm text-automotive-600">
                        {{ $template->scope->value === 'order' ? 'OS geral' : 'Por item' }}
                        · {{ $template->duration_days }} dias
                        · {{ $template->is_active ? 'Ativo' : 'Inativo' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('workshop.warranty-templates.edit', $template) }}" class="btn-secondary text-sm">Editar</a>
                    @if(!$template->isReferenced())
                        <form method="POST" action="{{ route('workshop.warranty-templates.destroy', $template) }}" onsubmit="return confirm('Remover este template?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary text-sm text-red-600">Excluir</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card text-center text-automotive-500">
            <p>Nenhum template de garantia cadastrado.</p>
        </div>
    @endforelse

    <div class="mt-4">{{ $templates->links() }}</div>
</div>
@endsection
