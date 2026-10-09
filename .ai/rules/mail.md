---
paths:
  - 'app/Mail/**'
---

# Mail

## Resend only on production infra
Production sends with MAIL_MAILER=resend from noreply@revisalog.com.br on Laravel Cloud. Reply-To and contact inbox are suporte@revisalog.com.br. Do not send From suporte@. Do not stand up Mailpit, local SMTP, or send real mail from a laptop. Do not put RESEND_API_KEY in local .env. Machine stays MAIL_MAILER=log so tests never leave the box. Inbound mail for revisalog.com.br lives on Zoho Mail (MX mx/mx2/mx3.zoho.com, SPF include:zoho.com, DKIM selector zmail, DMARC p=none): one mailbox contato@ with aliases suporte@ and revisalog@. Cloudflare Email Routing is off. Resend keeps its own records (send. CNAME, resend._domainkey); do not touch them when editing the root SPF/MX.

New accounts fire App\Events\UserRegistered (source web|api|oauth) after the registration transaction; discovered listeners queue welcome mail and the suporte ops alert. Do not put ShouldDispatchAfterCommit on that event — RefreshDatabase would swallow it; afterCommit lives on the queued mail/notification.

Markdown chrome is the literal string RevisaLog (not APP_NAME, SMTP_FROM_NAME, or mail.from.name). config/mail.php from.address ignores SMTP_FROM_ADDRESS so stale Gmail SMTP env cannot become From. From name is hardcoded RevisaLog (brand spelling with capital L; the domain stays revisalog.com.br).

## Brand theme and welcome CTA
E-mails use the 'revisalog' theme (resources/views/vendor/mail/html/themes/revisalog.css): config('mail.markdown.theme') defaults to revisalog, so MailMessage notifications get it too, and the mailables also set public $theme = 'revisalog'. Notifications end with salutation('RevisaLog'). The welcome CTA depends on App\Enums\RegistrationSource: Web goes to Adicionar veículo, Api to the browser login, and Oauth (no known password) to password.request, never to the password login.

## Comunicados únicos aos usuários
`users:announce-ios-app` envia o aviso do app iOS (IosAppLaunchedMail, noreply@) uma vez por usuário, registrando em user_announcements; oficinas e contas de domínios reservados (.test, .invalid, example.com) ficam de fora. Envia em sequência com pausa (--pause-ms) por causa do limite do Resend, e falha não é registrada, para a próxima rodada tentar de novo. Rode antes com --dry-run.

## Convite ao cliente (CustomerInviteMail)
Sai pelo mailer padrão (transacional), na fila, nunca pelo mailer de outreach. Texto: oficina, marca/modelo/ano, data e link /convite/{token}; sem placa nem chassi. Traz "Você recebeu esta mensagem porque a oficina {nome} registrou um serviço no seu carro", List-Unsubscribe e link assinado /convite/descadastrar (e-mail criptografado na query) que grava em email_suppressions.
