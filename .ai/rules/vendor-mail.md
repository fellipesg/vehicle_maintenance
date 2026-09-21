---
paths:
  - 'resources/views/vendor/mail/**'
---

# Vendor Mail

## Mail chrome uses mail.from.name, not APP_NAME
Header, title, and footer are the literal brand Revisalog. Do not interpolate APP_NAME, SMTP_FROM_NAME, or mail.from.name — those Cloud values are still Vehicle Maintenance System.
