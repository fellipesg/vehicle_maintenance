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

## The suite runs on SQLite and on Postgres
Production is Neon Postgres; `phpunit.xml` uses SQLite in memory and `phpunit.pgsql.xml` the compose Postgres. SQLite is the permissive one, so a test can only pass there and still be wrong:
- it does not enforce the `enum` check constraints, so writing a value outside the enum (e.g. `service_category => 'unknown'`) fails only on Postgres. Exercise view fallbacks for unmapped values **in memory**, without persisting them.
- it accepts loose types, so a boolean bound to a `bigint` column (`id != false`) fails only on Postgres.

Both jobs must be green before merging.

## `Sanctum::actingAs` gives a mock token whose key is `false`
It injects a Mockery mock of `PersonalAccessToken`, which is `instanceof Model` but whose `getKey()` returns `false`. Code that reads the current token's key must reject non-numeric keys (see `Api/PasswordController::update`), and a test that depends on which token survives must authenticate with a real `createToken()` plus a Bearer header.

## Pint reports "0 files" inside `.claude/worktrees`
Worktrees created under `.claude/` sit in a path Pint skips, so `vendor/bin/pint --test` there scans nothing and passes vacuously. Check style from the repository root, or copy the directory elsewhere before running it.
