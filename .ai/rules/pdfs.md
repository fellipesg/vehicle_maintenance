---
paths:
  - resources/views/pdfs/vehicle_maintenance_export.blade.php
  - app/Services/Vehicle/VehiclePdfMaintenancePhotos.php
  - app/Services/Vehicle/VehicleMaintenancePdfExporter.php
  - app/Services/Vehicle/VehiclePdfExportService.php
  - app/Http/Controllers/Api/VehiclePdfExportController.php
  - app/Http/Controllers/Web/User/VehiclePdfExportDownloadController.php
  - app/Console/Commands/CleanupVehiclePdfExports.php
---

# Pdfs

## Dompdf history PDF: flat tables, fill cover
Dompdf: never nest tables in the OS letterhead or the blue title-band. Each is one table, one row, two cells. Do not use .band-inner, flex, position, or height:100% on nested tables. Title-band cells share one tr (title left, date right) with the same blue background — inner tables create the black gap. Letterhead is a 75/25 table with a 1px #d1d5db border; workshop logo is portrait and compact (.letterhead-logo max 72x56) so header+band+body start on the first OS page.

Cover photo: .vehicle-cover has padding:0 and the img is width/height 100% (object-fit:cover if Dompdf honors it). The 12px gap to the data card stays outside the photo border (spacer td). Scope .document-table padding with child combinators (> thead/> tbody > tr > td) so nested cells do not inherit 18px padding.

## Dompdf history PDF layout
Cover: landscape photo first (coverPathForPdf prefers cover_photo_path), full-width banner crop 700×220, stacked below title band — no 2-column layout, no page-break-inside:avoid on .cover-document, no height:100% on img.

OS (.os-document): thead = letterhead + title-band only (display:table-header-group). tbody = separate tr for meta, items-table, invoices. items-table has its own thead (Item/Qtd/…) that repeats on page breaks. Workshop logo portrait: max-height 64px, max-width 48px.

Page breaks: `.cover-document` uses `page-break-after: always`. Do **not** set `page-break-before: always` on every `.os-document` — that stacks with the cover break and inserts a blank page. Use `.os-document + .os-document { page-break-before: always; }` so only the 2nd+ OS starts on a new page.

## Letterhead workshop logo size
Workshop logo in letterhead: portrait 2:3, CSS max-width 72px / max-height 108px, valign middle in .letterhead-logo-cell. Demo source images 200×300 (Picsum or DemoWorkshopLogoGenerator). Do not squash with tiny max-width.

## Letterhead workshop logo size
Crop workshop logos to 200×300 portrait in VehicleMaintenancePdfExporter before embed. Display with explicit width/height attrs (120×180) — Dompdf ignores max-width/max-height. Demo seeder resolves workshop by official name so Brothers Auto Service gets the logo file.

## Footer, cover counter and dates
The page footer ("RevisaLog · Página X de Y" plus the vehicle) is drawn by VehicleMaintenancePdfExporter::stampPageFooter through Dompdf page_script after render(), never in the HTML. The cover summary uses .prov-counter (N com selo · M declarada(s)) plus the .prov-dot row; .cover-document--only applies when the vehicle has no maintenances (the empty state lives on the cover itself). Dates and times use America/Sao_Paulo. Seals are checked at revisalog.com.br/verificar (the code typed from the PDF) or revisalog.com.br/v/{código}.

## Identifiers follow the requester
The template receives $identifiersMasked (VehicleMaintenancePdfExporter::generate(..., maskIdentifiers:)). When true, chassis and RENAVAM go through VehicleIdentifierMask and the engine code is left out. GenerateVehicleMaintenancePdfExport and EmailVehicleMaintenancePdf compute it from the requester with VehicleIdentifierVisibility::showsFullIdentifiers (full numbers only for Gate update), so a consignment holder or an admin gets the masked PDF.

## Registros de oficina no PDF seguem quem pediu
VehicleMaintenancePdfExporter::generate(..., viewer:) passa o solicitante ao MaintenanceRedactor. No PDF do próprio proprietário, o registro que ele vinculou e ocultou da consulta pública continua aparecendo (ocultar vale só para os outros); pendentes e recusados saem na forma mínima. Sem viewer, ou com outra pessoa (admin, lojista em consignação), o ocultado não entra.

## Fotos das OS no PDF: só com o aceite do dono atual
VehiclePdfMaintenancePhotos decide. Só entram fotos quando o proprietário ATUAL do veículo tem `users.export_include_photos = true` (nulo = nunca perguntado e falso = não, os dois sem foto; capa não muda). Quem pediu sendo esse dono leva, por OS, o primeiro antes + o primeiro depois do veículo (por `sort`), ou, se faltar o par, o "antes" avulso (OS com "antes" e nenhum "depois") ou os dois primeiros "depois" (OS sem "antes"); qualquer outra pessoa que exporta (lojista em consignação, admin, sem viewer) leva só as públicas (`MaintenancePhoto::isPublicVisible`), no máximo 2. As OS que o MaintenanceRedactor esvaziou continuam sem foto: o serviço só lê a relação `photos` que sobrou, nunca recarrega nem contorna. Teto de MAX_PER_PDF (16) fotos por arquivo, as OS mais recentes primeiro, para o PDF ficar bem abaixo de ~8 MB (o e-mail anexa o arquivo e o merge ainda cola as notas em PDF); o excedente vira "+N fotos no RevisaLog" com o link do selo.

Cada foto sai JPEG de até 900px no maior lado, qualidade 75, sobre fundo branco (o Dompdf não lida bem com WebP), convertida uma por vez e embutida como data URI (mesma regra da capa: nada de caminho em /tmp no src). Prefere `display_path` (variante de exibição) quando a coluna existe e está preenchida, lida por `getAttributes()`, senão `path`. No Blade: `.photo-grid` de 2 colunas abaixo da tabela de itens, `<img>` com width/height explícitos (caixa 300×225) e legenda "Antes"/"Depois"; tr com page-break-inside:avoid, sem tabela aninhada no letterhead nem na faixa.

## Fotos no PDF também pedem o recurso por conta
Antes do aceite do dono, quem pediu o PDF (`viewer`) precisa ter as fotos das manutenções ligadas (MaintenancePhotoAccess; features.md): sem isso o exportador nem consulta o dono e o PDF sai sem foto de OS. Sem viewer (null) conta como sem recurso. O cartão "PDF do histórico" em /conta e a preferência no PUT /api/v1/me só existem para a conta com o recurso.

## Baixar o PDF pede prazo e posse na hora, não só na geração
`VehiclePdfExportService::userCanViewExport` exige quem pediu a exportação (ou um admin) **e**
`VehiclePolicy::viewFullHistory` no veículo **naquele momento**: guardar o arquivo não guarda o
direito. Quem vendeu o carro para de baixar junto com a posse (403), e o lojista segue baixando só
enquanto a consignação dele libera o histórico — a mesma ability que autorizou o pedido em
`createExport`, nunca uma segunda cópia da regra. Dono atual, admin e lojista liberado continuam
passando.

O prazo (`expires_at`, `vehicle-pdf-export.file_ttl_hours`, 24 h, gravado por
`GenerateVehicleMaintenancePdfExport`) é cobrado no ato por `VehiclePdfExport::isExpired()`: **410**
na API (status e download) e na web (`resources/views/errors/410.blade.php`), não 404 — o 404 desses
endpoints quer dizer "ainda não ficou pronto" e o app continua consultando, enquanto o prazo vencido
é definitivo. O status expirado nem chega a montar o payload, então não entrega mais URL assinada do
storage.

`vehicle-pdf-exports:cleanup` deixou de ser o único lugar que aplica o prazo e virou faxina de
arquivo: roda de hora em hora (era diário) só para encurtar a brecha da URL assinada que o status
entregou pouco antes do vencimento (`download_url_expiry_minutes`, 60 min) e que resolve enquanto o
arquivo existir.
