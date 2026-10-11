---
paths:
  - 'app/Listeners/**'
---

# Listeners

## Registration mail uses UserRegistered, not ShouldDispatchAfterCommit
New accounts dispatch App\Events\UserRegistered (source web|api|oauth) after User::create + TenantService. Event discovery runs SendWelcomeEmail, SendSignupOpsAlert (Notification::route to legal.support_email), and SendWelcomePush (API/OAuth only). Do not implement ShouldDispatchAfterCommit on the event — RefreshDatabase never commits, so listeners would not run in tests. afterCommit() belongs on the queued WelcomeUserMail / NewUserSignupAlertNotification. Login must not fire the event or FCM welcome.

O cadastro próprio de oficina usa o mesmo UserRegistered (source Web): o alerta ao suporte sai com assunto "Novo cadastro de oficina", nome fantasia e CNPJ, e as boas-vindas usam emails.welcome-workshop (botão "Ir para o Início").

## Sem listener para registros de oficinas
O aviso de chegada é chamado direto por VehicleOwnershipService e pelo API VehicleController (WorkshopRecordsArrivalNotifier), não por evento: precisa saber o veículo e a conta no mesmo ponto do vínculo.
