@extends('layouts.app')

@section('title', 'Histórico permanente do seu carro')

@php
    $dashRoute = null;
    if (auth()->check()) {
        $dashRoute = auth()->user()->isAdmin() && request()->routeIs('admin.*')
            ? route('admin.dashboard')
            : match (auth()->user()->user_type) {
                'garage' => route('garage.dashboard'),
                'workshop' => route('workshop.dashboard'),
                default => route('user.dashboard'),
            };
    }
@endphp

@push('head')
    <meta name="description" content="RevisaLog: histórico permanente de manutenções vinculado ao veículo. Selo da oficina, busca por placa, chassi ou RENAVAM. Grátis no lançamento.">
    <meta property="og:title" content="Histórico permanente do seu carro">
    <meta property="og:description" content="RevisaLog: histórico permanente de manutenções vinculado ao veículo. Selo da oficina, busca por placa, chassi ou RENAVAM. Grátis no lançamento.">
@endpush

@section('content')
<div data-landing-page>
{{-- Hero --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-automotive-900 via-automotive-800 to-automotive-950"></div>
    <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]"></div>
    <div class="absolute -right-24 -top-24 h-[28rem] w-[28rem] rounded-full bg-wrench-500/15 blur-3xl"></div>
    <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-wrench-400/10 blur-3xl"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 lg:grid-cols-2 lg:py-24">
        <div>
            <span class="mb-5 inline-flex items-center gap-2 rounded-full bg-wrench-500 px-3.5 py-1.5 text-sm font-semibold text-automotive-950">
                <span class="h-1.5 w-1.5 rounded-full bg-automotive-950"></span>
                Histórico permanente · Grátis no lançamento
            </span>
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                O histórico do carro
                <span class="text-wrench-400">viaja com o carro</span>
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-automotive-200">
                Manutenções ficam vinculadas ao chassi, não ao dono. Selo da oficina, declaração do proprietário, PDF com notas e busca por placa, chassi ou RENAVAM.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ $dashRoute }}" class="btn-primary !px-5 !py-3">Ir para o painel</a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary !px-5 !py-3">Começar grátis</a>
                    <a href="{{ route('login') }}" class="btn-secondary !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">Entrar</a>
                @endauth
                <a href="{{ route('vehicle.search') }}" class="btn-secondary inline-flex items-center gap-2 !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 1 0 10.607 10.607Z" />
                    </svg>
                    Buscar veículo
                </a>
            </div>

            <ul class="mt-5 flex flex-wrap gap-2 text-xs text-automotive-300">
                <li class="rounded-full border border-white/10 bg-white/5 px-3 py-1">Sem cartão</li>
                <li class="rounded-full border border-white/10 bg-white/5 px-3 py-1">Sem instalação obrigatória</li>
                <li class="rounded-full border border-white/10 bg-white/5 px-3 py-1">Histórico no chassi</li>
            </ul>

            <dl class="mt-10 flex max-w-lg flex-wrap gap-x-8 gap-y-4 border-t border-white/10 pt-8">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-automotive-400">No veículo</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">Não some na venda</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-automotive-400">Procedência</dt>
                    <dd class="mt-1 text-sm font-semibold text-white">Selo ou declarada</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-automotive-400">Preço</dt>
                    <dd class="mt-1 text-sm font-semibold text-wrench-400">R$ 0 no lançamento</dd>
                </div>
            </dl>
        </div>

        <div class="relative mx-auto w-full max-w-sm landing-float lg:max-w-md" aria-hidden="true">
            <div class="absolute -inset-6 rounded-[2.5rem] bg-wrench-400/10 blur-2xl"></div>
            <div class="relative rounded-[2rem] border border-white/10 bg-automotive-950/80 p-3 shadow-2xl backdrop-blur">
                <x-landing.phone-timeline />
            </div>
        </div>
    </div>

    <div class="relative pb-6 text-center">
        <a href="#como-funciona" class="landing-bounce inline-flex flex-col items-center gap-1 text-xs text-automotive-400 transition hover:text-wrench-400">
            Role para saber mais
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </a>
    </div>

    @php
        $landingCapabilities = ['Histórico no chassi', 'Selo da oficina', 'Declaração do dono', 'PDF com notas', 'Busca pública', 'Diretório', 'App e web'];
    @endphp
    <div class="landing-marquee relative w-full border-t border-white/10 py-4" aria-label="O que o RevisaLog faz">
        <ul class="landing-marquee-track text-[11px] font-semibold uppercase tracking-[0.18em] text-automotive-300">
            @foreach (range(1, 4) as $copy)
                @foreach ($landingCapabilities as $capability)
                    <li class="flex shrink-0 items-center gap-4 px-4 whitespace-nowrap">
                        <span>{{ $capability }}</span>
                        <span class="text-wrench-500" aria-hidden="true">●</span>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
</section>

{{-- Como funciona --}}
<section id="como-funciona" class="scroll-mt-24 bg-white py-20">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Como funciona</p>
        <h2 class="mt-3 text-center text-3xl font-bold text-automotive-900 sm:text-4xl">Do cadastro ao PDF. O resto fica no carro.</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-automotive-600">O registro não é uma pasta no seu e-mail. Ele acompanha o veículo quando o dono muda.</p>

        <ol class="landing-reveal mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-landing-reveal>
            <li class="card relative overflow-hidden">
                <span class="text-4xl font-bold text-wrench-200">01</span>
                <h3 class="mt-3 text-lg font-semibold text-automotive-900">Cadastre o veículo</h3>
                <p class="mt-2 text-sm leading-relaxed text-automotive-600">Placa, chassi e RENAVAM. O histórico nasce no carro, não na sua conta.</p>
            </li>
            <li class="card relative overflow-hidden">
                <span class="text-4xl font-bold text-wrench-200">02</span>
                <h3 class="mt-3 text-lg font-semibold text-automotive-900">Registre a manutenção</h3>
                <p class="mt-2 text-sm leading-relaxed text-automotive-600">Você declara o serviço ou a oficina aplica o selo verificado — com nota e fotos, se quiser.</p>
            </li>
            <li class="card relative overflow-hidden">
                <span class="text-4xl font-bold text-wrench-200">03</span>
                <h3 class="mt-3 text-lg font-semibold text-automotive-900">Consulte quando precisar</h3>
                <p class="mt-2 text-sm leading-relaxed text-automotive-600">Busca por placa, chassi ou RENAVAM. A linha do tempo não some na transferência.</p>
            </li>
            <li class="card relative overflow-hidden">
                <span class="text-4xl font-bold text-wrench-200">04</span>
                <h3 class="mt-3 text-lg font-semibold text-automotive-900">Exporte o PDF</h3>
                <p class="mt-2 text-sm leading-relaxed text-automotive-600">Leve o relatório para a venda, o financiamento ou a próxima revisão — com notas anexadas.</p>
            </li>
        </ol>
    </div>
</section>

{{-- PDF --}}
<section class="border-t border-automotive-200 bg-automotive-50 py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 lg:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Relatório</p>
            <h2 class="mt-3 text-3xl font-bold text-automotive-900 sm:text-4xl">Um PDF que prova o que o carro já passou</h2>
            <p class="mt-4 max-w-xl text-automotive-600">Na hora de vender, financiar ou só organizar a gaveta, o histórico sai completo: serviços, quilometragem, selo da oficina e notas fiscais quando existirem.</p>
            <ul class="mt-6 space-y-3 text-sm text-automotive-800">
                <li class="flex gap-2"><span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span> Identidade do veículo no cabeçalho</li>
                <li class="flex gap-2"><span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span> Linha do tempo com procedência visível</li>
                <li class="flex gap-2"><span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span> Espaço para NF-e e fotos do serviço</li>
            </ul>
        </div>
        <div class="mx-auto w-full max-w-md landing-float" aria-hidden="true">
            <div class="rounded-2xl border border-automotive-200 bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-automotive-100 pb-3">
                    <p class="text-sm font-bold text-automotive-900">RevisaLog</p>
                    <p class="text-[11px] uppercase tracking-wide text-automotive-400">Histórico permanente</p>
                </div>
                <p class="mt-4 font-mono text-lg font-semibold text-automotive-900">ABC1D23</p>
                <p class="text-sm text-automotive-500">Honda Civic EX · 2022</p>
                <p class="mt-1 font-mono text-[11px] text-automotive-400">Chassi 93HFB1640NZ004251 · RENAVAM 00384719256</p>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between rounded-lg bg-automotive-50 px-3 py-2">
                        <span>Revisão 40 mil</span>
                        <span class="text-xs font-medium text-teal-800">Selo · NF-e · 40.012 km</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2">
                        <span>Pastilhas dianteiras</span>
                        <span class="text-xs font-medium text-amber-800">Declarada · 40.580 km</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-automotive-50 px-3 py-2">
                        <span>Alinhamento</span>
                        <span class="text-xs font-medium text-teal-800">Selo · 41.240 km</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-automotive-50 px-3 py-2">
                        <span>Troca de óleo 5W30</span>
                        <span class="text-xs font-medium text-teal-800">Selo · 42.180 km</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Busca --}}
<section class="bg-white py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 lg:grid-cols-2">
        <div class="order-2 mx-auto w-full max-w-md lg:order-1" aria-hidden="true">
            <div class="rounded-2xl border border-automotive-200 bg-white p-5 shadow-xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-automotive-400">Buscar histórico</p>
                <div class="mt-3 flex items-center gap-2 rounded-lg border border-automotive-300 bg-automotive-50 px-3 py-2.5 text-sm text-automotive-900">
                    <svg class="h-4 w-4 text-automotive-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 1 0 10.607 10.607Z" />
                    </svg>
                    ABC1D23
                </div>
                <p class="mt-2 text-[11px] text-automotive-400">Placa, chassi ou RENAVAM</p>
                <div class="mt-4 rounded-xl border border-automotive-200 p-4">
                    <p class="text-sm font-semibold text-automotive-900">Honda Civic EX</p>
                    <p class="font-mono text-xs text-automotive-500">ABC1D23</p>
                    <p class="mt-2 text-xs text-automotive-600">3 com selo · 1 declarada</p>
                </div>
            </div>
        </div>
        <div class="order-1 lg:order-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Consulta</p>
            <h2 class="mt-3 text-3xl font-bold text-automotive-900 sm:text-4xl">Ache o carro pela placa, chassi ou RENAVAM</h2>
            <p class="mt-4 max-w-xl text-automotive-600">Quem compra, vende ou revisa não precisa adivinhar. A busca pública abre a linha do tempo do veículo — com selo e declaração no lugar certo.</p>
        </div>
    </div>
</section>

{{-- Procedência --}}
<section id="procedencia" class="scroll-mt-24 border-t border-automotive-200 bg-automotive-50 py-20">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Procedência</p>
        <h2 class="mt-3 text-center text-3xl font-bold text-automotive-900 sm:text-4xl">Dá para ver o que é selo e o que é declaração</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-automotive-600">Não misturamos os dois. Quem compra, vende ou revisa o carro enxerga a origem de cada registro.</p>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <div class="card flex gap-4">
                <div class="prov-verified shrink-0">
                    <span class="prov-marker prov-marker--lg prov-marker--verified">S</span>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-800">Selo da oficina</p>
                    <h3 class="mt-1 text-xl font-semibold text-automotive-900">Serviço verificado</h3>
                    <p class="mt-2 text-sm leading-relaxed text-automotive-600">A oficina cadastrada confirma o trabalho. Anel teal cheio, código de verificação e, quando houver, nota fiscal anexada.</p>
                </div>
            </div>
            <div class="card flex gap-4">
                <div class="prov-declared shrink-0">
                    <span class="prov-marker prov-marker--lg prov-marker--declared">D</span>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Declarada</p>
                    <h3 class="mt-1 text-xl font-semibold text-automotive-900">Pelo proprietário ou lojista</h3>
                    <p class="mt-2 text-sm leading-relaxed text-automotive-600">Útil para o que você mesmo fez. Anel âmbar tracejado deixa claro que ainda não passou por uma oficina da rede.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Telas --}}
<section id="telas" class="scroll-mt-24 bg-white py-20">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Telas</p>
        <h2 class="mt-3 text-center text-3xl font-bold text-automotive-900 sm:text-4xl">Um olhar por dentro</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-automotive-600">A linha do tempo, a busca e o PDF — empilhados no celular, lado a lado no desktop.</p>

        <div class="landing-reveal mt-12 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4" data-landing-reveal>
            <figure class="rounded-[2rem] border border-automotive-800 bg-automotive-950 p-3 shadow-xl md:col-span-2 xl:col-span-2">
                <x-landing.phone-timeline compact />
                <figcaption class="mt-3 text-center text-xs text-automotive-400">Linha do tempo</figcaption>
            </figure>
            <figure class="flex flex-col rounded-[2rem] border border-automotive-200 bg-automotive-50 p-4 shadow-xl">
                <div class="flex-1 rounded-2xl border border-automotive-200 bg-white p-4" aria-hidden="true">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-automotive-400">Buscar histórico</p>
                    <div class="mt-3 rounded-lg border border-automotive-300 bg-automotive-50 px-3 py-2 font-mono text-sm">ABC1D23</div>
                    <p class="mt-2 text-[11px] text-automotive-400">Placa, chassi ou RENAVAM</p>
                    <div class="mt-4 rounded-xl border border-automotive-200 p-3">
                        <p class="text-sm font-semibold">Honda Civic EX</p>
                        <p class="font-mono text-xs text-automotive-500">ABC1D23 · 93HFB1640NZ004251</p>
                        <p class="mt-1 text-xs text-automotive-600">3 com selo · 1 declarada</p>
                    </div>
                </div>
                <figcaption class="mt-3 text-center text-xs text-automotive-500">Busca pública</figcaption>
            </figure>
            <figure class="flex flex-col rounded-[2rem] border border-automotive-200 bg-white p-4 shadow-xl">
                <div class="flex-1 rounded-2xl border border-automotive-100 bg-automotive-50 p-4" aria-hidden="true">
                    <p class="text-xs font-bold">RevisaLog · PDF</p>
                    <p class="mt-2 font-mono text-sm">ABC1D23</p>
                    <p class="text-[11px] text-automotive-500">Chassi 93HFB1640NZ004251</p>
                    <div class="mt-4 space-y-2 text-xs">
                        <p class="rounded bg-white px-2 py-1.5">Revisão 40 mil · 40.012 km · NF-e</p>
                        <p class="rounded border border-dashed border-amber-300 bg-amber-50 px-2 py-1.5">Pastilhas · 40.580 km · declarada</p>
                        <p class="rounded bg-white px-2 py-1.5">Alinhamento · 41.240 km · selo</p>
                        <p class="rounded bg-white px-2 py-1.5">Óleo 5W30 · 42.180 km · selo</p>
                    </div>
                </div>
                <figcaption class="mt-3 text-center text-xs text-automotive-500">Exportar PDF</figcaption>
            </figure>
        </div>
    </div>
</section>

{{-- Para quem --}}
<section id="para-quem" class="scroll-mt-24 border-t border-automotive-200 bg-automotive-50 py-20">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Públicos</p>
        <h2 class="mt-3 text-center text-3xl font-bold text-automotive-900 sm:text-4xl">Para quem é o sistema?</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-automotive-600">Um histórico só. Três entradas — dono, loja e oficina — sem misturar o que cada um pode fazer.</p>

        <div class="landing-reveal mt-12 grid gap-6 lg:grid-cols-3" data-landing-reveal>
            <article class="card flex flex-col overflow-hidden !p-0 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div class="h-1.5 bg-wrench-500"></div>
                <div class="flex flex-1 flex-col p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">01 · Proprietário</p>
                    <h3 class="mt-2 text-2xl font-bold text-automotive-900">Dono do carro</h3>
                    <p class="mt-2 text-sm text-automotive-600">O histórico nasce no chassi. Você declara o que fez, pede o selo na oficina e leva o PDF na venda.</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-automotive-800">
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Cadastro por placa, chassi e RENAVAM</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Declaração própria, visível como declarada</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> PDF com notas para financiar ou vender</li>
                    </ul>
                    @guest<a href="{{ route('register') }}" class="btn-primary mt-8">Cadastrar como usuário</a>@endguest
                </div>
            </article>

            <article class="card flex flex-col overflow-hidden !p-0 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div class="h-1.5 bg-automotive-700"></div>
                <div class="flex flex-1 flex-col p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">02 · Lojista</p>
                    <h3 class="mt-2 text-2xl font-bold text-automotive-900">Garagens</h3>
                    <p class="mt-2 text-sm text-automotive-600">Estoque com histórico que não some na transferência. Revisão pré-venda documentada no veículo, não na planilha.</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-automotive-800">
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Frota e veículos à venda no mesmo lugar</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Registro declarado até a oficina aplicar o selo</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Relatório para o comprador levar embora</li>
                    </ul>
                    @guest<a href="{{ route('login.lojista') }}" class="btn-secondary mt-8">Entrar como lojista</a>@endguest
                </div>
            </article>

            <article class="card flex flex-col overflow-hidden !p-0 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div class="h-1.5 bg-teal-700"></div>
                <div class="flex flex-1 flex-col p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-automotive-500">03 · Oficina</p>
                    <h3 class="mt-2 text-2xl font-bold text-automotive-900">Oficinas</h3>
                    <p class="mt-2 text-sm text-automotive-600">O selo é o seu nome no histórico do carro. Apareça no diretório e confirme o serviço com nota e fotos.</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-automotive-800">
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Selo verificado, anel teal, código único</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Diretório público para quem busca oficina</li>
                        <li class="flex gap-2"><span class="text-wrench-600" aria-hidden="true">✓</span> Serviços acompanhados depois da OS</li>
                    </ul>
                    @guest<a href="{{ route('login.oficina') }}" class="btn-secondary mt-8">Entrar como oficina</a>@endguest
                </div>
            </article>
        </div>
    </div>
</section>

{{-- Funcionalidades --}}
<section id="recursos" class="scroll-mt-24 bg-white py-20">
    <div class="landing-reveal mx-auto max-w-7xl px-4" data-landing-reveal>
        <div class="grid items-end gap-8 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Produto</p>
                <h2 class="mt-3 text-3xl font-bold text-automotive-900 sm:text-4xl">Funcionalidades que o histórico realmente usa</h2>
                <p class="mt-4 text-automotive-600">Não é uma lista de ícones. É o que fica no chassi: selo, declaração, busca e o PDF que viaja com o carro.</p>
                @guest
                    <a href="{{ route('register') }}" class="btn-primary mt-6">Começar grátis</a>
                @endguest
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:col-span-7">
                <article class="card transition duration-300 hover:-translate-y-1 hover:shadow-md sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-wrench-700">Histórico no chassi</p>
                    <h3 class="mt-2 text-xl font-semibold text-automotive-900">Linha do tempo permanente</h3>
                    <p class="mt-2 text-sm leading-relaxed text-automotive-600">Cada serviço fica no veículo, não na sua conta. Quem compra lê a mesma sequência — selo teal, declaração âmbar — com km que só cresce.</p>
                </article>
                <article class="card transition duration-300 hover:-translate-y-1 hover:shadow-md">
                    <h3 class="font-semibold text-automotive-900">Selo da oficina</h3>
                    <p class="mt-2 text-sm text-automotive-600">Oficina cadastrada confirma o trabalho. Anel cheio, código de verificação, nota quando houver.</p>
                </article>
                <article class="card transition duration-300 hover:-translate-y-1 hover:shadow-md">
                    <h3 class="font-semibold text-automotive-900">Exportar PDF</h3>
                    <p class="mt-2 text-sm text-automotive-600">Relatório com placa, chassi, RENAVAM e notas fiscais para venda ou financiamento.</p>
                </article>
                <article class="card transition duration-300 hover:-translate-y-1 hover:shadow-md">
                    <h3 class="font-semibold text-automotive-900">Busca pública</h3>
                    <p class="mt-2 text-sm text-automotive-600">Consulta por placa, chassi ou RENAVAM. O histórico abre no identificador do carro.</p>
                </article>
                <article class="card transition duration-300 hover:-translate-y-1 hover:shadow-md">
                    <h3 class="font-semibold text-automotive-900">Diretório e app</h3>
                    <p class="mt-2 text-sm text-automotive-600">Oficinas no mapa da rede. O mesmo histórico no navegador do celular e no app nativo.</p>
                </article>
            </div>
        </div>
    </div>
</section>

{{-- App --}}
<section id="app" class="scroll-mt-24 relative overflow-hidden">
    <div class="absolute inset-0 bg-automotive-950"></div>
    <div class="absolute -left-16 top-0 h-72 w-72 rounded-full bg-wrench-500/15 blur-3xl"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 lg:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-wrench-400">No celular</p>
            <h2 class="mt-3 text-3xl font-bold text-white sm:text-4xl">O app de verdade, não um mock</h2>
            <p class="mt-4 max-w-xl text-automotive-300">Telas reais do RevisaLog no iPhone: login por perfil, ficha do veículo com chassi e RENAVAM, linha do tempo e lista de manutenções. O mesmo histórico do site.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @guest
                    <a href="{{ route('register') }}" class="btn-primary !px-5 !py-3">Começar no celular</a>
                @else
                    <a href="{{ $dashRoute }}" class="btn-primary !px-5 !py-3">Ir para o painel</a>
                @endguest
                <a href="{{ route('vehicle.search') }}" class="btn-secondary !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">Buscar um veículo</a>
            </div>
            <p class="mt-4 text-xs text-automotive-500">Sem link de loja por enquanto. Cadastro e consulta já abrem no navegador do telefone.</p>
        </div>
        <div class="relative mx-auto flex min-h-[22rem] w-full max-w-lg items-end justify-center pb-4 sm:min-h-[32rem]">
            <x-landing.app-phone
                class="landing-float absolute left-0 top-10 hidden w-40 -rotate-12 sm:block lg:w-44"
                src="{{ \App\Support\AppStorage::landingUrl('app-login.png') }}"
                alt="App RevisaLog: escolha de portal para entrar"
            />
            <x-landing.app-phone
                class="landing-float relative z-10 w-52 sm:w-56"
                src="{{ \App\Support\AppStorage::landingUrl('app-vehicle.png') }}"
                alt="App RevisaLog: ficha do veículo com linha do tempo"
            />
            <x-landing.app-phone
                class="landing-float landing-float-delay absolute right-0 top-16 hidden w-40 rotate-12 sm:block lg:w-44"
                src="{{ \App\Support\AppStorage::landingUrl('app-maintenances.png') }}"
                alt="App RevisaLog: lista de manutenções"
            />
        </div>
    </div>
</section>

{{-- Preço --}}
<section id="preco" class="scroll-mt-24 border-t border-automotive-200 bg-automotive-50 py-20">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-wrench-700">Preço</p>
        <h2 class="mt-3 text-center text-3xl font-bold text-automotive-900 sm:text-4xl">Grátis enquanto a rede cresce</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-automotive-600">Ainda não cobramos. Não há tabela de planos — e inventar uma agora seria marketing vazio. O produto está aberto para construir histórico de verdade.</p>

        <div class="mx-auto mt-12 max-w-lg">
            <div class="card relative overflow-hidden border-wrench-400/40 ring-1 ring-wrench-500/20">
                <div class="absolute right-4 top-4 rounded-full bg-wrench-500 px-3 py-1 text-xs font-semibold text-automotive-950">Lançamento</div>
                <p class="text-sm font-semibold uppercase tracking-wide text-wrench-700">RevisaLog</p>
                <p class="mt-3 flex items-end gap-2">
                    <span class="text-5xl font-bold tracking-tight text-automotive-900">R$ 0</span>
                    <span class="pb-1 text-sm text-automotive-500">por mês, por agora</span>
                </p>
                <p class="mt-2 text-sm text-automotive-600">Sem cartão. Sem limite artificial de veículos no lançamento.</p>
                <ul class="mt-6 space-y-3 text-sm text-automotive-800">
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span>
                        Histórico permanente no veículo
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span>
                        Selo da oficina e registros declarados
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span>
                        Exportar PDF com notas fiscais
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-wrench-600" aria-hidden="true">✓</span>
                        Busca por placa, chassi ou RENAVAM
                    </li>
                </ul>
                @guest
                    <a href="{{ route('register') }}" class="btn-primary mt-8 w-full">Começar grátis</a>
                @else
                    <a href="{{ $dashRoute }}" class="btn-primary mt-8 w-full">Ir para o painel</a>
                @endguest
            </div>
            <p class="mt-6 text-center text-xs leading-relaxed text-automotive-500">
                Quando houver planos pagos, avisaremos com antecedência. Oficinas e garagens poderão ter opções próprias; o essencial para o dono do carro continua sendo registrar e consultar o histórico.
            </p>
        </div>
    </div>
</section>

{{-- FAQ --}}
<section id="faq" class="scroll-mt-24 bg-white py-20">
    <div class="mx-auto max-w-3xl px-4">
        <h2 class="text-center text-3xl font-bold text-automotive-900">Perguntas frequentes</h2>
        <div class="mt-10 space-y-3">
            <x-landing.faq-item question="É realmente de graça?">
                Sim. Estamos na fase de lançamento e ainda não cobramos. Não publicamos preços futuros porque ainda não existem.
            </x-landing.faq-item>
            <x-landing.faq-item question="O histórico muda de dono junto com o carro?">
                O registro fica no veículo. Quando a propriedade muda, o histórico continua consultável por placa, chassi ou RENAVAM.
            </x-landing.faq-item>
            <x-landing.faq-item question="Qual a diferença entre selo e declaração?">
                Selo da oficina é o serviço confirmado por uma oficina cadastrada. Declaração é o que o proprietário ou lojista registrou sem essa verificação.
            </x-landing.faq-item>
            <x-landing.faq-item question="Oficina e garagem também podem usar?">
                Sim. Oficinas aplicam o selo e aparecem no diretório. Garagens documentam revisões pré-venda no estoque.
            </x-landing.faq-item>
            <x-landing.faq-item question="Tem aplicativo?">
                O site já funciona no celular. O app nativo usa o mesmo histórico. Ainda não publicamos links de loja — o cadastro abre no navegador.
            </x-landing.faq-item>
        </div>
    </div>
</section>

{{-- CTA final --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-automotive-950"></div>
    <div class="absolute right-0 top-0 h-64 w-64 bg-wrench-500/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-3xl px-4 py-20 text-center">
        <h2 class="text-3xl font-bold text-white sm:text-4xl">Comece pelo primeiro veículo</h2>
        <p class="mt-4 text-automotive-300">Quanto antes o histórico existir, mais valor o carro carrega na próxima venda — e na próxima revisão.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @auth
                <a href="{{ $dashRoute }}" class="btn-primary !px-5 !py-3">Ir para o painel</a>
            @else
                <a href="{{ route('register') }}" class="btn-primary !px-5 !py-3">Começar grátis</a>
                <a href="{{ route('login') }}" class="btn-secondary !border-automotive-600 !bg-transparent !text-white hover:!bg-automotive-800">Já tenho conta</a>
            @endauth
        </div>
    </div>
</section>
</div>
@endsection
