/**
 * Eventos de conversão do GA4 (só age se o gtag foi carregado pelo <x-analytics />, ou seja, nas
 * páginas públicas com GA_MEASUREMENT_ID). Delegação de cliques: não depende do markup de cada tela.
 */
const APP_STORE_HOST = 'apps.apple.com';

function eventFor(link) {
    let url;

    try {
        url = new URL(link.href, window.location.href);
    } catch {
        return null;
    }

    if (url.hostname === APP_STORE_HOST) {
        return ['app_store_click', {}];
    }

    if (url.origin === window.location.origin && url.pathname === '/register') {
        return ['register_cta_click', { link_text: link.textContent.trim().slice(0, 60) }];
    }

    return null;
}

export function initAnalytics() {
    if (typeof window.gtag !== 'function' || document.documentElement.dataset.analyticsReady === '1') {
        return;
    }

    document.documentElement.dataset.analyticsReady = '1';

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        const tracked = link ? eventFor(link) : null;

        if (tracked) {
            window.gtag('event', tracked[0], { ...tracked[1], transport_type: 'beacon' });
        }
    });
}
