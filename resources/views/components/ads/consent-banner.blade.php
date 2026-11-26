@php
    /*
     * Aviso de cookies de publicidade do artigo do blog (a única página com anúncio). Nasce escondido: resources/js/ad-consent.js mostra só
     * para quem ainda não escolheu e não manda o sinal Global Privacy Control. Recusar não tira os
     * anúncios, só a personalização (seção "Cookies" da política de privacidade).
     */
@endphp
@if (\App\Support\GoogleAdsense::isActiveOnCurrentPage())
    <section hidden aria-labelledby="aviso-cookies-titulo" class="theme-default fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-lg sm:px-6" data-ad-consent>
        <div class="mx-auto flex max-w-5xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 id="aviso-cookies-titulo" class="text-sm font-semibold text-foreground">Anúncios nos artigos</h2>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    Os artigos do blog mostram anúncios do Google. Aceita cookies de publicidade para ver anúncios personalizados? Se recusar, os anúncios continuam, sem personalização.
                    <a href="{{ route('legal.privacy') }}#cookies" class="font-medium text-link underline underline-offset-2">Política de privacidade</a>
                </p>
            </div>
            <div class="flex shrink-0 gap-3 max-sm:*:grow">
                <x-ui.button variant="secondary" data-ad-consent-choice="denied">Recusar</x-ui.button>
                <x-ui.button data-ad-consent-choice="granted">Aceitar</x-ui.button>
            </div>
        </div>
    </section>
@endif
