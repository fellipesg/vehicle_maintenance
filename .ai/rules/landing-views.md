---
paths:
  - 'resources/views/components/landing/**,resources/views/home.blade.php'
---

# Landing Views

## Landing timeline mock stays ordered and identified
The landing timeline mock must list oldest-first events with strictly increasing kilometers (smaller km at the top, latest service at the bottom), one provenance dot per event in the same order, and show Placa atual, Chassi and RENAVAM as real-looking values — never the placeholder “2022 · chassi · RENAVAM”. Do not invent testimonials, download counts, price tiers or App Store links. Keep Começar grátis behind @guest.

## Landing timeline is oldest-first
Landing timeline mocks (hero, Telas, PDF, app) are oldest-first: kilometers increase top to bottom, latest service at the bottom. Provenance dots must match that order. Never render newest-first.
