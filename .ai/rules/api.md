---
paths:
  - 'app/Http/Controllers/Api/**'
  - app/Http/Controllers/Api/VehicleController.php
  - app/Http/Controllers/Api/AuthController.php
  - app/Http/Resources/Api/V1/MaintenanceResource.php
  - app/Http/Resources/Api/V1/UserResource.php
  - app/Support/Maintenance/MaintenancePhotoUrls.php
  - app/Http/Resources/Api/V1/MaintenancePhotoResource.php
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
o mapa `admin.maps.users` as mostra. As duas últimas filtram `user_type = 'user'`: em conta de
garagem ou de oficina o mesmo dado é escrito e exibido em lugar nenhum, e geocodificá-lo só gastaria
chamada do Nominatim. A decisão é que a seção Endereço de "Meus Dados" sai do app para essas duas, e
esse tipo de edição acontece só na web.

Ordem obrigatória ao executar: **app primeiro, backend depois**. Tirar os campos de
`UpdateProfileRequest` ou de `UserResource` antes de o app parar de enviá-los faz a tela "salvar" sem
gravar nada, em silêncio. Enquanto o app não soltar a versão sem a seção, a API continua aceitando.

## Foto de manutenção: URL temporária só depois da policy, nunca o caminho do storage
`MaintenancePhotoResource` não devolve `path`, `thumb_path` nem `display_path`. Devolve `url` (a rota
autorizada `api.maintenance-photos.show`, `ability:maintenances:read`, `Gate::authorize('view', $photo)`,
mantida por compatibilidade) e `variants` {thumb, display, master}: URLs temporárias geradas por
`MaintenancePhotoUrls` **somente** quando o leitor passa a `MaintenancePhotoPolicy::view` (o resource
checa com o Gate, uma vez por OS e requisição; só a linha do tempo, que já aplicou viewFullHistory,
MaintenanceRedactor e `hidesAttachmentsFrom`, passa `forViewer($viewer, authorized: true)`). Não volte a
expor `AppStorage::url($photo->path)` nem o caminho: no disco público o arquivo abre sem token e a URL
assinada do S3/R2 é compartilhável até expirar.

- **Vida e janelas.** TTL de 10 minutos com a expiração arredondada para janelas fixas:
  `floor(agora/600)*600 + 1200` (`MaintenancePhotoUrls::expiresAt`). Requisições na mesma janela recebem
  a mesma URL, então a resposta da linha do tempo não muda a cada chamada (cache/ETag do app seguem
  devolvendo 304). O S3/R2 grava o horário da assinatura (X-Amz-Date) na URL, que mudaria a cada chamada;
  por isso as URLs de cada foto e janela ficam no cache da aplicação (uma leitura por foto). `expires_at`
  (ISO 8601) acompanha as variantes. Foto ainda sem processar (ou antiga) devolve a URL do original em
  `thumb` e `display`, com `processed: false`.
- **Disco local** (desenvolvimento e testes): `Storage::disk('public')->buildTemporaryUrlsUsing()`
  (AppServiceProvider, e de novo em `MaintenancePhotoUrls` porque `Storage::fake` troca a instância)
  aponta para `GET /api/v1/maintenance-photo-files/{photo}/{variant}` (`signed`, sem auth:sanctum),
  que vale por uma foto e uma variante e serve pelo `MaintenancePhotoFileResponse`.
- **Nada de redirect 302** do endpoint autorizado para a URL do storage: o HttpClient do Dart pode
  reenviar o `Authorization` no redirect e o S3/R2 recusa dois mecanismos de autenticação.
- Também saem `width`, `height` (do master), `dominant_color` (hex, para o placeholder) e `processed`.
- Linha do tempo (`GET /vehicles/{id}/timeline`) e detalhe/lista da OS trazem `photos_count`,
  `photos_locked` e, na linha do tempo, `photo_preview` (até 2, no formato do resource). Bloqueada
  (anexos de OS sem dono que o leitor não vê): `photos_count` é o total, `photos_locked` true e a prévia
  vazia. Liberada: a contagem é só do que o leitor enxerga (fora do tenant e da oficina autora, veículo
  + depois). As fotos vêm numa query por linha do tempo (sem N+1; há teste que compara a contagem de
  queries).
- A URL assinada de uma foto continua abrindo até expirar (no máximo 20 minutos) mesmo se a OS for
  oculta ou o aceite revogado depois; é o preço da janela estável.

**Exceção: a busca pública segue com a URL do storage.** `PublicVehicleSearchResource` (de
`GET /api/v1/vehicles/search/{identifier}`, sem middleware de autenticação) mantém `photos[].url` em
`$photo->url`. A busca por placa é aberta, lá só entram as fotos públicas (`isPublicVisible()`, veículo
+ depois) e a rota autorizada responderia 403 a quem só buscou a placa. É a mesma exceção que o
`x-provenance-card` tem na web (registrada em `.ai/rules/views.md`). Se um dia essa lista precisar de
foto não pública, a rota não serve: o caminho é uma URL assinada de vida curta só para ela.

## Preferência de fotos no PDF: GET/PUT /api/v1/me
`export_include_photos` (true | false | null) vem em UserResource, logo em GET /api/v1/me, login e PUT /api/v1/me. Nulo = o proprietário nunca respondeu (o app pergunta uma vez). O PUT aceita só esse campo (`sometimes|boolean`, `name` também é `sometimes`, então atualização parcial funciona) e recusa null e texto com 422. Só vale para a conta de proprietário; o PDF consulta a preferência do dono ATUAL do veículo (.ai/rules/pdfs.md). Na web é o cartão "PDF do histórico" em /conta (PUT /conta/preferencias, só `user.type:user`).

## Fotos só para quem tem o recurso ligado
Sem as fotos das manutenções na conta (flag por conta, features.md): POST /maintenances/{m}/photos responde 403; MaintenanceResource devolve `photos` vazio, `photos_count` 0 e `photos_locked` false (o campo continua existindo); a linha do tempo devolve `photos_count` 0, `photos_locked` false e `photo_preview` vazio; as rotas autorizadas respondem 403 e nenhuma URL assinada é emitida (uma URL já emitida vale até expirar, no máximo 20 minutos, porque a rota assinada não tem usuário). `attachments.photos` dos registros de oficina vira 0. A busca pública por placa (GET /vehicles/search/{identifier}) é a exceção documentada: segue com as fotos públicas, com ou sem o recurso.

`GET /api/v1/me`, login e cadastro trazem `features: {"maintenance_photos": true|false}` em UserResource (só na própria conta, nunca no autor de uma OS aninhada), para o app esconder a interface. `export_include_photos` continua no payload, mas o PUT /me o recusa com 422 quando a conta não tem o recurso.
