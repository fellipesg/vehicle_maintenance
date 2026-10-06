---
paths:
  - 'app/Http/Controllers/Api/**'
  - app/Http/Controllers/Api/VehicleController.php
  - app/Http/Controllers/Api/AuthController.php
---

# Api

## Enterprise API patterns
Use ApiResponse for all JSON responses with success/data/message/errors/meta/links envelope. Use Eloquent API Resources in app/Http/Resources/Api/V1/ for explicit field exposure. Use Form Requests in app/Http/Requests/Api/V1/ instead of inline Validator. Paginate all list endpoints (per_page default 15, max 100). Enforce Sanctum abilities on protected routes via ability middleware; session-authenticated web clients bypass ability checks.

## Listagens de veículos com ?include=
Endpoints de listagem (`myVehicles`, `index`) não devem carregar `plates` nem `provenanceStripMaintenances` por padrão. Use `VehicleListIncludes` e `?include=plates,provenance_strip` quando o cliente precisar desses campos.

## iOS Sign in with Apple is required
The iOS app offers Google, Facebook, and X login, so Guideline 4.8 requires native Sign in with Apple on the same owner and lojista screens, above those buttons. POST /api/v1/auth/apple verifies the identity token (audience br.com.revisalog.app) against Apple JWKS and stores the Apple subject plus the email Apple returns, including @privaterelay.appleid.com. Do not remove the button or accept an unverified token. The App ID needs the Sign in with Apple capability.

## Login social pelo navegador desligado no app
GET /api/v1/auth/{provider}/redirect responde 200 com success=false e error_code OAUTH_NOT_CONFIGURED enquanto services.mobile_oauth_enabled (MOBILE_OAUTH_ENABLED) for false, que é o padrão. O fluxo pelo navegador nunca devolvia o login ao app (sem deep link) e o GOOGLE_REDIRECT_URI de produção era localhost:8080. Não religue sem login nativo (token do Google verificado no backend, como em POST /auth/apple) ou retorno ao app por App Link/Universal Link.
