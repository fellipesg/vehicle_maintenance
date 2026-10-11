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

## Google Analytics follows the privacy policy
GA4 (x-analytics, GA_MEASUREMENT_ID in Laravel Cloud) loads only on public pages and sets analytics_storage 'granted' with every ad signal denied (ad_storage, ad_user_data, ad_personalization, Google signals). That is legitimate interest disclosed in privacy sections 2, 3, 6 and 10 (privacy_version 2026-10-10). Measuring a portal page, turning on ad signals or adding another tracker changes the policy: update config/legal.php and bump privacy_version in the same change.

## Textos para registros de oficinas
config/legal.php (terms 6. Oficinas; privacy 5. Registros de oficinas em veículos sem conta) descreve dado mínimo, base legal, anexos pendentes de 90 dias, escolhas do proprietário e convite por hash. Mudar o que o fluxo guarda ou o prazo exige atualizar o texto e a versão juntos. As chaves maintenance.invite_email_daily_limit e maintenance.pending_attachments_retention_days ficam em config/maintenance.php.
