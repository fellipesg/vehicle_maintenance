---
paths:
  - config/legal.php
  - config/mail.php
---

# Config

## Legal copy lives in config/legal.php
Terms and privacy are the single source in config/legal.php. Web /termos and /privacidade, the API legal endpoint, and the terms-scroll-accept widget all read that file. Bump terms_version (and privacy_version for the policy) when the substance of the text changes. The versions are dates shown as "em vigor desde" and stored on acceptance, so a cosmetic fix (the RevisaLog brand spelling, typos) keeps them.

## From address ignores SMTP_FROM_* leftovers
mail.from.address reads only MAIL_FROM_ADDRESS (default noreply@revisalog.com.br). mail.from.name is RevisaLog. Do not fall back to SMTP_FROM_ADDRESS or SMTP_FROM_NAME — those Cloud leftovers still say Gmail / Vehicle Maintenance System and make suporte@ look like a self-send to the inbox (now Zoho).

## Markdown mail theme
config/mail.php markdown.theme reads MAIL_MARKDOWN_THEME with default 'revisalog' and markdown.paths points to resources/views/vendor/mail. Do not set MAIL_MARKDOWN_THEME=default in production: notifications would lose the brand chrome.
