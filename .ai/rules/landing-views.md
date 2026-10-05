---
paths:
  - 'resources/views/components/landing/**,resources/views/home.blade.php'
---

# Landing Views

## Landing timeline mock stays ordered and identified
The landing timeline mock must list oldest-first events with strictly increasing kilometers (smaller km at the top, latest service at the bottom), one provenance dot per event in the same order, and show Placa atual, Chassi and RENAVAM as real-looking values — never the placeholder “2022 · chassi · RENAVAM”. Do not invent testimonials, download counts or price tiers. The only store link is the real iOS app: <x-landing.app-store-badge> (Apple's official badge SVG, never redrawn or recolored) pointing to config('app.ios_app_store_url'), shown under the hero actions with "Android em breve" until the Play Store listing exists; it is static (not part of the .landing-intro stagger). Keep Começar grátis behind @guest.

## Landing timeline is oldest-first
Landing timeline mocks (hero, Produto: Linha do tempo · Busca · PDF, app) are oldest-first: kilometers increase top to bottom, latest service at the bottom. Provenance dots must match that order. Never render newest-first.

## Mocks read one sample vehicle
The hero, Busca and PDF mocks take vehicle and services from App\Support\LandingSampleVehicle (oldest to newest, one dot per service, identifiers masked by VehicleIdentifierMask in the search mock). Change the sample there, never inline in a mock. The old "Telas" section is gone: the product showcase is the Produto section (x-ui.tabs Linha do tempo | Busca | PDF, with the real app-timeline.png via AppStorage::landingUrl()).

## Markers are icons, CTAs are x-landing.cta
Selo da oficina and Declarada markers inside the landing mocks use icons (x-landing.provenance-glyph), never letters such as S/D or OF/PR. Primary calls to action use <x-landing.cta> (arrow that slides on hover and focus); secondary ones use <x-ui.button variant="secondary">.

## One motion moment per section, all off with reduced motion
Hero entrance is CSS only: .landing-intro with --landing-intro-delay (40/100/160ms), 500ms ease-smooth-out, under @media (prefers-reduced-motion: no-preference). The only loops are the hero mock float (.landing-float, data-landing-loop) and the RTL capability marquee; the "Pausar animação" button (aria-pressed, x-ui.icon pause/play) stops both. The scroll hint bounces 3 times and stops. Static cards never lift on hover. Como funciona draws its rail, Procedência and Para quem reveal once, Produto fades its tab panels; nothing else moves.
