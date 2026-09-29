---
paths:
  - 'app/Notifications/**'
---

# Notifications

## MailMessage salutation is RevisaLog
MailMessage defaults the footer to Regards + APP_NAME (vehicle_maintenance on Cloud). Always chain ->salutation('RevisaLog') on toMail(). From is noreply@revisalog.com.br / RevisaLog. The brand is spelled RevisaLog (capital L) in every user-visible string; the domain and addresses stay lowercase (revisalog.com.br). Gmail SMTP still rewrites the From address; production must use MAIL_MAILER=resend so Cloudflare Email Routing to suporte@ is not a self-send.
