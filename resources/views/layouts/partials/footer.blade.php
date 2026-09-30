{{--
    Rodapé institucional, só para visitantes (logados recebem layouts.partials.footer-compact).
    Colunas: Produto (seções da landing, na ordem da página, "Conferir selo da oficina" e Blog) ·
    Conta · Lojas e oficinas · Legal. "Consultar um veículo" leva à busca, que pede login e oferece
    o cadastro grátis (o texto avisa); a conferência do selo (/verificar) é a única consulta aberta a
    qualquer visitante. Títulos de coluna em text-xs caixa-alta (ritmo do rodapé do ObsidianUI) e
    links em text-sm com 40px de alvo.
--}}
@php
    $footerColumns = [
        'Produto' => [
            ['label' => 'Como funciona', 'href' => route('home').'#como-funciona'],
            ['label' => 'Procedência', 'href' => route('home').'#procedencia'],
            ['label' => 'Linha do tempo, busca e PDF', 'href' => route('home').'#produto'],
            ['label' => 'Conferir selo da oficina', 'href' => route('verification.lookup')],
            ['label' => 'App', 'href' => route('home').'#app'],
            ['label' => 'Preço', 'href' => route('home').'#preco'],
            ['label' => 'Perguntas frequentes', 'href' => route('home').'#faq'],
            ['label' => 'Blog', 'href' => route('blog.index')],
        ],
        'Conta' => [
            ['label' => 'Entrar', 'href' => route('login')],
            ['label' => 'Começar grátis', 'href' => route('register')],
            ['label' => 'Consultar um veículo', 'href' => route('vehicle.search'), 'note' => 'grátis, com conta'],
        ],
        'Lojas e oficinas' => [
            ['label' => 'Quero ser parceiro', 'href' => route('contact.show', ['assunto' => 'partnership'])],
            ['label' => 'Entrar como lojista', 'href' => route('login.lojista')],
            ['label' => 'Entrar como oficina', 'href' => route('login.oficina')],
        ],
        'Legal' => [
            ['label' => 'Termos de uso', 'href' => route('legal.terms')],
            ['label' => 'Privacidade', 'href' => route('legal.privacy')],
            ['label' => 'Contato', 'href' => route('contact.show')],
            ['label' => config('legal.support_email'), 'href' => 'mailto:'.config('legal.support_email')],
        ],
    ];
@endphp
<footer class="border-t border-border bg-background py-12 text-sm text-muted-foreground sm:py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-5">
            <div>
                {{-- Os lockups PNG não têm canal alfa (fundo automotive-950 embutido): o contêiner escuro
                     arredondado torna o fundo intencional até existir uma versão transparente. --}}
                <a href="{{ route('home') }}" class="inline-flex items-center rounded-control bg-automotive-950 px-3 py-2">
                    <img
                        src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal.png') }}"
                        alt="RevisaLog"
                        class="h-7 w-auto"
                    >
                </a>
                <p class="mt-4 max-w-xs text-sm leading-relaxed">Histórico permanente de manutenções. O registro fica no carro, não na conta.</p>
            </div>
            <nav aria-label="Rodapé" class="grid gap-8 sm:grid-cols-2 lg:col-span-4 lg:grid-cols-4">
                @foreach($footerColumns as $footerHeading => $footerLinks)
                    <div>
                        <h2 class="text-xs font-semibold tracking-wider text-foreground uppercase">{{ $footerHeading }}</h2>
                        <ul role="list" class="mt-3">
                            @foreach($footerLinks as $footerLink)
                                <li>
                                    <a href="{{ $footerLink['href'] }}" class="inline-flex min-h-10 flex-wrap items-center gap-x-1 wrap-anywhere transition-colors duration-fast hover:text-link-hover hover:underline motion-reduce:transition-none">
                                        {{ $footerLink['label'] }}
                                        @isset($footerLink['note'])
                                            <span class="text-xs text-subtle-foreground">({{ $footerLink['note'] }})</span>
                                        @endisset
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>
        <div class="mt-10 flex flex-col gap-2 border-t border-border pt-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>
                © {{ now()->year }} {{ config('legal.company.legal_name') ?: 'RevisaLog' }}
                @if(config('legal.company.cnpj'))
                    · CNPJ {{ config('legal.company.cnpj') }}
                @endif
            </p>
            <p>O histórico fica vinculado ao veículo, não ao proprietário</p>
        </div>
    </div>
</footer>
