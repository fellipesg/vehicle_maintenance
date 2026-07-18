# Vehicle Maintenance — Backend

API e portal web Laravel para histórico de manutenções veiculares, importação de CRLV-e, notas fiscais (PDF/XML) e exportação de relatórios.

> App mobile: [`vehicle_maintenance_frontend`](https://github.com/fellipesg/vehicle_maintenance_frontend)

## Stack

| Camada | Tecnologia |
|--------|------------|
| Runtime | PHP 8.2+ · Laravel 12 |
| Auth | Laravel Sanctum · Socialite (OAuth) |
| DB | SQLite (dev) · MySQL 8 (Docker) |
| Cache / queue | Redis · database queue |
| PDF | DomPDF · PDF Parser · FPDI |
| Push | Firebase Admin (kreait/firebase-php) |
| Front web | Blade · Vite · Tailwind |

## Pré-requisitos

- PHP 8.2+ com extensões comuns do Laravel (`pdo_sqlite` ou `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`)
- [Composer](https://getcomposer.org/) 2.x
- Node.js 20+ e npm (assets Vite)
- **Ou** Docker + Docker Compose (MySQL + Redis + app)

## Início rápido (local sem Docker)

```bash
git clone https://github.com/fellipesg/vehicle_maintenance.git
cd vehicle_maintenance

composer install
cp .env.example .env
php artisan key:generate

# SQLite (padrão do .env.example)
touch database/database.sqlite

php artisan migrate
# opcional: php artisan db:seed

npm install
npm run build

composer run dev
```

O script `composer run dev` sobe, em paralelo:

- `php artisan serve` (API + portal)
- queue worker
- Vite (`npm run dev`)
- logs (`pail`)

App: [http://127.0.0.1:8000](http://127.0.0.1:8000)  
API: `http://127.0.0.1:8000/api/v1`

> Em alguns ambientes a porta `8000` já está ocupada. Use `php artisan serve --port=8080` e ajuste `APP_URL`.

## Docker

```bash
cp .env.example .env
# Ajuste DB_* para MySQL do compose, por exemplo:
# DB_CONNECTION=mysql
# DB_HOST=db
# DB_PORT=3306
# DB_DATABASE=vehicle_maintenance
# DB_USERNAME=vehicle_user
# DB_PASSWORD=root

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Serviços típicos do `docker-compose.yml`: app, MySQL 8, Redis 7, Nginx (conforme configuração do projeto).

## Variáveis de ambiente

Copie `.env.example` → `.env`. Principais chaves:

| Variável | Descrição |
|----------|-----------|
| `APP_KEY` | Gerada por `php artisan key:generate` |
| `APP_URL` | URL pública (importante para OAuth e links) |
| `DB_*` | Conexão SQLite ou MySQL |
| `FILESYSTEM_DISK` | `local` ou `s3` |
| `AWS_*` | Credenciais S3 (se usar storage remoto) |
| `GOOGLE_*` / `FACEBOOK_*` / `TWITTER_*` | OAuth Socialite |
| Firebase credentials | Conta de serviço para FCM (fora do git) |

**Nunca** committe `.env`, chaves privadas ou JSON de service account.

## Testes

```bash
composer test
# ou
php artisan test
```

Com Docker:

```bash
docker compose exec app php artisan test
```

## Portal web

Além da API REST, o backend inclui rotas Blade para:

- **Usuário** — veículos, manutenções, importação CRLV, exportação PDF
- **Oficina / Garage** — fluxos de oficina e consignação
- **Admin** — catálogo de marcas/modelos

Após login no browser, navegue pelas rotas em `routes/web.php`.

## API (visão geral)

Prefixo: `/api/v1`

| Recurso | Exemplos |
|---------|----------|
| Auth | `POST /register`, `POST /login`, `POST /logout`, `GET /me` |
| Vehicles | CRUD + `GET /vehicles/{id}/maintenances` + export PDF |
| Maintenances | CRUD |
| Invoices | upload / download |
| Workshops | listagem e gestão |

Autenticação: Bearer token (Sanctum).

## Estrutura

```
app/
  Http/Controllers/Api/   # API REST
  Http/Controllers/Web/   # Portal Blade
  Services/               # CRLV, NF-e, ownership, catálogo
  Models/
database/migrations/
resources/views/          # Blade + PDF
routes/api.php
routes/web.php
tests/
```

## Scripts úteis

```bash
composer run setup   # install + .env + migrate + npm build
composer run dev     # serve + queue + vite + logs
composer run test
vendor/bin/pint      # estilo de código
```

## Segurança

- `.env`, caches e `vendor/` estão no `.gitignore`
- Não versionar dumps SQLite locais nem `storage/logs`
- Credenciais Firebase/OAuth apenas em ambiente local ou secrets de CI

## Licença

MIT
