---
paths:
  - 'lang/**'
  - 'app/Http/**'
  - 'config/app.php'
  - 'phpunit.xml'
  - 'phpunit.pgsql.xml'
---

# Locale

## The app runs in pt_BR
Validation messages come from lang/pt_BR/validation.php and framework strings from lang/pt_BR.json. A new field goes into validation attributes with its glossary name (placa, chassi, RENAVAM, número do CRV, quilometragem, nota fiscal). Do not write English messages in web code. The auth throttle uses trans_choice('auth.too_many_attempts'). Both phpunit.xml and phpunit.pgsql.xml force APP_LOCALE, APP_FALLBACK_LOCALE and APP_FAKER_LOCALE to pt_BR; production (Laravel Cloud) must set APP_LOCALE=pt_BR too, or an old APP_LOCALE=en wins over the config default.

## API messages are still English on purpose
Some API messages (ApiResponse::validation, the error envelope in bootstrap/app.php, TwoFactor/Auth/Invoice controllers, RegisterRequest, UpdateProfileRequest) are English. Translate them only in an API change that first confirms the Flutter app does not compare those strings.

## Mensagens de registros de oficinas
Decisão do proprietário e convite respondem 422 com message em pt-BR no envelope da API (ApiResponse::error com a primeira mensagem); e-mail inválido ou suprimido usa a mesma frase genérica "Não foi possível enviar para este e-mail." para não revelar a supressão.

## Os DB_* dos testes precisam de <server>, não só de <env>
No Docker o compose exporta `DB_CONNECTION`, `DB_DATABASE` e `DB_URL` para o processo, e o PHP
recebe isso em `$_SERVER`. O repositório de env do Laravel lê `$_SERVER` **antes** de `$_ENV`, e o
`<env force="true">` do PHPUnit só escreve `putenv()` e `$_ENV`. Resultado: com apenas as linhas
`<env>`, `env('DB_CONNECTION')` devolve o `pgsql` do compose e `php artisan test` roda no banco de
desenvolvimento — o `RefreshDatabase` faz `migrate:fresh` e apaga os dados locais. `phpunit.xml`
(sqlite `:memory:`) e `phpunit.pgsql.xml` (`vehicle_maintenance_test`) repetem os três `DB_*` em
`<server>`; mantenha os dois blocos em sincronia ao mexer em qualquer um deles.

Isso vale para toda variável que molda o ambiente de teste, não só os `DB_*`: `APP_ENV`,
`APP_LOCALE`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` e companhia têm `<env force>`
**e** `<server>` nos dois arquivos. Ver `.ai/rules/ci.md` ("An exported environment variable beats
`<env>` without `force`").
