---
paths:
  - 'app/Listeners/**'
---

# Listeners

## Registration mail uses UserRegistered, not ShouldDispatchAfterCommit
New accounts dispatch App\Events\UserRegistered (source web|api|oauth) after User::create + TenantService. Event discovery runs SendWelcomeEmail, SendSignupOpsAlert (Notification::route to legal.support_email), and SendWelcomePush (API/OAuth only). Do not implement ShouldDispatchAfterCommit on the event — RefreshDatabase never commits, so listeners would not run in tests. afterCommit() belongs on the queued WelcomeUserMail / NewUserSignupAlertNotification. Login must not fire the event or FCM welcome.
