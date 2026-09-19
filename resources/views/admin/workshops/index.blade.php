@extends('layouts.admin')

@section('title', 'Oficinas — Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold text-automotive-900">Oficinas cadastradas</h1>

    <div class="card overflow-hidden !p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-automotive-200 bg-automotive-50">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Nome</th>
                        <th class="px-4 py-3 font-semibold">Cidade</th>
                        <th class="px-4 py-3 font-semibold">Endereço</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workshops as $workshop)
                        <tr class="border-b border-automotive-100">
                            <td class="px-4 py-3 font-medium">{{ $workshop->name }}</td>
                            <td class="px-4 py-3">{{ $workshop->city }}/{{ $workshop->state }}</td>
                            <td class="px-4 py-3 text-automotive-600">
                                {{ $workshop->street }}, {{ $workshop->number }}
                                @if($workshop->neighborhood)
                                    — {{ $workshop->neighborhood }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-automotive-600">Nenhuma oficina.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $workshops->links() }}</div>
</div>
@endsection
