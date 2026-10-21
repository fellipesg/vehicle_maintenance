---
paths:
  - .github/workflows/**
  - Makefile
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

The classic symptom is a wall of 419s on every web POST: `APP_ENV` stayed `local`, so the app is not in the testing environment and CSRF verification is on (measured: 372 failures). `QUEUE_CONNECTION=database` adds queue/notification failures because jobs are no longer dispatched synchronously, and `DB_CONNECTION`/`DB_DATABASE` point `RefreshDatabase` at the development database.

Both `phpunit.xml` and `phpunit.pgsql.xml` therefore pin every environment-shaping variable twice: `<env ... force="true"/>` and a `<server>` twin. Keep the two blocks in sync. The one deliberate exception is the Postgres server coordinates in `phpunit.pgsql.xml` — `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` stay plain `<env>` so CI (`DB_HOST=127.0.0.1`), the compose host `db` and a host-side run on `127.0.0.1:5433` can all override them.

## The suite is only green with `APP_DEBUG=true`
Both configs pin `APP_DEBUG=true`, the value the suite has always run with (the local `.env` and the `.env.example` CI copies both set it). Flipping it to `false` fails the four rate-limit tests in `tests/Feature/AuthControllerTest.php`: `ThrottleRequests` wraps a limiter's custom `response()` in an `HttpResponseException`, which is neither a `TooManyRequestsHttpException` nor an `HttpExceptionInterface`, so the API renderer in `bootstrap/app.php` falls through to `ApiResponse::error('An error occurred.', 500)` and the 429 never reaches the client. With debug on, that same renderer returns `null` and Laravel renders the exception's own response. This is a production bug (production runs with debug off), not a test artefact: fix the renderer before changing the pinned value.

## The suite runs on SQLite and on Postgres
Production is Neon Postgres; `phpunit.xml` uses SQLite in memory and `phpunit.pgsql.xml` the compose Postgres. SQLite is the permissive one, so a test can only pass there and still be wrong:
- it does not enforce the `enum` check constraints, so writing a value outside the enum (e.g. `service_category => 'unknown'`) fails only on Postgres. Exercise view fallbacks for unmapped values **in memory**, without persisting them.
- it accepts loose types, so a boolean bound to a `bigint` column (`id != false`) fails only on Postgres.

Both jobs must be green before merging.

## `Sanctum::actingAs` gives a mock token whose key is `false`
It injects a Mockery mock of `PersonalAccessToken`, which is `instanceof Model` but whose `getKey()` returns `false`. Code that reads the current token's key must reject non-numeric keys (see `Api/PasswordController::update`), and a test that depends on which token survives must authenticate with a real `createToken()` plus a Bearer header.

## Pint reports "0 files" inside `.claude/worktrees`
Worktrees created under `.claude/` sit in a path Pint skips, so `vendor/bin/pint --test` there scans nothing and passes vacuously. Check style from the repository root, or copy the directory elsewhere before running it.
