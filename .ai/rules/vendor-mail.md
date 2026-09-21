---
paths:
  - 'resources/views/vendor/mail/**'
---

# Vendor Mail

## Mail chrome uses mail.from.name, not APP_NAME
Published markdown wrappers in resources/views/vendor/mail/html/{message,layout}.blade.php and text/message.blade.php must brand as config('mail.from.name') (Revisalog). Do not use config('app.name') in mail title, header, or footer — Cloud APP_NAME is still vehicle_maintenance.
