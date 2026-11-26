@php
    /*
     * Google AdSense só no artigo do blog (App\Support\GoogleAdsense) e só com ADSENSE_CLIENT_ID definido.
     * Anúncio personalizado depende do aceite no aviso <x-ads.consent-banner> (cookie rl_ads_consent,
     * gravado por resources/js/ad-consent.js); sem aceite, ou com o sinal Global Privacy Control do
     * navegador, o AdSense recebe consentimento negado e serve só anúncio não personalizado.
     * Vem depois de <x-analytics />, que já declara o consentimento padrão quando o GA4 está ligado.
     */
    $adsenseClient = \App\Support\GoogleAdsense::isActiveOnCurrentPage() ? \App\Support\GoogleAdsense::client() : null;
    $analyticsDeclaresConsent = trim((string) config('services.google_analytics.measurement_id')) !== '';
@endphp
@if ($adsenseClient !== null)
    <script nonce="@cspNonce">
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        @unless ($analyticsDeclaresConsent)
        window.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        @endunless
        (function () {
            var accepted = ! navigator.globalPrivacyControl && document.cookie.split('; ').indexOf('rl_ads_consent=granted') !== -1;
            if (accepted) {
                window.gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
            } else {
                (window.adsbygoogle = window.adsbygoogle || []).requestNonPersonalizedAds = 1;
            }
        })();
    </script>
    <script nonce="@cspNonce" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsenseClient }}" crossorigin="anonymous"></script>
@endif
