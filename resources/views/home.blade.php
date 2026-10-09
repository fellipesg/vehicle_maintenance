@extends('layouts.app')

@section('title', 'Histórico permanente do seu carro')

@php
    // Destino de "Ir para o Início": o mesmo do logo na navbar (App\Enums\Portal), sem match em user_type.
    $landingPortal = \App\Enums\Portal::current(auth()->user());
    $landingHomeUrl = $landingPortal ? route($landingPortal->dashboardRoute()) : null;
    $landingPartnershipUrl = route('contact.show', ['assunto' => 'partnership']);
    $landingCapabilities = ['Histórico no chassi', 'Selo da oficina', 'Declaração do dono', 'PDF com notas fiscais', 'Busca por placa, chassi ou RENAVAM', 'Oficinas da rede', 'App e navegador'];
@endphp

@push('head')
    <meta name="description" content="RevisaLog: histórico permanente de manutenções vinculado ao veículo. Selo da oficina, busca por placa, chassi ou RENAVAM e PDF com notas. Grátis no lançamento.">
    <meta property="og:title" content="Histórico permanente do seu carro">
    <meta property="og:description" content="RevisaLog: histórico permanente de manutenções vinculado ao veículo. Selo da oficina, busca por placa, chassi ou RENAVAM e PDF com notas. Grátis no lançamento.">
@endpush

{{--
    Landing em 9 seções: Hero → Como funciona → Procedência → Produto → Para quem → App → Preço →
    FAQ → CTA final. Um momento de movimento por seção, todos com prefers-reduced-motion: entrada
    do hero (landing-intro), trilho de "Como funciona", revelação de Procedência e Para quem, troca
    de aba do Produto. Scripts em resources/js/landing.js; regras em .ai/rules/landing-views.md.
--}}
@section('content')
<div data-landing-page>
{{-- Hero --}}
<section class="theme-inverse relative overflow-hidden" aria-labelledby="landing-titulo" data-landing-hero>
    <div class="absolute inset-0 bg-gradient-to-br from-automotive-900 via-automotive-800 to-automotive-950"></div>
    <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]"></div>
    <div class="absolute -top-24 -right-24 h-[28rem] w-[28rem] rounded-full bg-wrench-500/15 blur-3xl"></div>
    <div class="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-wrench-400/10 blur-3xl"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-10 sm:px-6 sm:py-16 lg:grid-cols-2 lg:gap-12 lg:py-24">
        <div>
            <p class="landing-intro mb-5 inline-flex items-center gap-2 rounded-full bg-accent px-3.5 py-1.5 text-sm font-semibold text-accent-foreground ring-1 ring-accent-border ring-inset" data-landing-badge>
                <x-ui.icon name="shield-check" class="size-4" />
                Histórico que fica no veículo
            </p>
            <h1 id="landing-titulo" class="landing-intro text-4xl leading-tight font-bold tracking-tight text-balance text-foreground [--landing-intro-delay:40ms] sm:text-5xl lg:text-6xl">
                O histórico do carro
                <span class="text-accent-foreground">viaja com o carro</span>
            </h1>
            <p class="landing-intro mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground [--landing-intro-delay:100ms]">
                Cada manutenção fica vinculada ao chassi, não ao dono. A oficina confirma o serviço com o Selo da oficina, você declara o que fez, e o histórico passa para o próximo dono na venda.
            </p>

            <div class="landing-intro mt-8 flex flex-col gap-3 [--landing-intro-delay:160ms] sm:flex-row sm:items-start" data-landing-hero-actions>
                @auth
                    <x-landing.cta :href="$landingHomeUrl" class="max-sm:w-full">Ir para o Início</x-landing.cta>
                    <x-ui.button variant="secondary" size="lg" icon="magnifying-glass" :href="route('vehicle.search')" class="max-sm:w-full">Consultar um veículo</x-ui.button>
                @else
                    <x-landing.cta :href="route('register')" class="max-sm:w-full">Começar grátis</x-landing.cta>
                    <div class="flex flex-col items-center gap-1.5 sm:items-start">
                        <x-ui.button variant="secondary" size="lg" icon="lock-closed" :href="route('vehicle.search')" aria-describedby="landing-consulta-nota" class="max-sm:w-full">Consultar um veículo</x-ui.button>
                        <p id="landing-consulta-nota" class="text-sm text-muted-foreground">Grátis, com conta</p>
                    </div>
                @endauth
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2" data-landing-app-badges>
                <x-landing.app-store-badge />
                <p class="text-sm text-muted-foreground">Android em breve</p>
            </div>

            <dl class="mt-10 hidden max-w-lg flex-wrap gap-x-8 gap-y-4 border-t border-border pt-8 sm:flex">
                <div>
                    <dt class="text-xs tracking-wide text-muted-foreground uppercase">No veículo</dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">Não some na venda</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-muted-foreground uppercase">Procedência</dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">Selo ou declarada</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-muted-foreground uppercase">Preço</dt>
                    <dd class="mt-1 text-sm font-semibold text-accent-foreground">R$ 0 no lançamento</dd>
                </div>
            </dl>
        </div>

        {{-- No celular o mock mostra a identificação e os primeiros serviços (do mais antigo para o
             mais novo) e some num degradê, para o hero não passar de duas telas. --}}
        <div class="relative mx-auto w-full max-w-sm lg:max-w-md" aria-hidden="true">
            <div class="landing-float" data-landing-loop>
                <div class="absolute -inset-6 rounded-[2.5rem] bg-wrench-400/10 blur-2xl"></div>
                <div class="relative max-h-[26rem] overflow-hidden rounded-[2rem] border border-white/10 bg-automotive-950/80 p-3 shadow-2xl [mask-image:linear-gradient(to_bottom,#000_75%,transparent)] sm:max-h-none sm:[mask-image:none]">
                    <x-landing.phone-timeline />
                </div>
            </div>
        </div>
    </div>

    <div class="relative hidden pb-6 text-center lg:block">
        <a href="#como-funciona" class="landing-bounce inline-flex flex-col items-center gap-1 rounded-control text-sm text-muted-foreground transition-colors duration-fast hover:text-foreground motion-reduce:transition-none [animation-iteration-count:3]">
            Veja como funciona
            <x-ui.icon name="chevron-down" class="size-4" />
        </a>
    </div>

    {{-- Faixa de capacidades: marquee RTL na largura toda (.ai/rules/views.md), 4 cópias e só a
         primeira legível. O botão pausa a faixa e a flutuação do mock (WCAG 2.2.2); o hover e o foco
         também pausam a faixa. Com prefers-reduced-motion nada anda e o botão não aparece. --}}
    <div class="relative w-full border-t border-border">
        <div class="landing-marquee relative w-full py-4">
            <ul class="landing-marquee-track text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase" aria-label="O que o RevisaLog faz" data-landing-marquee-track>
                @foreach (range(1, 4) as $copy)
                    @foreach ($landingCapabilities as $capability)
                        <li class="flex shrink-0 items-center gap-4 px-4 whitespace-nowrap" @if ($copy > 1) aria-hidden="true" @endif>
                            <span>{{ $capability }}</span>
                            <span class="size-1.5 rounded-full bg-primary" aria-hidden="true"></span>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
        <button
            type="button"
            class="absolute top-1/2 right-3 z-10 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full border border-border-strong bg-background text-muted-foreground transition-colors duration-fast hover:text-foreground motion-reduce:transition-none"
            aria-label="Pausar animação"
            aria-pressed="false"
            data-landing-marquee-toggle
            hidden
        >
            <x-ui.icon name="pause" variant="solid" class="size-4" data-landing-marquee-icon="pause" />
            <x-ui.icon name="play" variant="solid" class="size-4" data-landing-marquee-icon="play" hidden />
        </button>
    </div>
</section>

{{-- Como funciona --}}
<section id="como-funciona" class="scroll-mt-24 bg-surface py-16 sm:py-20" aria-labelledby="como-funciona-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Como funciona</p>
        <h2 id="como-funciona-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Quatro passos, e o histórico fica no carro</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">O registro não fica numa pasta do seu e-mail: ele acompanha o veículo quando o dono muda.</p>

        <ol class="group/rail mx-auto mt-12 grid max-w-md lg:max-w-none lg:grid-cols-4 lg:gap-6" data-landing-rail>
            <x-landing.step :number="1" title="Cadastre o veículo">Placa, chassi e RENAVAM. O histórico nasce no carro, não na sua conta.</x-landing.step>
            <x-landing.step :number="2" title="Registre cada serviço">Você declara o que fez, ou a oficina aplica o Selo da oficina, com nota fiscal e fotos quando houver.</x-landing.step>
            <x-landing.step :number="3" title="Consulte quando precisar">Pela placa, pelo chassi ou pelo RENAVAM. A linha do tempo continua lá depois da transferência.</x-landing.step>
            <x-landing.step :number="4" title="Exporte o PDF" last>Leve o relatório para a venda, o financiamento ou a próxima revisão, com as notas anexadas.</x-landing.step>
        </ol>
    </x-ui.container>
</section>

{{-- Procedência --}}
<section id="procedencia" class="scroll-mt-24 border-t border-border bg-background py-16 sm:py-20" aria-labelledby="procedencia-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Procedência</p>
        <h2 id="procedencia-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Dá para ver o que é selo e o que é declaração</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">Cada registro mostra quem o fez. Quem compra, vende ou revisa o carro sabe em que se apoiar.</p>

        <div class="landing-reveal mt-12 grid gap-6 lg:grid-cols-2" data-landing-reveal>
            <x-ui.card as="article" padding="lg" aria-labelledby="procedencia-selo-titulo">
                <div class="flex gap-4">
                    <x-landing.provenance-glyph size="lg" />
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-prov-verified uppercase">Selo da oficina</p>
                        <h3 id="procedencia-selo-titulo" class="mt-1 text-xl font-semibold text-foreground">Pela oficina que fez o serviço</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">A oficina da rede confirma o trabalho no próprio RevisaLog. O disco teal cheio, o código de conferência e a nota fiscal, quando houver, ficam no registro.</p>
                    </div>
                </div>
            </x-ui.card>
            <x-ui.card as="article" padding="lg" aria-labelledby="procedencia-declarada-titulo">
                <div class="flex gap-4">
                    <x-landing.provenance-glyph :sealed="false" size="lg" />
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-prov-declared uppercase">Declarada</p>
                        <h3 id="procedencia-declarada-titulo" class="mt-1 text-xl font-semibold text-foreground">Pelo proprietário ou lojista</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">Para o que você mesmo fez ou já tinha no papel. O anel âmbar tracejado mostra que nenhuma oficina da rede confirmou o serviço.</p>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- /verificar é a única consulta aberta a qualquer visitante: o código vem no PDF e no QR. --}}
        <div class="mt-10 flex flex-col items-center gap-3 text-center" data-landing-verify>
            <x-ui.button variant="secondary" icon="shield-check" :href="route('verification.lookup')">Conferir selo da oficina</x-ui.button>
            <p class="max-w-md text-sm text-muted-foreground">Recebeu um PDF ou um QR code? Digite o código do selo para ver a oficina e a data, sem precisar de conta.</p>
        </div>
    </x-ui.container>
</section>

{{-- Produto: split-showcase do ObsidianUI em abas (x-ui.tabs, setas do teclado em resources/js/ui/tabs.js). --}}
<section id="produto" class="scroll-mt-24 bg-surface py-16 sm:py-20" aria-labelledby="produto-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Produto</p>
        <h2 id="produto-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">O histórico como ele aparece para você</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">A linha do tempo, a busca e o relatório em PDF mostram os mesmos dados, na mesma ordem.</p>

        <x-ui.tabs label="Telas do produto" class="mt-10 flex flex-col items-center gap-4" data-landing-showcase>
            <x-slot:tabs>
                <x-ui.tab target="produto-linha-do-tempo" :active="true" icon="clock">Linha do tempo</x-ui.tab>
                <x-ui.tab target="produto-busca" icon="magnifying-glass">Busca</x-ui.tab>
                <x-ui.tab target="produto-pdf" icon="document-text">PDF</x-ui.tab>
            </x-slot:tabs>

            <x-landing.showcase-panel
                id="produto-linha-do-tempo"
                :active="true"
                title="Linha do tempo do veículo"
                :points="['Quilometragem sempre em ordem crescente', 'Selo da oficina ou declarada em cada serviço', 'Itens, valores e garantia de cada serviço']"
            >
                Do serviço mais antigo ao mais recente, com a quilometragem subindo. A próxima revisão estimada aparece no fim da linha.
                <x-slot:frame>
                    <x-landing.app-phone
                        class="mx-auto w-52 sm:w-60"
                        :src="\App\Support\AppStorage::landingUrl('app-timeline.png')"
                        alt="App RevisaLog: linha do tempo do veículo, da manutenção mais antiga à mais recente"
                    />
                </x-slot:frame>
            </x-landing.showcase-panel>

            <x-landing.showcase-panel
                id="produto-busca"
                title="Ache o carro pela placa, chassi ou RENAVAM"
                :points="['Placa atual ou antiga, chassi ou RENAVAM', 'Chassi e RENAVAM parciais para quem não é o dono', 'Procedência de cada serviço antes de abrir a ficha']"
            >
                Com uma conta gratuita, a busca abre a linha do tempo do veículo. O código de um selo qualquer pessoa confere, sem conta.
                <x-slot:frame>
                    <x-landing.search-mock />
                </x-slot:frame>
            </x-landing.showcase-panel>

            <x-landing.showcase-panel
                id="produto-pdf"
                title="Um PDF que prova o que o carro já passou"
                :points="['Placa, chassi e RENAVAM no cabeçalho', 'Serviços em ordem, com a procedência de cada um', 'Notas fiscais anexadas quando existirem']"
            >
                Para vender, financiar ou só organizar a papelada, o histórico sai completo: serviços, quilometragem, Selo da oficina e notas fiscais.
                <x-slot:frame>
                    <x-landing.pdf-mock />
                </x-slot:frame>
            </x-landing.showcase-panel>
        </x-ui.tabs>
    </x-ui.container>
</section>

{{-- Para quem --}}
<section id="para-quem" class="scroll-mt-24 border-t border-border bg-background py-16 sm:py-20" aria-labelledby="para-quem-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Para quem</p>
        <h2 id="para-quem-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Para quem é o RevisaLog</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">Um histórico só, com três entradas. Cada perfil faz o que é dele, sem misturar.</p>

        <div class="landing-reveal mt-12 grid gap-6 lg:grid-cols-3" data-landing-reveal>
            <x-landing.audience
                profile="Proprietário"
                title="Dono do carro"
                icon="user"
                :points="['Cadastro por placa, chassi e RENAVAM', 'O que você registra aparece como declarado', 'PDF com notas para vender ou financiar']"
            >
                Registre o que fez, peça o Selo da oficina no próximo serviço e leve o PDF na venda.
                @guest
                    <x-slot:actions>
                        <x-landing.cta :href="route('register')" size="md">Começar grátis</x-landing.cta>
                    </x-slot:actions>
                @endguest
            </x-landing.audience>

            <x-landing.audience
                profile="Lojista"
                title="Lojas de veículos"
                icon="building-storefront"
                :points="['Estoque e veículos à venda no mesmo lugar', 'Registros declarados até a oficina aplicar o selo', 'Relatório para o comprador levar']"
            >
                O histórico do estoque não some na transferência. A revisão antes da venda fica no carro, não na planilha.
                <x-slot:actions>
                    <x-landing.cta :href="$landingPartnershipUrl" variant="secondary" size="md">Fale com a equipe</x-landing.cta>
                    @guest
                        <x-ui.link :href="route('login.lojista')">Já tenho conta · Entrar<span class="sr-only"> como lojista</span></x-ui.link>
                    @endguest
                </x-slot:actions>
            </x-landing.audience>

            <x-landing.audience
                profile="Oficina"
                title="Oficinas"
                icon="wrench-screwdriver"
                :points="['Selo da oficina com código de conferência', 'Listada em Oficinas da rede', 'Acompanhamento do cliente depois da OS']"
            >
                O Selo da oficina põe o nome da sua oficina no histórico do carro, e quem procura serviço encontra você em Oficinas da rede.
                <x-slot:actions>
                    <x-landing.cta :href="$landingPartnershipUrl" variant="secondary" size="md">Quero ser oficina parceira</x-landing.cta>
                    @guest
                        <x-ui.link :href="route('login.oficina')">Já tenho conta · Entrar<span class="sr-only"> como oficina</span></x-ui.link>
                    @endguest
                </x-slot:actions>
            </x-landing.audience>
        </div>
    </x-ui.container>
</section>

{{-- App --}}
<section id="app" class="theme-inverse relative scroll-mt-24 overflow-hidden" aria-labelledby="app-titulo">
    <div class="absolute inset-0 bg-automotive-950"></div>
    <div class="absolute top-0 -left-16 h-72 w-72 rounded-full bg-wrench-500/15 blur-3xl"></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
        <div>
            <p class="text-xs font-semibold tracking-[0.2em] text-link uppercase">No celular</p>
            <h2 id="app-titulo" class="mt-3 text-3xl font-bold text-balance text-foreground sm:text-4xl">O mesmo histórico, no seu bolso</h2>
            <p class="mt-4 max-w-xl text-muted-foreground">No app, a ficha do veículo traz chassi e RENAVAM, a linha do tempo e a lista de manutenções, com os mesmos dados do site.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @guest
                    <x-landing.cta :href="route('register')">Começar grátis</x-landing.cta>
                @else
                    <x-landing.cta :href="$landingHomeUrl">Ir para o Início</x-landing.cta>
                @endguest
            </div>
            <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <x-landing.app-store-badge />
                <p class="text-sm text-muted-foreground">Android em breve. No Android, cadastro e consulta já funcionam no navegador do celular.</p>
            </div>
        </div>
        <div class="relative mx-auto flex min-h-[22rem] w-full max-w-lg items-end justify-center pb-4 sm:min-h-[32rem]">
            <x-landing.app-phone
                class="absolute top-10 left-0 hidden w-40 -rotate-12 sm:block lg:w-44"
                :src="\App\Support\AppStorage::landingUrl('app-login.png')"
                alt="App RevisaLog: escolha do perfil para entrar"
            />
            <x-landing.app-phone
                class="relative z-10 w-52 sm:w-56"
                :src="\App\Support\AppStorage::landingUrl('app-vehicle.png')"
                alt="App RevisaLog: ficha do veículo com chassi, RENAVAM e linha do tempo"
            />
            <x-landing.app-phone
                class="absolute top-16 right-0 hidden w-40 rotate-12 sm:block lg:w-44"
                :src="\App\Support\AppStorage::landingUrl('app-maintenances.png')"
                alt="App RevisaLog: lista de manutenções do veículo"
            />
        </div>
    </div>
</section>

{{-- Preço --}}
<section id="preco" class="scroll-mt-24 bg-background py-16 sm:py-20" aria-labelledby="preco-titulo">
    <x-ui.container>
        <p class="text-center text-xs font-semibold tracking-[0.2em] text-link uppercase">Preço</p>
        <h2 id="preco-titulo" class="mt-3 text-center text-3xl font-bold text-balance text-foreground sm:text-4xl">Grátis enquanto a rede cresce</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-muted-foreground">Ainda não cobramos e não há planos definidos. Quando houver, avisaremos com antecedência.</p>

        <div class="mx-auto mt-12 max-w-lg">
            <x-ui.card padding="lg" class="border-accent-border ring-1 ring-accent-border">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-semibold tracking-wide text-link uppercase">RevisaLog</p>
                    <x-ui.badge variant="primary">Lançamento</x-ui.badge>
                </div>
                <p class="mt-3 flex items-end gap-2">
                    <span class="text-5xl font-bold tracking-tight text-foreground">R$ 0</span>
                    <span class="pb-1 text-sm text-muted-foreground">por mês, no lançamento</span>
                </p>
                <p class="mt-2 text-sm text-muted-foreground">Sem cartão e sem limite de veículos durante o lançamento.</p>
                <ul role="list" class="mt-6 space-y-3 text-sm text-foreground">
                    @foreach (['Histórico permanente no veículo', 'Selo da oficina e registros declarados', 'PDF com notas fiscais', 'Busca por placa, chassi ou RENAVAM'] as $priceItem)
                        <li class="flex gap-2">
                            <x-ui.icon name="check" class="mt-px size-4 text-link" />
                            <span>{{ $priceItem }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-8">
                    @guest
                        <x-landing.cta :href="route('register')" class="w-full">Começar grátis</x-landing.cta>
                    @else
                        <x-landing.cta :href="$landingHomeUrl" class="w-full">Ir para o Início</x-landing.cta>
                    @endguest
                </div>
            </x-ui.card>
        </div>
    </x-ui.container>
</section>

{{-- FAQ --}}
<section id="faq" class="scroll-mt-24 border-t border-border bg-surface py-16 sm:py-20" aria-labelledby="faq-titulo">
    <x-ui.container size="md">
        <h2 id="faq-titulo" class="text-center text-3xl font-bold text-foreground">Perguntas frequentes</h2>
        <div class="mt-10 space-y-3">
            <x-landing.faq-item question="É de graça mesmo?">
                Sim. No lançamento o RevisaLog não cobra nada e não pede cartão.
            </x-landing.faq-item>
            <x-landing.faq-item question="O histórico muda de dono junto com o carro?">
                Sim. O registro fica no veículo: depois da transferência, o histórico continua consultável pela placa, pelo chassi ou pelo RENAVAM.
            </x-landing.faq-item>
            <x-landing.faq-item question="Qual a diferença entre selo e declaração?">
                O Selo da oficina é o serviço confirmado por uma oficina da rede, com código de conferência. A declaração é o que o proprietário ou o lojista registrou por conta própria, sem essa confirmação.
            </x-landing.faq-item>
            <x-landing.faq-item question="Preciso de conta para consultar um veículo?">
                Para buscar pela placa, pelo chassi ou pelo RENAVAM, sim: uma conta gratuita. Para conferir o código de um selo, não: qualquer pessoa confere em <x-ui.link :href="route('verification.lookup')" variant="inline">Conferir selo da oficina</x-ui.link>.
            </x-landing.faq-item>
            <x-landing.faq-item question="Oficinas e lojas também podem usar?">
                Sim. Oficinas aplicam o Selo da oficina e aparecem em Oficinas da rede. Lojas registram a revisão antes da venda no próprio estoque. Para entrar na rede, <x-ui.link :href="$landingPartnershipUrl" variant="inline">fale com a equipe</x-ui.link>.
            </x-landing.faq-item>
            <x-landing.faq-item question="Tem aplicativo?">
                Tem. O app para iPhone está na <x-ui.link :href="config('app.ios_app_store_url')" external variant="inline">App Store</x-ui.link>, com a mesma conta e o mesmo histórico do site. A versão para Android está a caminho; enquanto isso, cadastro e consulta funcionam no navegador do celular.
            </x-landing.faq-item>
        </div>

        <div class="mt-10 rounded-card border border-border bg-background p-6 text-center">
            <p class="text-muted-foreground">Ficou com outra dúvida? No blog escrevemos sobre manutenção, documentação e o que pesa na hora de vender o carro.</p>
            <x-ui.button variant="secondary" icon="newspaper" :href="route('blog.index')" class="mt-4">Ir para o blog</x-ui.button>
        </div>
    </x-ui.container>
</section>

{{-- CTA final --}}
<section class="theme-inverse relative overflow-hidden" aria-labelledby="cta-final-titulo">
    <div class="absolute inset-0 bg-automotive-950"></div>
    <div class="absolute top-0 right-0 h-64 w-64 bg-wrench-500/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 sm:py-20">
        <h2 id="cta-final-titulo" class="text-3xl font-bold text-balance text-foreground sm:text-4xl">Comece pelo primeiro veículo</h2>
        <p class="mt-4 text-muted-foreground">Quanto antes o histórico existir, mais ele conta na próxima venda e na próxima revisão.</p>
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            @auth
                <x-landing.cta :href="$landingHomeUrl">Ir para o Início</x-landing.cta>
            @else
                <x-landing.cta :href="route('register')">Começar grátis</x-landing.cta>
                <x-ui.button variant="secondary" size="lg" :href="route('login')">Já tenho conta</x-ui.button>
            @endauth
        </div>
    </div>
</section>
</div>
@endsection
