---
paths:
  - 'frontend/lib/views/vehicles/**'
---

# Vehicles

> Flutter app (`vehicle_maintenance_frontend`), not this repository. Paths are relative to a sibling `frontend/` checkout. The Blade equivalents live in `.ai/rules/user-vehicles.md`.

## Dual vehicle covers (landscape + portrait)
Cadastro pede capa paisagem (celular deitado, 16:9) e retrato (celular em pé, 9:16). Hero largo / desktop usa paisagem; telas <768px, avatares e PDF usam retrato. Sempre cropper antes do upload. Cancelar mantém a foto anterior daquela orientação.

## Open vehicle history PDF in-app
After the history PDF is saved, push PdfViewerPage (printing PdfPreview). Do not use url_launcher + Uri.file — iOS simulator cannotLaunch file:// and only shows the snackbar path. OpenFilex is for invoice files; the vehicle history export opens in-app.

## PDF export snackbar must be cancellable
The Gerando PDF snackbar must include a Cancelar action that increments the export generation and stops polling/opening the viewer. Do not use a 5-minute snackbar without an action — iOS users cannot dismiss it.
