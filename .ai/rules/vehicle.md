---
paths:
  - app/Services/Vehicle/VehicleMaintenancePdfExporter.php
  - app/Services/Vehicle/VehicleCoverService.php
---

# Vehicle

## Embed cover in history PDF via data URI
DomPDF has enable_remote=false and chroot=base_path(). Never pass S3 signed URLs or /tmp paths as <img src>. Copy the cover with AppStorage::localCopy and embed JPEG/PNG/GIF as a data URI (convert other formats to JPEG). Center-crop the cover to 210×260 in VehicleMaintenancePdfExporter::coverImageSrcFromCopy before embedding; the Blade img uses width/height 210×260 with no padding inside .vehicle-cover (Dompdf ignores height:100% on images).

## PDF cover crop landscape banner
coverPathForPdf() prefers cover_photo_path (landscape), then portrait. Center-crop to COVER_CROP_WIDTH×COVER_CROP_HEIGHT (700×220) before data-URI embed. Blade uses .vehicle-cover-photo with width:100%; height:auto — no height:100%.

## Thumbnails obrigatórios para capas
Todo upload de capa (paisagem/retrato) deve gerar `cover_photo_thumb_path` 192×192 via `VehicleCoverCropper::cropToThumb`. Listagens e avatares devem preferir `cover_photo_thumb_url`.
