---
paths:
  - 'app/Mail/**'
---

# Mail

## Resend only on production infra
Production sends with MAIL_MAILER=resend from noreply@revisalog.com.br on Laravel Cloud. Reply-To and contact inbox are suporte@revisalog.com.br. Do not send From suporte@. Do not stand up Mailpit, local SMTP, or send real mail from a laptop. Do not put RESEND_API_KEY in local .env. Machine stays MAIL_MAILER=log so tests never leave the box. Inbox/DNS for suporte@ lives on prod Cloudflare / Laravel Cloud.

New accounts fire App\Events\UserRegistered (source web|api|oauth) after the registration transaction; discovered listeners queue welcome mail and the suporte ops alert. Do not put ShouldDispatchAfterCommit on that event — RefreshDatabase would swallow it; afterCommit lives on the queued mail/notification.

Markdown chrome lives in resources/views/vendor/mail/{html,text}/message.blade.php and uses config('mail.from.name') (Revisalog), never config('app.name') — production APP_NAME is still vehicle_maintenance.
