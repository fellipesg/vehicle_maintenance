---
paths:
  - resources/views/pdfs/vehicle_maintenance_export.blade.php
---

# Pdfs

## Dompdf history PDF: flat tables, fill cover
Dompdf: never nest tables in the OS letterhead or the blue title-band. Each is one table, one row, two cells. Do not use .band-inner, flex, position, or height:100% on nested tables. Title-band cells share one tr (title left, date right) with the same blue background — inner tables create the black gap. Letterhead is a 75/25 table with a 1px #d1d5db border; workshop logo is portrait and compact (.letterhead-logo max 72x56) so header+band+body start on the first OS page.

Cover photo: .vehicle-cover has padding:0 and the img is width/height 100% (object-fit:cover if Dompdf honors it). The 12px gap to the data card stays outside the photo border (spacer td). Scope .document-table padding with child combinators (> thead/> tbody > tr > td) so nested cells do not inherit 18px padding.

## Dompdf history PDF layout
Cover: landscape photo first (coverPathForPdf prefers cover_photo_path), full-width banner crop 700×220, stacked below title band — no 2-column layout, no page-break-inside:avoid on .cover-document, no height:100% on img.

OS (.os-document): thead = letterhead + title-band only (display:table-header-group). tbody = separate tr for meta, items-table, invoices. items-table has its own thead (Item/Qtd/…) that repeats on page breaks. Workshop logo portrait: max-height 64px, max-width 48px.

## Letterhead workshop logo size
Workshop logo in letterhead: portrait 2:3, CSS max-width 72px / max-height 108px, valign middle in .letterhead-logo-cell. Demo source images 200×300 (Picsum or DemoWorkshopLogoGenerator). Do not squash with tiny max-width.

## Letterhead workshop logo size
Crop workshop logos to 200×300 portrait in VehicleMaintenancePdfExporter before embed. Display with explicit width/height attrs (120×180) — Dompdf ignores max-width/max-height. Demo seeder resolves workshop by official name so Brothers Auto Service gets the logo file.
