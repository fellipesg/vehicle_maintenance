@extends('layouts.app')

@section('title', 'Para oficinas')

@php
    // Variáveis (WorkshopLandingController): $signupUrl (cadastro, já com ?ref= quando o link veio do
    // e-mail de prospecção) e $followUps (os gatilhos de WorkshopMessageTrigger).
    $landingPortal = \App\Enums\Portal::current(auth()->user());
    $landingHomeUrl = $landingPortal ? route($landingPortal->dashboardRoute()) : null;
    $contactUrl = route('contact.show', ['assunto' => 'partnership']);
    $benefits = [
        ['icon' => 'shield-check', 'title' => 'Selo da oficina em cada serviço', 'text' => 'O serviço confirmado pela sua oficina fica no histórico do carro com o nome de vocês e um código de conferência.'],
        ['icon' => 'map-pin', 'title' => 'Listada em Oficinas da rede', 'text' => 'Quem procura uma oficina no RevisaLog encontra a sua, com endereço, telefone e WhatsApp.'],
        ['icon' => 'wrench-screwdriver', 'title' => 'OS com fotos, itens, garantia e nota fiscal', 'text' => 'A ordem de serviço guarda o que foi feito, as peças, as fotos de antes e depois, a garantia e a nota fiscal.'],
        ['icon' => 'check-circle', 'title' => 'Fila de validação', 'text' => 'Quando um cliente registra um serviço feito na sua oficina, ele aparece para você confirmar e emitir o Selo da oficina.'],
    ];
@endphp

@section('meta_description', \App\Support\Seo\WorkshopFaq::metaDescription())

@push('head')
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::webPage('Para oficinas', \App\Support\Seo\WorkshopFaq::metaDescription(), \App\Support\Seo\CanonicalUrl::named('workshops.landing'))" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::breadcrumb([
        ['name' => 'Início', 'url' => \App\Support\Seo\CanonicalUrl::home()],
        ['name' => 'Para oficinas', 'url' => \App\Support\Seo\CanonicalUrl::named('workshops.landing')],
    ])" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::faqPage(\App\Support\Seo\WorkshopFaq::items())" />
@endpush

{{--
    Página pública para oficinas: Hero → O que a oficina recebe → Como funciona → Preço → FAQ → CTA
    final. Mesmos componentes x-landing.* da home; o cadastro fica em /para-oficinas/cadastro e some
    para quem já está logado. Regras em .ai/rules/landing-views.md.
--}}
@section('content')
<div data-landing-page>
<section class="theme-inverse relative overflow-hidden" aria-labelledby="oficinas-titulo">
    <div class="absolute inset-0 bg-gradient-to-br from-automotive-900 via-automotive-800 to-automotive-950"></div>
    <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]"></div>
    <div class="absolute -top-24 -right-24 h-[28rem] w-[28rem] rounded-full bg-wrench-500/15 blur-3xl"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-10 sm:px-6 sm:py-16 lg:grid-cols-2 lg:gap-12 lg:py-24">
        <div>
            <x-ui.breadcrumb class="mb-5" :items="[['Início', \App\Support\Seo\CanonicalUrl::home()], ['Para oficinas']]" />
            <p class="landing-intro mb-5 inline-flex items-center gap-2 rounded-full bg-accent px-3.5 py-1.5 text-sm font-semibold text-accent-foreground ring-1 ring-accent-border ring-inset">
                <x-ui.icon name="wrench-screwdriver" class="size-4" />
                Para oficinas
            </p>
            <h1 id="oficinas-titulo" class="landing-intro text-4xl leading-tight font-bold tracking-tight text-balance text-foreground [--landing-intro-delay:40ms] sm:text-5xl lg:text-6xl">
                O nome da sua oficina
                <span class="text-accent-foreground">fica no histórico do carro</span>
            </h1>
            <p class="landing-intro mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground [--landing-intro-delay:100ms]">
                Cada serviço confirmado pela oficina recebe o Selo da oficina, com um código que qualquer pessoa confere em <x-ui.link :href="route('verification.lookup')" variant="inline">revisalog.com.br/verificar</x-ui.link>. O carro muda de dono, o seu serviço continua lá.
            </p>

            <div class="landing-intro mt-8 flex flex-col gap-3 [--landing-intro-delay:160ms] sm:flex-row sm:items-start">
                @auth
                    <x-landing.cta :href="$landingHomeUrl" class="max-sm:w-full">Ir para o Início</x-landing.cta>
                @else
                    <x-landing.cta :href="$signupUrl" class="max-sm:w-full">Cadastrar minha oficina</x-landing.cta>
                    <x-ui.button variant="secondary" size="lg" :href="$contactUrl" class="max-sm:w-full">Falar com a equipe</x-ui.button>
                @endauth
            </div>
            @guest
                <p class="mt-4 text-sm text-muted-foreground">Grátis no lançamento. Já tem conta? <x-ui.link :href="route('login.oficina')" variant="inline">Entrar como oficina</x-ui.link></p>
            @endguest
        </div>

        <div class="relative mx-auto w-full max-w-sm lg:max-w-md" aria-hidden="true">
            <div class="absolute -inset-6 rounded-[2.5rem] bg-wrench-400/10 blur-2xl"></div>
            <div class="relative max-h-[26rem] overflow-hidden rounded-[2rem] border border-white/10 bg-automotive-950/80 p-3 shadow-2xl [mask-image:linear-gradient(to_bottom,#000_75%,transparent)] sm:max-h-none sm:[mask-image:none]">
                <x-landing.phone-timeline />
            </div>
        </div>
    </div>
</section>

<section id="beneficios" class="scroll-mt-24 bg-surface py-16 sm:py-20" aria-labelledby="beneficios-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">O que a oficina recebe</p>
        <h2 id="beneficios-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Tudo isso já funciona hoje</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">Sem prometer o que ainda não existe: estas são as ferramentas da conta da oficina.</p>

        <div class="landing-reveal mt-12 grid gap-6 sm:grid-cols-2" data-landing-reveal>
            @foreach ($benefits as $benefit)
                <x-ui.card as="article" padding="lg">
                    <div class="flex gap-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground ring-1 ring-accent-border ring-inset">
                            <x-ui.icon :name="$benefit['icon']" class="size-5" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">{{ $benefit['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $benefit['text'] }}</p>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card padding="lg" class="mt-6">
            <div class="flex gap-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent text-accent-foreground ring-1 ring-accent-border ring-inset">
                    <x-ui.icon name="chat-bubble-left-right" class="size-5" />
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-foreground">Mensagens automáticas para os clientes</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">A oficina escolhe o texto e a RevisaLog avisa o cliente na hora certa, por e-mail e notificação. Hoje são duas situações:</p>
                    <ul role="list" class="mt-4 space-y-2.5 text-sm text-foreground">
                        @foreach ($followUps as $trigger)
                            <li class="flex gap-2">
                                <x-ui.icon name="check" class="mt-px size-4 shrink-0 text-link" />
                                <span><strong class="font-semibold">{{ $trigger->label() }}.</strong> {{ $trigger->description() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-ui.card>
    </x-ui.container>
</section>

<section id="como-funciona" class="scroll-mt-24 border-t border-border bg-background py-16 sm:py-20" aria-labelledby="como-funciona-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Como funciona</p>
        <h2 id="como-funciona-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Três passos para aparecer no histórico</h2>

        <ol class="group/rail mx-auto mt-12 grid max-w-md lg:max-w-none lg:grid-cols-3 lg:gap-6" data-landing-rail>
            <x-landing.step :number="1" title="Cadastre a oficina">Nome, CNPJ, WhatsApp e endereço. O CEP preenche a rua, e a conta fica pronta na hora.</x-landing.step>
            <x-landing.step :number="2" title="Registre a OS">Informe o veículo, os itens, as fotos, a garantia e a nota fiscal. Ou confirme o serviço que o cliente registrou.</x-landing.step>
            <x-landing.step :number="3" title="O Selo vai para o histórico" last>O serviço entra na linha do tempo do carro com o nome da oficina e um código que qualquer pessoa confere.</x-landing.step>
        </ol>
    </x-ui.container>
</section>

<section id="preco" class="scroll-mt-24 border-t border-border bg-surface py-16 sm:py-20" aria-labelledby="preco-titulo">
    <x-ui.container size="md">
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Preço</p>
        <h2 id="preco-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Grátis no lançamento</h2>
        <p class="mx-auto mt-3 max-w-xl text-center text-muted-foreground">No lançamento, a oficina usa o RevisaLog sem custo. Se no futuro houver planos pagos, avisamos com antecedência, e nada é cobrado sem a oficina contratar.</p>
    </x-ui.container>
</section>

<section id="faq" class="scroll-mt-24 border-t border-border bg-background py-16 sm:py-20" aria-labelledby="faq-titulo">
    <x-ui.container size="md">
        <h2 id="faq-titulo" class="text-center text-3xl font-bold text-foreground">Perguntas frequentes</h2>
        <x-seo.faq-list :items="\App\Support\Seo\WorkshopFaq::items()" />
    </x-ui.container>
</section>

<section class="theme-inverse relative overflow-hidden" aria-labelledby="cta-final-titulo">
    <div class="absolute inset-0 bg-automotive-950"></div>
    <div class="absolute top-0 right-0 h-64 w-64 bg-wrench-500/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 sm:py-20">
        <h2 id="cta-final-titulo" class="text-3xl font-bold text-balance text-foreground sm:text-4xl">Ponha o nome da sua oficina no histórico</h2>
        <p class="mt-4 text-muted-foreground">O cadastro leva poucos minutos e não pede cartão.</p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            @auth
                <x-landing.cta :href="$landingHomeUrl">Ir para o Início</x-landing.cta>
            @else
                <x-landing.cta :href="$signupUrl">Cadastrar minha oficina</x-landing.cta>
                <x-ui.button variant="secondary" size="lg" :href="$contactUrl">Falar com a equipe</x-ui.button>
            @endauth
        </div>
    </div>
</section>
</div>
@endsection
