---
paths:
  - config/legal.php
  - config/mail.php
---

# Config

## Legal copy lives in config/legal.php
Terms and privacy are the single source in config/legal.php. Web /termos and /privacidade, the API legal endpoint, and the terms-scroll-accept widget all read that file. Bump terms_version when the text changes.

## From address ignores SMTP_FROM_* leftovers
mail.from.address reads only MAIL_FROM_ADDRESS (default noreply@revisalog.com.br). mail.from.name is Revisalog. Do not fall back to SMTP_FROM_ADDRESS or SMTP_FROM_NAME — those Cloud leftovers still say Gmail / Vehicle Maintenance System and make Cloudflare Email Routing think suporte@ is a self-send.
