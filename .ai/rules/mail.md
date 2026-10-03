---
paths:
  - 'app/Mail/**'
---

# Mail

## Resend only on production infra
Production sends with MAIL_MAILER=resend from noreply@revisalog.com.br on Laravel Cloud. Reply-To and contact inbox are suporte@revisalog.com.br. Do not send From suporte@. Do not stand up Mailpit, local SMTP, or send real mail from a laptop. Do not put RESEND_API_KEY in local .env. Machine stays MAIL_MAILER=log so tests never leave the box. Inbox/DNS for suporte@ lives on prod Cloudflare / Laravel Cloud.

New accounts fire App\Events\UserRegistered (source web|api|oauth) after the registration transaction; discovered listeners queue welcome mail and the suporte ops alert. Do not put ShouldDispatchAfterCommit on that event — RefreshDatabase would swallow it; afterCommit lives on the queued mail/notification.

Markdown chrome is the literal string RevisaLog (not APP_NAME, SMTP_FROM_NAME, or mail.from.name). config/mail.php from.address ignores SMTP_FROM_ADDRESS so stale Gmail SMTP env cannot become From. From name is hardcoded RevisaLog (brand spelling with capital L; the domain stays revisalog.com.br).

## Brand theme and welcome CTA
E-mails use the 'revisalog' theme (resources/views/vendor/mail/html/themes/revisalog.css): config('mail.markdown.theme') defaults to revisalog, so MailMessage notifications get it too, and the mailables also set public $theme = 'revisalog'. Notifications end with salutation('RevisaLog'). The welcome CTA depends on App\Enums\RegistrationSource: Web goes to Adicionar veículo, Api to the browser login, and Oauth (no known password) to password.request, never to the password login.
