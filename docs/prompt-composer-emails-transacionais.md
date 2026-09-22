# Prompt — Composer 2.5

Copie o bloco abaixo inteiro para o Composer 2.5. Escopo: só o código de e-mail transacional no backend Laravel. Não configurar Resend/Cloudflare/Laravel Cloud.

---

Implement transactional registration emails for Revisalog (Laravel 12 / PHP 8.4) in `backend/`. Do not set up Mailpit, local SMTP, or Resend keys on the laptop. Machine stays `MAIL_MAILER=log`. Production will send via Resend from `noreply@revisalog.com.br` with Reply-To `suporte@revisalog.com.br` (already in `config/mail.php` and `backend/.ai/rules/mail.md`).

## Before writing code

1. Run `graphify query "register welcome mail notifications WorkshopFollowUpNotification AuthController"` from the repo that has `graphify-out/`.
2. Read `backend/.ai/rules/index.md` and every rule whose globs match files you will touch (`mail.md`, `web-api.md`, `jobs.md`, `config.md`, `views.md`).
3. Read sibling files before inventing a new pattern. Use Laravel Boost `search-docs` for mail, notifications, events (`packages: ["laravel/framework"]`).
4. Read `.cursor/skills/laravel-best-practices/rules/mail.md`, `events-notifications.md`, `testing.md`, `architecture.md`.

## Product rules

- **From** is always `config('mail.from')` → `noreply@revisalog.com.br` / Revisalog. Never From `suporte@`.
- **Reply-To** on user-facing mail: `config('mail.reply_to')` except workshop follow-up (below).
- Internal alerts (new signup, existing contact form) **To** `config('legal.support_email')` (`suporte@revisalog.com.br`).
- Brand every user-visible string **Revisalog**, never “Vehicle Maintenance”.
- Queue everything: `ShouldQueue` + `afterCommit()` when dispatched inside a registration transaction. Tests use `Mail::fake()` / `Notification::fake()` and **`assertQueued`**, not `assertSent`.
- PHPUnit only (`php artisan make:test --phpunit`). Do not add Pest. Do not add Composer packages. Do not change Laravel Cloud env. Do not create docs unless a rule file needs a one-line update.
- Do not rebuild workshop template CRUD, legal pages, footer, or contact form — they already exist.

## What to build

### 1. Single registration event

Public signup is user-only (`backend/.ai/rules/web-api.md`). After `User::create` + `TenantService::createForUser`:

- `app/Http/Controllers/Web/AuthController.php` `register` — **does not** notify today.
- `app/Http/Controllers/Api/AuthController.php` `register` — FCM welcome only, branded Vehicle Maintenance.
- API OAuth callback — FCM welcome only when `$isNewUser`.

Introduce one event (prefer `Illuminate\Auth\Events\Registered` or a small `App\Events\UserRegistered` with `ShouldDispatchAfterCommit` and a `source`: `web|api|oauth`). Fire it only for **new** accounts, never on login.

Event-discover listeners (no manual `Event::listen` in the provider):

1. **Welcome email** to the new user — queued Notification or markdown Mailable (`ShouldQueue`). Short PT-BR: greeting with first name, what Revisalog is (histórico no veículo, não na conta), CTA to the matching dashboard/login URL, Reply-To suporte. Do **not** send a welcome email on login. You may keep FCM welcome on API register/OAuth new user but rebrand to Revisalog; **remove or skip FCM on login** if you touch that method so login does not look like a new signup. Extract `sendWelcomeNotification` out of the controller if it stays — controllers should not own mail/FCM.

2. **Ops alert** to `config('legal.support_email')` via `Notification::route('mail', …)` (on-demand, no dummy User). Include name, email, user_type, source. Queued.

### 2. Existing mail — branding only

- `VehicleMaintenancePdfMail` / `resources/views/emails/vehicle-maintenance-pdf.blade.php`: drop “Vehicle Maintenance”.
- `MaintenanceKmReminderNotification` and `WorkshopFollowUpNotification`: already Reply-To suporte. Keep From global. On workshop follow-up, if `$this->template->workshop->email` is a non-empty valid email, Reply-To that workshop (name = workshop name); otherwise keep suporte. Do not change dispatcher/dedupe logic.
- If you publish Markdown mail components, set the footer/app name to Revisalog. Do not invent a second email CSS system.

Workshop templates (`WorkshopMessageTemplate` + `WorkshopMessageTemplateRenderer` placeholders `{{workshop_name}}`, `{{customer_name}}`, `{{vehicle}}`, `{{estimated_km}}`, `{{next_due_km}}`, `{{days_since_service}}`, `{{last_service}}`) already render into `WorkshopFollowUpNotification`. Do not replace that pipeline.

### 3. Tests

Update/create PHPUnit feature tests:

- Web `POST /register` queues welcome to the user and ops alert to suporte. Guest happy path already in `tests/Feature/Web/AuthTest.php`.
- API `POST /api/v1/register` same. Existing `tests/Feature/AuthControllerTest.php`.
- Login (web and API) queues **neither**.
- OAuth new user: queue both if you can fake Socialite the way existing tests do; skip OAuth if there is no established Socialite fake — do not invent a brittle new OAuth stack.
- Workshop follow-up mail: Reply-To workshop email when present; suporte otherwise. Extend `tests/Feature/WorkshopFollowUpDispatcherTest.php` or a focused notification test.
- PDF mail HTML no longer contains “Vehicle Maintenance” (`tests/Feature/EmailVehicleMaintenancePdfTest.php` if it asserts body).

Follow existing TestCase / RefreshDatabase / factory conventions. `php artisan test --compact` only the files you touched.

## After edits

- `vendor/bin/pint --dirty` from `backend/`.
- `graphify update .` from the graphify root if you changed PHP.
- Record a durable rule with Boost `record-rule` only if you settle a non-obvious mail/event convention (glob `app/Mail/**` or `app/Listeners/**`).

## Out of scope

Resend dashboard, Cloudflare DNS, Laravel Cloud env vars, Mailpit, welcome-back emails, marketing campaigns, changing workshop template placeholders, frontend Flutter except if an API contract already documents welcome (do not add new API fields).
