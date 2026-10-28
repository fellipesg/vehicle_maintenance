---
paths:
  - .github/workflows/**
  - Makefile
  - Dockerfile
  - phpunit.xml
  - phpunit.pgsql.xml
---

# CI and the test suite

## `artisan test` cannot take `--configuration`
`php artisan test` always appends its own `--configuration=phpunit.xml` (Collision's `TestCommand::getConfigurationFile()`), so passing the option duplicates it: PHPUnit prints `Option --configuration cannot be used more than once` and **exits 1 even when every test passes**. To run a different config (`phpunit.pgsql.xml`), call `vendor/bin/phpunit --configuration=...` directly — that is what CI and `make test-pgsql` do. `make test` keeps `artisan test --compact` because it uses the default config.

## Verify commands by their exit code, not their output
A green summary does not mean a green run. Piping a test command into `tail`/`grep` returns the pipe's status and hides a non-zero exit — that is how the duplicated `--configuration` reached CI. When validating a CI step locally, redirect to a file and check `$?`.

## An exported environment variable beats `<env>` without `force`
PHPUnit only applies `<env name="X" value="Y"/>` when `getenv('X') === false`, and even `force="true"` writes just `putenv()` and `$_ENV`. Laravel's env repository reads `$_SERVER` **before** `$_ENV` (`RepositoryBuilder::DEFAULT_ADAPTERS`), and the CLI puts every exported variable into `$_SERVER`. So a shell that exports the project's `.env` (`set -a; source .env`, docker compose, a direnv setup) silently wins over the test configuration.

The classic symptom is a wall of 419s on every web POST: `APP_ENV` stayed `local`, so the app is not in the testing environment and CSRF verification is on (measured: 372 failures). `QUEUE_CONNECTION=database` adds queue/notification failures because jobs are no longer dispatched synchronously.

The expensive one is the database. Docker compose exports `DB_CONNECTION`, `DB_DATABASE` and `DB_URL` into the process, so with `<env>` alone `env('DB_CONNECTION')` returns compose's `pgsql` and the suite points `RefreshDatabase` at the development database — `migrate:fresh` then **wipes the local data**. That is why `phpunit.xml` (sqlite `:memory:`) and `phpunit.pgsql.xml` (`vehicle_maintenance_test`) repeat those three `DB_*` in `<server>` too.

Both `phpunit.xml` and `phpunit.pgsql.xml` therefore pin every environment-shaping variable twice: `<env ... force="true"/>` and a `<server>` twin — `APP_ENV`, `APP_LOCALE`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, the `DB_*` and company. Keep the two blocks in sync. The one deliberate exception is the Postgres server coordinates in `phpunit.pgsql.xml` — `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` stay plain `<env>` so CI (`DB_HOST=127.0.0.1`), the compose host `db` and a host-side run on `127.0.0.1:5433` can all override them.

## The suite runs with `APP_DEBUG=false`, like production
Both configs pin `APP_DEBUG=false`. Do not flip it back to `true` to make something pass: debug on hides a whole class of API error bugs, because the renderer in `bootstrap/app.php` ends in `if (app()->hasDebugModeEnabled()) { return null; }` — with debug on, anything it fails to classify is rendered by Laravel and looks right, and only with debug off does it become `ApiResponse::error('An error occurred.', 500)`.

That is exactly how the rate-limit 500 shipped. `ThrottleRequests::buildException()` wraps a limiter's custom `response()` in an `HttpResponseException`, which is neither a `TooManyRequestsHttpException` nor an `HttpExceptionInterface`; the renderer matched no `instanceof`, fell through, and in production turned the `auth`/`auth-web` 429 (pt-BR message plus `Retry-After`) into a 500 with no `Retry-After` on `POST /api/v1/login`, `/api/v1/register` and the OAuth callback. The renderer now handles `HttpResponseException` by returning `null`, so Laravel sends the response the exception already carries, and the envelopes built there pass `$e->getHeaders()` through so the limiters without a `response()` of their own keep their `Retry-After` too.

The pin is one of two guards, not the only one: the rate-limit tests in `tests/Feature/AuthControllerTest.php` also set `config(['app.debug' => false])` themselves, so they keep covering the production path even if this value moves. Any new test that needs debug on should turn it on for itself rather than change the pin.

## The suite runs on SQLite and on Postgres
Production is Neon Postgres; `phpunit.xml` uses SQLite in memory and `phpunit.pgsql.xml` the compose Postgres. SQLite is the permissive one, so a test can only pass there and still be wrong:
- it does not enforce the `enum` check constraints, so writing a value outside the enum (e.g. `service_category => 'unknown'`) fails only on Postgres. Exercise view fallbacks for unmapped values **in memory**, without persisting them.
- it accepts loose types, so a boolean bound to a `bigint` column (`id != false`) fails only on Postgres.

Both jobs must be green before merging.

## Both image drivers are installed in CI, and a missing one must fail
`MaintenancePhotoImageProcessor` has an Imagick path and a GD path, because we do not control which one production brings; `MaintenancePhotoImageProcessorTest` drives both through a data provider. CI installs `gd` **and** `imagick` for that reason. Before `imagick` was on the `extensions` line, fourteen data sets called `markTestSkipped` on every machine and runner we have, and PHPUnit still exited 0 — the Imagick path shipped with no coverage anywhere and nothing said so. The test now **fails** instead of skipping when `CI` is set in the environment, so a runner image that drops the extension turns the job red instead of quietly shrinking the suite.

The `Dockerfile` deliberately stays on GD alone: `pecl install imagick` there costs `libmagickwand-dev` plus a build step for a driver the container does not need, and CI is where keeping the two-driver promise is cheap. So on a machine without the extension those fourteen data sets still skip. That is the expected local state, not the gap this section is about.

## `Sanctum::actingAs` gives a mock token whose key is `false`
It injects a Mockery mock of `PersonalAccessToken`, which is `instanceof Model` but whose `getKey()` returns `false`. Code that reads the current token's key must reject non-numeric keys (see `Api/PasswordController::update`), and a test that depends on which token survives must authenticate with a real `createToken()` plus a Bearer header.

## Pint reports "0 files" inside `.claude/worktrees`
Worktrees created under `.claude/` sit in a path Pint skips, so `vendor/bin/pint --test` there scans nothing and passes vacuously. Check style from the repository root, or copy the directory elsewhere before running it.
