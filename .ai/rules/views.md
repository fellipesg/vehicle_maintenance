---
paths:
  - 'resources/views/**'
  - resources/views/home.blade.php
---

# Views

## Brand assets via CDN brandUrl
Static RevisaLog brand images (favicon, apple-touch-icon, lockups, app-icon, og-image) must use AppStorage::brandUrl() or the brand-head-icons component so production serves them from the R2 CDN (cdn.revisalog.com.br). Do not use asset() for files under images/brand/ or public favicon paths.

## Landing uses real Flutter app screenshots
Landing marketing screenshots of the Flutter app live in public/images/landing, are published to R2 with brand:publish-to-r2, and are referenced with AppStorage::landingUrl(). Do not use asset() for those files. Do not use CSS-only fake phones when real captures exist. Keep Começar grátis behind @guest.

## Landing marquee spans full viewport RTL
The capability strip under the hero is a full-viewport RTL marquee: overflow hidden, track width max-content, at least 4 copies of the labels so one half is wider than 100vw, animation translateX(0) to translateX(-50%). Do not center or max-width the strip or it will only travel mid-to-left.

## Times shown to people use Brasília time
Timestamps are stored in UTC (config app.timezone). A time (or a date next to a time) taken from a timestamp goes through App\Support\DisplayTime::local() (config app.display_timezone, America/Sao_Paulo): the seal on /v/{code}, x-provenance-seal, the OS page ("Atualizada em … às HH:MM"), notifications, Maintenance::provenance_meta, the PDF mail and the history PDF agree to the minute. Date-only columns (maintenance_date) are not converted. Not converted yet: the admin blog schedule (its datetime-local input is read as UTC, so fix input and display together) and date-only created_at columns in admin lists.

## Placa nula
Nunca imprima license_plate direto: use filled() e, nas telas privadas, "Placa não informada". Telas públicas não mostram nada no lugar.

## Foto de manutenção nunca pelo caminho do storage
A miniatura, o link "abre em nova aba" e os itens da <x-ui.lightbox> em maintenances/_detail e
partials/maintenance-photos-form apontam para route('maintenance-photos.show', $photo) —
MaintenancePhotoController, atrás de auth e da MaintenancePhotoPolicy::view (nega enquanto
Maintenance::hidesAttachmentsFrom, como a nota). Não use $photo->url (AppStorage::url) numa tela de
portal: no disco público a foto abre sem login e, no S3/R2, a URL assinada é compartilhável — e a
foto de OS sem proprietário continuaria legível por quem tiver o link, mesmo com o
MaintenanceRedactor tirando-a da tela. A rota abre jpg/jpeg/png/webp inline (o resto baixa), com
nosniff, CSP sandbox e Cache-Control privado e curto; `private` tem de ser marcado no objeto de
resposta (`setPrivate()`), porque o BinaryFileResponse de response()->download() nasce público e
descarta um 'Cache-Control' do array de cabeçalhos. Exceção registrada: x-provenance-card ainda usa
$photo->url, porque só a busca por placa abre aquele bloco e lá x-vehicle.detail carrega apenas as
fotos públicas (veículo/depois); a rota responderia 403 a quem só buscou a placa. O arquivo sai pelo
MaintenancePhotoFileResponse, compartilhado com a rota da API. Do lado da API a foto também passou a
sair por rota autorizada (api.maintenance-photos.show), e a busca pública mantém a mesma exceção:
ver .ai/rules/api.md (agora com variantes em URL temporária, só depois da policy). As telas web seguem
apontando para a rota autorizada, que serve o master.

## Nota fiscal nunca pelo caminho do storage
O link "Abrir" de uma nota fiscal (maintenances/_detail e partials/workshop-maintenance-form) aponta
para route('invoices.show', $invoice) — InvoiceDownloadController, atrás de auth e da
InvoicePolicy::view. Não volte a usar AppStorage::url($invoice->file_path): no disco público o
arquivo abre sem login e, no S3/R2, a URL assinada é compartilhável. A rota serve só PDF na aba
(Content-Disposition inline); XML e o resto baixam, com nosniff e CSP sandbox, porque o arquivo é do
cliente.
