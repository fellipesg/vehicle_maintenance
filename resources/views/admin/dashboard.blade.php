@extends('layouts.app')

@section('title', 'Painel Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">⚙️ Painel Administrador</h1>
        <p class="text-automotive-600">Visão geral da plataforma, usuários e catálogo de veículos</p>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Usuários</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $userCount }}</p>
            <p class="mt-1 text-xs text-automotive-500">
                {{ $usersByType['user'] ?? 0 }} proprietários · {{ $usersByType['garage'] ?? 0 }} lojistas · {{ $usersByType['workshop'] ?? 0 }} oficinas
            </p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Veículos cadastrados</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $vehicleCount }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Manutenções</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $maintenanceCount }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Marcas / Modelos</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $brandCount }} / {{ $modelCount }}</p>
            <a href="{{ route('admin.brands.index') }}" class="mt-1 inline-block text-xs text-wrench-600 hover:underline">Gerenciar catálogo</a>
        </div>
    </div>

    <div class="card overflow-hidden !p-0">
        <div class="border-b border-automotive-200 px-6 py-4">
            <h2 class="text-xl font-semibold">👥 Todos os usuários</h2>
            <p class="text-sm text-automotive-500">Clique em um usuário para ver veículos e manutenções</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-automotive-200 bg-automotive-50">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Nome</th>
                        <th class="px-4 py-3 font-semibold">E-mail</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                        <th class="px-4 py-3 font-semibold">Veículos</th>
                        <th class="px-4 py-3 font-semibold">Manutenções</th>
                        <th class="px-4 py-3 font-semibold text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $account)
                        <tr class="border-b border-automotive-100 last:border-0">
                            <td class="px-4 py-3 font-medium">
                                {{ $account->name }}
                                @if($account->is_admin)
                                    <span class="badge badge-orange ml-1">Admin</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-automotive-600">{{ $account->email }}</td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $account->typeBadgeClass() }}">{{ $account->typeLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $account->vehicles_count }}</td>
                            <td class="px-4 py-3">{{ $account->maintenances_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.users.show', $account) }}" class="text-wrench-600 hover:underline">Ver detalhes</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-automotive-500">Nenhum usuário cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
