---
paths:
  - 'resources/views/**'
---

# Views

## Brand assets via CDN brandUrl
Static Revisalog brand images (favicon, apple-touch-icon, lockups, app-icon, og-image) must use AppStorage::brandUrl() or the brand-head-icons component so production serves them from the R2 CDN (cdn.revisalog.com.br). Do not use asset() for files under images/brand/ or public favicon paths.
