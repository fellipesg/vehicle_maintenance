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

## Servidor local com limite de upload de 20 MB
`php artisan serve` não repassa flags `-d` ao processo filho (`php -S`), então `-d upload_max_filesize=20M` no comando dele não tem efeito e uploads acima de 2 MB falham localmente. O `composer run dev` sobe o servidor com `php -S` a partir de `public/`, usando `vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` e as flags no próprio processo. Não volte a usar `artisan serve` com `-d` para testar upload de nota fiscal. A mensagem de erro em `StoresMaintenanceInvoices` aponta para `composer run dev`.
