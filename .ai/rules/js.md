---
paths:
  - resources/js/user-portal.js
---

# Js

## Vehicle PDF export download (user portal)

After polling `GET /api/v1/vehicle-pdf-exports/{id}` until `status === completed`, replace the export button with a same-origin `<a>` the user must click.

`href` must be a relative path whose **last segment is the ASCII `.pdf` filename**:

`/usuario/exportacoes-pdf/{exportId}/historico_manutencoes_QOS6H54_....pdf`

Never set the `download` attribute. Chromium implements `<a download>` by fetching the response as a `blob:` URL, so the download shelf shows a UUID with no extension even when the href already ends in `.pdf`. A plain GET lets `Content-Disposition` and the URL filename win.

Do not use `download_url`, absolute `route()` URLs, `/api/v1/.../download`, or `blob:` URLs.

Keep `/usuario/exportacoes-pdf/{id}/baixar` only as a 302 to the named `.pdf` path. The `Location` header must be a relative path — `redirect()->to()` prefixes `APP_URL` (`localhost` vs `127.0.0.1:8000`) and drops the session cookie.

Do not auto-navigate after polling: no `location.assign`, `window.open`, iframe, `fetch`, or axios `responseType: 'blob'`.

The Cursor Simple Browser download UI (`--disable-blink-features=AutomationControlled`) always lists intercepted files as UUIDs. Verify in Microsoft Edge / Chrome, not in the in-IDE browser.

## No download attribute on portal PDF links
Never set the download attribute on the Baixar PDF link. Chromium implements it as a blob: fetch, so the shelf shows a UUID even when href already ends in historico_....pdf. Plain same-origin GET only. Cursor Simple Browser always lists downloads as UUIDs; verify in real Edge.
