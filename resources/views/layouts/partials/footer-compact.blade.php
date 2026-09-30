{{-- Rodapé das áreas logadas, numa linha: ©, Termos, Privacidade, Contato e o e-mail de suporte, sem os links de marketing do rodapé institucional. --}}
<footer class="border-t border-border bg-background py-4 text-xs text-muted-foreground">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p>© {{ now()->year }} {{ config('legal.company.legal_name') ?: 'RevisaLog' }}</p>
        <nav aria-label="Rodapé">
            <ul role="list" class="flex flex-wrap items-center gap-x-5">
                <li><a href="{{ route('legal.terms') }}" class="inline-flex min-h-10 items-center hover:text-link-hover hover:underline">Termos de uso</a></li>
                <li><a href="{{ route('legal.privacy') }}" class="inline-flex min-h-10 items-center hover:text-link-hover hover:underline">Privacidade</a></li>
                <li><a href="{{ route('contact.show') }}" class="inline-flex min-h-10 items-center hover:text-link-hover hover:underline">Contato</a></li>
                @if(filled(config('legal.support_email')))
                    <li><a href="mailto:{{ config('legal.support_email') }}" class="inline-flex min-h-10 items-center break-all hover:text-link-hover hover:underline">{{ config('legal.support_email') }}</a></li>
                @endif
            </ul>
        </nav>
    </div>
</footer>
