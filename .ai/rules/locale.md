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

## As variáveis de ambiente dos testes precisam de `<server>`, não só de `<env>`
`APP_LOCALE`, `APP_FALLBACK_LOCALE` e `APP_FAKER_LOCALE` aparecem duas vezes em `phpunit.xml` e em
`phpunit.pgsql.xml` (`<env force="true">` **e** `<server>`) pelo mesmo motivo de todas as outras
variáveis que moldam o ambiente de teste: um shell que exporta o `.env` vence o `<env>`. A armadilha
inteira, com o mecanismo e o estrago no banco de desenvolvimento, está em `.ai/rules/ci.md`
("An exported environment variable beats `<env>` without `force`"). Ao mexer em um dos dois blocos,
mantenha o outro em sincronia.
