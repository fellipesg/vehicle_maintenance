---
paths:
  - app/Services/Vehicle/VehicleMaintenancePdfExporter.php
---

# Vehicle

## Embed cover in history PDF via data URI
DomPDF has enable_remote=false and chroot=base_path(). Never pass S3 signed URLs or /tmp paths as <img src>. Copy the cover with AppStorage::localCopy and embed JPEG/PNG/GIF as a data URI (convert other formats to JPEG). Show the full photo (max-width/max-height), do not crop.
