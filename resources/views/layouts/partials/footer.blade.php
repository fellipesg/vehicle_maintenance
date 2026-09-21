<footer class="border-t border-automotive-200 bg-automotive-50 py-12 text-sm text-automotive-600">
    <div class="mx-auto max-w-7xl px-4">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center">
                    <img
                        src="{{ \App\Support\AppStorage::brandUrl('lockup-horizontal.png') }}"
                        alt="RevisaLog"
                        class="h-8 w-auto"
                    >
                </a>
                <p class="mt-3 text-xs leading-relaxed text-automotive-500">Histórico permanente de manutenções veiculares. O registro fica no carro, não na conta.</p>
            </div>
            <nav class="flex flex-col gap-2 text-xs" aria-label="Produto">
                <p class="font-semibold uppercase tracking-wide text-automotive-800">Produto</p>
                <a href="{{ route('home') }}" class="hover:text-wrench-700">Início</a>
                <a href="{{ route('home') }}#como-funciona" class="hover:text-wrench-700">Como funciona</a>
                <a href="{{ route('home') }}#recursos" class="hover:text-wrench-700">Recursos</a>
                <a href="{{ route('home') }}#telas" class="hover:text-wrench-700">Telas</a>
                <a href="{{ route('home') }}#preco" class="hover:text-wrench-700">Preço</a>
            </nav>
            <nav class="flex flex-col gap-2 text-xs" aria-label="Conta">
                <p class="font-semibold uppercase tracking-wide text-automotive-800">Conta</p>
                <a href="{{ route('login') }}" class="hover:text-wrench-700">Entrar</a>
                <a href="{{ route('register') }}" class="hover:text-wrench-700">Cadastrar</a>
                <a href="{{ route('login.lojista') }}" class="hover:text-wrench-700">Lojista</a>
                <a href="{{ route('login.oficina') }}" class="hover:text-wrench-700">Oficina</a>
            </nav>
            <nav class="flex flex-col gap-2 text-xs" aria-label="Plataforma">
                <p class="font-semibold uppercase tracking-wide text-automotive-800">Plataforma</p>
                <a href="{{ route('vehicle.search') }}" class="hover:text-wrench-700">Buscar veículo</a>
                <a href="{{ route('home') }}#app" class="hover:text-wrench-700">App</a>
                <a href="{{ route('home') }}#faq" class="hover:text-wrench-700">Perguntas</a>
            </nav>
            <nav class="flex flex-col gap-2 text-xs" aria-label="Legal">
                <p class="font-semibold uppercase tracking-wide text-automotive-800">Legal</p>
                <a href="{{ route('legal.terms') }}" class="hover:text-wrench-700">Termos de uso</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-wrench-700">Privacidade</a>
                <a href="{{ route('contact.show') }}" class="hover:text-wrench-700">Contato</a>
                <a href="mailto:{{ config('legal.support_email') }}" class="hover:text-wrench-700">{{ config('legal.support_email') }}</a>
            </nav>
        </div>
        <p class="mt-10 text-center text-xs text-automotive-400">O histórico fica vinculado ao veículo, não ao proprietário</p>
    </div>
</footer>
