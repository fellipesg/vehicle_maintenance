@extends('layouts.app')

@section('title', 'Editar template de garantia')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <span class="badge badge-orange mb-2">🔧 Oficina</span>
    <h1 class="mb-6 text-3xl font-bold">Editar template de garantia</h1>

    <form method="POST" action="{{ route('workshop.warranty-templates.update', $template) }}" class="card space-y-4">
        @csrf
        @method('PUT')
        @include('workshop.warranty-templates._form', [
            'workshop' => $workshop,
            'template' => $template,
            'templatesLocked' => $templatesLocked,
        ])
        <div class="flex gap-3">
            @if(!$templatesLocked)
                <button type="submit" class="btn-primary">Salvar alterações</button>
            @else
                <button type="submit" class="btn-primary">Salvar status ativo</button>
            @endif
            <a href="{{ route('workshop.warranty-templates.index') }}" class="btn-secondary">Voltar</a>
        </div>
    </form>
</div>
@endsection
