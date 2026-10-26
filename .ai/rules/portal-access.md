---
paths:
  - 'bootstrap/app.php'
  - 'app/Support/PortalAccess.php'
  - 'app/Support/FriendlyHttpErrors.php'
  - 'app/Http/Controllers/Web/Auth/**'
  - 'app/Http/Controllers/Web/AuthController.php'
---

# Portal Access

## Wrong area redirects to the account's Início
A web 403 on another profile's area (/usuario, /garagem, /oficina, /admin) becomes a redirect to the account's Início with a notice, done in bootstrap/app.php through App\Support\FriendlyHttpErrors (not in the middleware). A 403 inside the account's own area keeps the pt-BR error page. 419 on login, sign-up, password, contact and logout forms returns to the form with a friendly message. The API keeps answering JSON.

## url.intended only inside the account's area
After login, url.intended is followed only when it points to this host and to a path the account can open (App\Support\PortalAccess::allowsIntendedUrl); otherwise the login goes to the Início. Guests opening a protected link go to the login of the portal that serves it. Admin accounts land on admin.dashboard (Portal::homeFor), while Portal::current() only shows the Admin area on admin.* routes.

## Oficina se cadastra sozinha, proprietário continua em /register
POST /para-oficinas/cadastro (StoreWorkshopSignupRequest, WorkshopSignupService) cria user_type workshop, tenant e Workshop pelo TenantService::createForUser (com os dados reais da oficina no lugar do endereço de demonstração), dispara UserRegistered (Web), loga e vai para workshop.dashboard com analytics_event sign_up e analytics_method workshop. Não há aprovação: o Workshop não tem flag disso e nasce no plano Free. O CNPJ (workshops.cnpj, único, 14 dígitos, dígitos verificadores por App\Rules\ValidCnpj) é obrigatório só aqui; oficinas antigas ficam com null. Usa o throttle auth-web, no mesmo balde 'register|ip' do /register. AuthController::register continua recusando user_type garage e workshop.

## Uma resposta pronta vence o envelope de erro da API
O renderizador de API em bootstrap/app.php trata `Illuminate\Http\Exceptions\HttpResponseException`
devolvendo `null`, para o Laravel enviar a resposta que a exceção já carrega. É por esse ramo que passa
o `->response(...)` dos limiters `auth` e `auth-web`: `ThrottleRequests::buildException()` embrulha o 429
custom nesse tipo, que não é `TooManyRequestsHttpException` nem `HttpExceptionInterface`. Sem ele, e só
com APP_DEBUG=false (produção), o 429 em pt-BR com `Retry-After` caía no fallback final e virava
`ApiResponse::error('An error occurred.', 500)`. Não mexa na ordem sem manter esse ramo antes do
fallback, e não troque o `null` por um envelope novo: a resposta do limiter é a resposta. Os envelopes
montados ali repassam `$e->getHeaders()`, para o 429 dos limiters sem `response()` também dizer quanto
esperar.
