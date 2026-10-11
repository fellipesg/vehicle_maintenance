@php
    /*
     * Google Analytics 4 só nas páginas públicas e só com GA_MEASUREMENT_ID definido. Os portais
     * (usuário, oficina, garagem, admin, conta, notificações) guardam dados pessoais e ficam de fora.
     * Exceção: a página logo após o cadastro (flash analytics_event) carrega o gtag só para registrar
     * o sign_up, sem page_view.
     */
    $measurementId = trim((string) config('services.google_analytics.measurement_id'));
    $signedUp = session('analytics_event') === 'sign_up';
    $isPublicPage = request()->routeIs(
        'home', 'workshops.*', 'blog.*', 'legal.*', 'contact.*', 'verification.*',
        'login', 'login.*', 'register', 'password.*',
    ) || (request()->routeIs('vehicle.search') && auth()->guest());
    $outreachRef = request()->routeIs('contact.show', 'workshops.landing') && request()->filled('ref');
@endphp
@if ($measurementId !== '' && ($isPublicPage || $signedUp))
    {{-- Cookies de medição liberados (legítimo interesse, declarado na seção 9 da política de
         privacidade); cookies e sinais de anúncios ficam negados. IP é anonimizado por padrão no GA4. --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('consent', 'default', { analytics_storage: 'granted', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        gtag('js', new Date());
        gtag('config', @json($measurementId), { allow_google_signals: false, allow_ad_personalization_signals: false, send_page_view: {{ $isPublicPage ? 'true' : 'false' }} });
        @if ($signedUp)
        gtag('event', 'sign_up', { method: '{{ session('analytics_method') === 'workshop' ? 'workshop' : 'email' }}' });
        @endif
        @if ($outreachRef)
        gtag('event', 'outreach_click', { campaign: 'workshop_prospect_invite' });
        @endif
    </script>
@endif
