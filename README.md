# Vehicle Maintenance

Laravel API and web portal for vehicle service history. Records stay on the vehicle, not on whoever currently owns it.

Mobile client: [vehicle_maintenance_frontend](https://github.com/fellipesg/vehicle_maintenance_frontend)

## What it does

- Register vehicles (plate / RENAVAM) and keep a permanent maintenance log
- Workshops, service categories, and checklists
- Upload invoices (NF-e XML and DANFE PDF); line items can be applied to a maintenance
- Import vehicles from CRLV PDFs
- Export a history PDF and email it to the owner with invoice files attached (queued job)
- Web portals for owners, workshops/garages, and catalog admin
- REST API (`/api/v1`) for the Flutter app (Sanctum)

## Stack

| Layer | Local | Production |
| --- | --- | --- |
| Runtime | PHP 8.4, Laravel 12 | Laravel Cloud |
| Database | SQLite or MySQL 8 | Neon Postgres |
| Files | Local disk | Amazon S3 |
| Queue | `database` driver (`queue:listen`) | Same driver, workers on Cloud |
| Auth | Sanctum, Socialite | Same |
| Observability | Log / Telescope (dev) | Sentry |
| Web UI | Blade, Vite, Tailwind | Same |

PDF: DomPDF, smalot/pdfparser, FPDI. Push: Firebase Admin (FCM).

## Requirements

- PHP 8.4+ (`pdo_sqlite` or `pdo_mysql` / `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`)
- Composer 2.x
- Node.js 20+ (Vite)
- Or Docker Compose (MySQL 8, Redis 7, PHP-FPM, Nginx)

## Quick start (no Docker)

```bash
git clone https://github.com/fellipesg/vehicle_maintenance.git
cd vehicle_maintenance/backend

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
composer run dev
```

`composer run dev` starts the HTTP server, a queue worker, Vite, and log tailing.

- App: http://127.0.0.1:8000
- API: `http://127.0.0.1:8000/api/v1`

If port 8000 is taken: `php artisan serve --port=8080` and set `APP_URL`.

## Docker

From `backend/`:

```bash
cp .env.example .env
# Set DB_CONNECTION=mysql, DB_HOST=db, DB_PORT=3306,
# DB_DATABASE=vehicle_maintenance, DB_USERNAME=vehicle_user, DB_PASSWORD=root

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Nginx listens on **8080** by default (`APP_PORT`).

## Production notes (Laravel Cloud + Neon + S3)

This app runs on Laravel Cloud with:

- **Neon Postgres** as the database
- **S3** for invoices and generated PDFs
- **Database queue** (no Redis required in prod)
- A worker that runs `EmailVehicleMaintenancePdf`: builds the history PDF, loads invoice objects from S3, and mails them as attachments (300s timeout, 2 tries)

### Postgres booleans

Laravel binds PHP `true`/`false` as `0`/`1`. Neon/Postgres treats that as integer, aborts the transaction, and the next statement can fail with `SQLSTATE[25P02]` (the original error is gone). This project uses `App\Database\PostgresConnection` so booleans are sent as `true`/`false`. Prefer the Neon host **without** `-pooler` for Laravel; if you must use the pooler, keep `DB_PGBOUNCER=true` (see `.env.example`).

### Typical production env

```env
DB_CONNECTION=pgsql
DB_HOST=ep-....aws.neon.tech
DB_PORT=5432
DB_SSLMODE=require
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database
SESSION_DRIVER=cookie
CACHE_STORE=file
```

Never commit `.env`, AWS keys, or Firebase service-account JSON.

## Environment

| Variable | Role |
| --- | --- |
| `APP_KEY` | `php artisan key:generate` |
| `APP_URL` | Public URL (OAuth and signed links) |
| `DB_*` | SQLite, MySQL, or Postgres/Neon |
| `FILESYSTEM_DISK` | `local` or `s3` |
| `AWS_*` | S3 bucket and credentials |
| `QUEUE_CONNECTION` | `database` in this project |
| `MAIL_*` | `log` locally; SMTP (or Cloud mail) in prod |
| `SENTRY_DSN` | Exception reporting |
| `GOOGLE_*` / `FACEBOOK_*` / `TWITTER_*` | Socialite |
| Firebase | Service account for FCM (not in git) |

## Tests

```bash
composer test
# or
php artisan test
```

Docker: `docker compose exec app php artisan test`

Coverage includes invoice parsers, CRLV import, ownership, portals, and Postgres boolean binding.

## API (overview)

Prefix: `/api/v1`. Authenticated routes use Sanctum (`Authorization: Bearer …`) and tenant middleware.

| Area | Examples |
| --- | --- |
| Auth | `POST /register`, `POST /login`, `POST /logout`, `GET /me`, OAuth redirect/callback |
| Vehicles | CRUD, `GET /my-vehicles`, `GET /vehicles/{id}/maintenances`, `GET /vehicles/{id}/export-pdf` |
| Search | `GET /vehicles/search/{identifier}` (plate or RENAVAM) |
| Maintenances | CRUD |
| Invoices | upload / download / delete |
| Workshops | public list + authenticated write |
| FCM | token register / list / delete |

Web (Blade): owner portal, workshop/garage flows, admin brand/model catalog. See `routes/web.php`.

## Layout

```
app/
  Http/Controllers/Api/    REST
  Http/Controllers/Web/    Blade portals
  Jobs/                    EmailVehicleMaintenancePdf
  Services/                Invoice, CRLV, ownership, PDF export
  Database/                PostgresConnection (boolean binding)
database/migrations/
resources/views/
routes/api.php
routes/web.php
tests/
```

## Scripts

```bash
composer run setup    # install, .env, migrate, npm build
composer run dev      # serve + queue + vite + logs
composer run test
vendor/bin/pint       # code style
```

## License

MIT. See [LICENSE](LICENSE).
