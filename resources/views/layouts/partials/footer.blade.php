<footer class="border-t border-automotive-200 bg-automotive-50 py-12 text-sm text-automotive-600">
    <div class="mx-auto max-w-7xl px-4">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="inline-flex items-center gap-2 font-semibold text-automotive-800">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-automotive-800 text-wrench-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3 w-3" aria-hidden="true">
                            <path d="M5 11h1.6l1.1-3.2A1.4 1.4 0 0 1 9.02 7h5.96a1.4 1.4 0 0 1 1.32.8L17.4 11H19a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-1.1a2.4 2.4 0 1 1-4.8 0H9.9a2.4 2.4 0 1 1-4.8 0H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h1V11Z"/>
                            <path d="M15.5 3.5 14 5l1.5 1.5L14 8l1.5 1.5L17 8l1.5 1.5L20 8l-1.5-1.5L20 5l-1.5-1.5L17 5l-1.5-1.5Z"/>
                        </svg>
                    </span>
                    Vehicle Maintenance
                </p>
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
        </div>
        <p class="mt-10 text-center text-xs text-automotive-400">O histórico fica vinculado ao veículo, não ao proprietário</p>
    </div>
</footer>
