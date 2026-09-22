---
paths:
  - 'app/Notifications/**'
---

# Notifications

## MailMessage salutation is Revisalog
MailMessage defaults the footer to Regards + APP_NAME (vehicle_maintenance on Cloud). Always chain ->salutation('Revisalog') on toMail(). From is noreply@revisalog.com.br / Revisalog. Gmail SMTP still rewrites the From address; production must use MAIL_MAILER=resend so Cloudflare Email Routing to suporte@ is not a self-send.
