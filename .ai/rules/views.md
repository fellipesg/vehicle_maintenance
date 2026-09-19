---
paths:
  - 'resources/views/**'
  - resources/views/home.blade.php
---

# Views

## Brand assets via CDN brandUrl
Static Revisalog brand images (favicon, apple-touch-icon, lockups, app-icon, og-image) must use AppStorage::brandUrl() or the brand-head-icons component so production serves them from the R2 CDN (cdn.revisalog.com.br). Do not use asset() for files under images/brand/ or public favicon paths.

## Landing uses real Flutter app screenshots
Landing marketing screenshots of the Flutter app live in public/images/landing, are published to R2 with brand:publish-to-r2, and are referenced with AppStorage::landingUrl(). Do not use asset() for those files. Do not use CSS-only fake phones when real captures exist. Keep Começar grátis behind @guest.

## Landing marquee spans full viewport RTL
The capability strip under the hero is a full-viewport RTL marquee: overflow hidden, track width max-content, at least 4 copies of the labels so one half is wider than 100vw, animation translateX(0) to translateX(-50%). Do not center or max-width the strip or it will only travel mid-to-left.
