---
paths:
  - 'resources/views/vendor/mail/**'
---

# Vendor Mail

## Mail chrome uses mail.from.name, not APP_NAME
Header, title, and footer are the literal brand Revisalog. Do not interpolate APP_NAME, SMTP_FROM_NAME, or mail.from.name — those Cloud values are still Vehicle Maintenance System.

## Mail header uses CDN lockup
HTML mail header uses AppStorage::brandUrl('lockup-horizontal.png') with alt Revisalog. Do not use asset() for brand files. Gmail inbox avatar is BIMI, not this header.
