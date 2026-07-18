@extends('layouts.app')

@section('title', 'Marcas')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">🏷️ Marcas de veículos</h1>
            <p class="text-automotive-600">Catálogo usado nos cadastros de veículos</p>
        </div>
        <a href="{{ route('admin.brands.create') }}" class="btn-primary">+ Nova marca</a>
    </div>

    <div class="card overflow-hidden !p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-automotive-200 bg-automotive-50">
                <tr>
                    <th class="px-4 py-3 font-semibold">Marca</th>
                    <th class="px-4 py-3 font-semibold">Modelos</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                    <tr class="border-b border-automotive-100 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ $brand->name }}</td>
                        <td class="px-4 py-3 text-automotive-600">{{ $brand->models_count }}</td>
                        <td class="px-4 py-3">
                            @if($brand->is_active)
                                <span class="badge badge-blue">Ativa</span>
                            @else
                                <span class="badge badge-orange">Inativa</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.brands.show', $brand) }}" class="text-wrench-600 hover:underline">Modelos</a>
                            <span class="text-automotive-300">·</span>
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="text-automotive-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-automotive-500">
                            Nenhuma marca cadastrada.
                            <a href="{{ route('admin.brands.create') }}" class="text-wrench-600 hover:underline">Cadastrar primeira marca</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
