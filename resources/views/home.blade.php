@extends('layouts.app')

@section('title', 'Início')

@section('content')
<div class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-automotive-900 via-automotive-800 to-automotive-950"></div>
    <div class="absolute right-0 top-0 h-96 w-96 bg-wrench-500/10 blur-3xl"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 text-white">
        <div class="max-w-3xl">
            <span class="mb-4 inline-flex items-center rounded-full border border-automotive-600 bg-automotive-800/50 px-3 py-1 text-xs font-medium text-automotive-300">
                Histórico permanente de manutenções
            </span>
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
                <a href="{{ route('vehicle.search') }}" class="btn-secondary inline-flex items-center gap-2 !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 1 0 10.607 10.607Z" />
                    </svg>
                    Buscar veículo
                </a>
            </div>
        </div>
    </div>
</div>

<div class="mx-auto max-w-7xl px-4 py-16">
    <h2 class="mb-8 text-center text-2xl font-bold text-automotive-900">Para quem é o sistema?</h2>

    <div class="grid gap-6 md:grid-cols-3">
        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-automotive-200 bg-automotive-50 text-automotive-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">Proprietário</p>
            <h3 class="mt-1 text-lg font-semibold text-automotive-900">Proprietários</h3>
            <p class="mt-2 text-sm text-automotive-600">Registre manutenções dos seus veículos, exporte PDF e consulte oficinas parceiras.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar como usuário</a>@endguest
        </div>

        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-automotive-200 bg-automotive-50 text-automotive-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 5.25 9.349m0 0a3.001 3.001 0 0 1 3.75-.614 2.993 2.993 0 0 1 3.25 3.25" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">Lojista</p>
            <h3 class="mt-1 text-lg font-semibold text-automotive-900">Garagens</h3>
            <p class="mt-2 text-sm text-automotive-600">Gerencie o estoque de veículos e documente revisões pré-venda com histórico permanente.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar garagem</a>@endguest
        </div>

        <div class="card text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-automotive-200 bg-automotive-50 text-automotive-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.015 1.745-1.437" />
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">Oficina</p>
            <h3 class="mt-1 text-lg font-semibold text-automotive-900">Oficinas</h3>
            <p class="mt-2 text-sm text-automotive-600">Cadastre sua oficina, apareça no diretório e acompanhe os serviços realizados.</p>
            @guest<a href="{{ route('register') }}" class="btn-secondary mt-4">Cadastrar oficina</a>@endguest
        </div>
    </div>
</div>

<div class="border-t border-automotive-200 bg-white py-16">
    <div class="mx-auto max-w-7xl px-4">
        <h2 class="mb-8 text-center text-2xl font-bold text-automotive-900">Funcionalidades</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['icon' => 'clipboard', 'title' => 'Histórico completo', 'desc' => 'Todas as manutenções vinculadas ao veículo'],
                ['icon' => 'document', 'title' => 'Exportar PDF', 'desc' => 'Relatório com notas fiscais anexadas'],
                ['icon' => 'search', 'title' => 'Busca pública', 'desc' => 'Consulte por placa ou RENAVAM'],
                ['icon' => 'building', 'title' => 'Diretório de oficinas', 'desc' => 'Encontre mecânicas cadastradas'],
            ] as $feature)
                <div class="card !p-4">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg border border-automotive-200 bg-automotive-50 text-automotive-600">
                        @if($feature['icon'] === 'clipboard')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                        @elseif($feature['icon'] === 'document')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        @elseif($feature['icon'] === 'search')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 1 0 10.607 10.607Z" />
                            </svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-16.5-3v3m16.5-3v3M3.75 21V6.75A2.25 2.25 0 0 1 6 4.5h12a2.25 2.25 0 0 1 2.25 2.25V21M9 8.25h.008v.008H9V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 0h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        @endif
                    </div>
                    <h3 class="font-semibold text-automotive-900">{{ $feature['title'] }}</h3>
                    <p class="mt-1 text-sm text-automotive-600">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
