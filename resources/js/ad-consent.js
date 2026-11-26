/**
 * AdSense do artigo do blog (<x-adsense />, <x-ads.unit>, <x-ads.consent-banner>): pede os blocos de anúncio
 * da página e mostra o aviso de cookies de publicidade para quem ainda não escolheu. A escolha fica no
 * cookie rl_ads_consent por um ano; quem manda o sinal Global Privacy Control nunca vê o aviso e
 * segue com consentimento negado.
 */
const CONSENT_COOKIE = 'rl_ads_consent';
const ONE_YEAR_SECONDS = 60 * 60 * 24 * 365;

function storedChoice() {
    const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${CONSENT_COOKIE}=`));

    return entry ? entry.slice(CONSENT_COOKIE.length + 1) : null;
}

function storeChoice(choice) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${CONSENT_COOKIE}=${choice}; Max-Age=${ONE_YEAR_SECONDS}; Path=/; SameSite=Lax${secure}`;
}

function requestAdUnits(root) {
    root.querySelectorAll('ins.adsbygoogle:not([data-ad-requested])').forEach((unit) => {
        unit.dataset.adRequested = '1';
        (window.adsbygoogle = window.adsbygoogle || []).push({});
    });
}

function initBanner(banner) {
    if (banner.dataset.adConsentReady === '1') {
        return;
    }

    banner.dataset.adConsentReady = '1';

    if (navigator.globalPrivacyControl || storedChoice() !== null) {
        return;
    }

    banner.hidden = false;

    banner.querySelectorAll('[data-ad-consent-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.dataset.adConsentChoice === 'granted' ? 'granted' : 'denied';
            storeChoice(choice);

            if (choice === 'granted' && typeof window.gtag === 'function') {
                window.gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
            }

            banner.hidden = true;
        });
    });
}

export function initAdConsent(root = document) {
    requestAdUnits(root);

    const banner = root.querySelector('[data-ad-consent]');

    if (banner) {
        initBanner(banner);
    }
}
