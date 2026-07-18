@extends('layouts.app')

@section('title', 'Início')

@section('content')
<div class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-automotive-900 via-automotive-800 to-automotive-950"></div>
    <div class="absolute right-0 top-0 h-96 w-96 bg-wrench-500/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 text-white">
        <div class="max-w-3xl">
            <span class="badge badge-orange mb-4">🚗 Histórico permanente de manutenções</span>
            <h1 class="text-4xl font-bold leading-tight sm:text-5xl">
                Cuide do seu carro com <span class="text-wrench-400">histórico completo</span>
            </h1>
            <p class="mt-4 text-lg text-automotive-200">
                Registre manutenções, consulte o histórico por placa ou RENAVAM, e mantenha o valor do seu veículo documentado.
            </p>

            <div class="mt-8 flex flex-wrap gap-4">
                @auth
                    @php
                        $dashRoute = auth()->user()->isAdmin() && request()->routeIs('admin.*')
                            ? route('admin.dashboard')
                            : match(auth()->user()->user_type) {
                                'garage' => route('garage.dashboard'),
                                'workshop' => route('workshop.dashboard'),
                                default => route('user.dashboard'),
                            };
                    @endphp
                    <a href="{{ $dashRoute }}" class="btn-primary">Ir para o painel</a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary">Começar grátis</a>
                    <a href="{{ route('login') }}" class="btn-secondary !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">Entrar</a>
                @endauth
                <a href="{{ route('vehicle.search') }}" class="btn-secondary !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">🔍 Buscar veículo</a>
            </div>
        </div>
    </div>
</div>

<div class="mx-auto max-w-7xl px-4 py-16">
    <h2 class="mb-8 text-center text-2xl font-bold text-automotive-900">Para quem é o sistema?</h2>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-automotive-100 text-3xl">👤</div>
            <h3 class="text-lg font-semibold">Proprietários</h3>
            <p class="mt-2 text-sm text-automotive-600">Registre manutenções dos seus veículos, exporte PDF e consulte oficinas parceiras.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar como usuário</a>@endguest
        </div>

        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl">🏪</div>
            <h3 class="text-lg font-semibold">Garagens</h3>
            <p class="mt-2 text-sm text-automotive-600">Gerencie o estoque de veículos e documente revisões pré-venda com histórico permanente.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar garagem</a>@endguest
        </div>

        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-3xl">🔧</div>
            <h3 class="text-lg font-semibold">Oficinas</h3>
            <p class="mt-2 text-sm text-automotive-600">Cadastre sua oficina, apareça no diretório e acompanhe os serviços realizados.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar oficina</a>@endguest
        </div>
    </div>
</div>

<div class="border-t border-automotive-200 bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="mb-8 text-center text-2xl font-bold">Funcionalidades</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['icon' => '📋', 'title' => 'Histórico completo', 'desc' => 'Todas as manutenções vinculadas ao veículo'],
                ['icon' => '📄', 'title' => 'Exportar PDF', 'desc' => 'Relatório com notas fiscais anexadas'],
                ['icon' => '🔍', 'title' => 'Busca pública', 'desc' => 'Consulte por placa ou RENAVAM'],
                ['icon' => '🏭', 'title' => 'Diretório de oficinas', 'desc' => 'Encontre mecânicas cadastradas'],
            ] as $feature)
                <div class="rounded-lg border border-automotive-100 p-4">
                    <span class="text-2xl">{{ $feature['icon'] }}</span>
                    <h3 class="mt-2 font-semibold">{{ $feature['title'] }}</h3>
                    <p class="mt-1 text-sm text-automotive-600">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
