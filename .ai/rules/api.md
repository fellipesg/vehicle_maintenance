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
GET /api/v1/auth/{provider}/redirect responde 200 com success=false e error_code OAUTH_NOT_CONFIGURED enquanto services.mobile_oauth_enabled (MOBILE_OAUTH_ENABLED) for false, que é o padrão. O fluxo pelo navegador nunca devolvia o login ao app (sem deep link) e o GOOGLE_REDIRECT_URI de produção era localhost:8080. O Google entra pelo login nativo: POST /api/v1/auth/google recebe o id_token do google_sign_in e o GoogleIdTokenVerifier confere assinatura (JWKS do Google), emissor, audiência (services.google.token_audiences, o cliente Web 61844221484-b7jtd51c… do projeto vehicle-maintenance-a9e32, usado como serverClientId no app), validade e email_verified. Vincula por provider_id ou e-mail, como a Apple; vincular não apaga a foto do usuário.

## Registros de oficinas (contrato com o app)
GET /api/v1/me/workshop-records?status=pending|all, POST /api/v1/maintenances/{id}/owner-decision {link, attach_files, hide_from_public}, GET /api/v1/workshop/vehicles/lookup?chassis=, POST /api/v1/workshop/vehicles, POST /api/v1/maintenances/{id}/invites/email|whatsapp, e pending_workshop_records_count em GET /api/v1/me. MaintenanceResource ganhou is_ownerless_record, owner_status (null em OS comum), attachments_status (null em OS comum) e hidden_from_public. Erros de decisão e convite voltam 422 com message em pt-BR.

## Endereço é da empresa, não da conta (contrato com o app)
A conta não tem endereço na web: `/conta` edita só nome, e-mail, senha e telefone, nos quatro
portais. Quem tem endereço é a loja (`garages`, em "Minha loja") e a oficina (`workshops`, em "Minha
oficina"), e é de lá que sai o que o proprietário de um veículo em consignação vê
(`User::storeIdentity()`). Não faça `storeIdentity()` cair no endereço da conta quando o da loja
está vazio: o e-mail fala em nome da loja e mostraria o endereço de outro lugar.

As colunas de endereço em `users` continuam existindo para o **proprietário**: `GET /api/v1/me` e
`PUT /api/v1/me` as expõem, o app as edita em "Meus Dados", `geo:geocode-addresses` as geocodifica e
o mapa `admin.maps.users` as mostra — filtrando `user_type = 'user'`. Em conta de garagem ou de
oficina o mesmo dado é escrito, geocodificado e exibido em lugar nenhum: a decisão é que a seção
Endereço de "Meus Dados" sai do app para essas duas, e esse tipo de edição acontece só na web.

Ordem obrigatória ao executar: **app primeiro, backend depois**. Tirar os campos de
`UpdateProfileRequest` ou de `UserResource` antes de o app parar de enviá-los faz a tela "salvar" sem
gravar nada, em silêncio. Enquanto o app não soltar a versão sem a seção, a API continua aceitando.

## Foto de manutenção nunca pelo caminho do storage
`MaintenancePhotoResource` devolve em `url` a rota `api.maintenance-photos.show`
(`GET /api/v1/maintenance-photos/{photo}`, `ability:maintenances:read`, `Gate::authorize('view', $photo)`
pela `MaintenancePhotoPolicy::view`) e não devolve mais `path`. Não volte a expor
`AppStorage::url($photo->path)` nem o `path`: no disco público o arquivo abre sem token e, no S3/R2, a
URL assinada é compartilhável até expirar — a foto de uma OS sem proprietário continuaria legível por
quem tiver o link, mesmo com o `MaintenanceRedactor` tirando-a do resource. `url` só aparece para
requisição autenticada (como `download_url` da `InvoiceResource`), e o arquivo sai pelo
`MaintenancePhotoFileResponse`, o mesmo da rota web `maintenance-photos.show`: jpg/jpeg/png/webp
inline, o resto baixa, com nosniff, CSP sandbox e `Cache-Control` privado e curto. O app Flutter não lia
`photos` nem `path` quando isto mudou (nenhum `.dart` cita as chaves), então o contrato pôde mudar sem
mexer no app.

**Exceção: a busca pública segue com a URL do storage.** `PublicVehicleSearchResource` (de
`GET /api/v1/vehicles/search/{identifier}`, sem middleware de autenticação) mantém `photos[].url` em
`$photo->url`. A busca por placa é aberta, lá só entram as fotos públicas (`isPublicVisible()`, veículo
+ depois) e a rota autorizada responderia 403 a quem só buscou a placa. É a mesma exceção que o
`x-provenance-card` tem na web (registrada em `.ai/rules/views.md`). Se um dia essa lista precisar de
foto não pública, a rota não serve: o caminho é uma URL assinada de vida curta só para ela.

## Preferência de fotos no PDF: GET/PUT /api/v1/me
`export_include_photos` (true | false | null) vem em UserResource, logo em GET /api/v1/me, login e PUT /api/v1/me. Nulo = o proprietário nunca respondeu (o app pergunta uma vez). O PUT aceita só esse campo (`sometimes|boolean`, `name` também é `sometimes`, então atualização parcial funciona) e recusa null e texto com 422. Só vale para a conta de proprietário; o PDF consulta a preferência do dono ATUAL do veículo (.ai/rules/pdfs.md). Na web é o cartão "PDF do histórico" em /conta (PUT /conta/preferencias, só `user.type:user`).
