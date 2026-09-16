---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Cloud PDF worker must use database connection
PDF exports dispatch to QUEUE_CONNECTION=database (Neon jobs table). The managed-queue worker runs `queue:work cloud` and never sees those jobs. Production needs a background process on the App instance: `queue:work database --timeout=300 --tries=2` (process-a2bec05f). The Cloud CLI lives in the Composer sandbox cache, not PATH.
