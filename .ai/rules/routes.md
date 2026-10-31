---
paths:
  - 'routes/web.php'
  - 'routes/api.php'
---

# Routes

## Rota que recebe arquivo entra no balde `uploads`

Toda rota que aceita upload leva `throttle:uploads` (10/min por conta ou IP,
`App\Providers\AppServiceProvider`), em qualquer portal e na API — não só as rotas cujo nome fala de
upload. Nas três superfícies de OS isso inclui o store e o update, porque o formulário inteiro vem
com `invoices`: `App\Rules\InvoiceFile` lê o arquivo (MIME por conteúdo, cabeçalho do PDF e uma
passada de `XMLReader`), então uma requisição sem limite paga parsing de verdade. Na API as rotas de
manutenção ficam com os dois baldes: `throttle:api` do grupo e `throttle:uploads` por cima.

Hoje estão no balde: CRLV-e (importar, vincular e importar na edição, nos dois portais), store/update
de OS (proprietário, lojista, oficina, API), capas e edição de veículo, consignação (procuração em
PDF), cadastro e edição da oficina (logo), `POST /api/v1/me/avatar`,
`POST /api/v1/vehicles/{id}/cover`, `POST /api/v1/maintenances/{maintenance}/photos` e
`POST /api/v1/invoices/upload`. Rota nova que receba arquivo entra junto. Telas GET de conferência
ficam fora de propósito (`.ai/rules/vehicles-entry.md`).

## Ability por método de um resource vai em `->middlewareFor()`

`Route::apiResource(...)->middleware(['index' => 'ability:a', 'store' => 'ability:b'])` **ignora as
chaves**: `PendingResourceRegistration::middleware()` só faz `Arr::wrap` e aplica todas as entradas
em todas as rotas do recurso. Era assim nos dois `apiResource` de oficina
(`workshops.message-templates` e `workshops.warranty-templates`), que acabavam exigindo
`workshops:read` *e* `workshops:write` em cada rota — nenhum teste de comportamento pegava, porque o
token do app traz as duas (`App\Support\SanctumMobileToken::ABILITIES`). O certo é
`->middlewareFor(['index', 'show'], 'ability:workshops:read')` por grupo de métodos.
`ApiResourceAbilitiesTest` lê as rotas registradas e trava a ability de cada uma, além de cobrir
token com uma ability só; recurso novo com ability por método entra no provider dele.

## Rota que busca veículo por identificador é limitada, e tela logada tem balde pela conta

Toda tela que acha veículo a partir de placa, chassi ou RENAVAM é oráculo de enumeração e leva
`throttle`, em qualquer portal — não só as públicas. O balde `search` (20/min **por IP**,
`App\Providers\AppServiceProvider`) é o das buscas abertas a visitante: `/verificar`, `/v/{code}`,
`/convite/{token}` e a consignação.

Tela de conta logada não divide esse balde: numa chave por IP a cota é gasta por estranhos, e
`GET /oficina/manutencoes/nova` levava 429 na tela que registra serviço por causa de tráfego anônimo
do mesmo NAT (ou de um colega de oficina atrás do mesmo IP). Ela tem balde próprio pela conta,
`workshop-vehicle-search`, como a consulta por chassi da API já tinha o `workshop-chassis-lookup`:
20/min por `user()?->id ?: ip()`. O teto continua valendo contra sondagem — conta de oficina é
exatamente o que o visitante não tem — e um funcionário não cala o outro.

`workshop-vehicle-search` devolve `Limit::none()` sem `license_plate` nem `chassis` no pedido: abrir
o formulário não acha veículo nenhum, e cobrar ficha por isso fazia F5, "Trocar veículo" e o retorno
de um erro de validação gastarem cota de consulta.

`/buscar-veiculo` (portal do proprietário e do lojista) segue no `search` embora peça login: é a
mesma dívida, não paga aqui. O limite não basta sozinho: o que cada perfil pode ver do veículo
achado está em `.ai/rules/public-lookup.md`.

## Rota que manda e-mail para o endereço digitado tem balde próprio

Além do `throttle` por IP, essa rota precisa de um teto por destinatário, e nunca divide o balde com
outra rota. Hoje: `POST /excluir-conta` com `throttle:account-deletion`. O porquê, os limites e o
cuidado para o 429 não revelar quem tem cadastro estão em `.ai/rules/abuse-guards.md`.

## `invoices` tem teto de quantidade

O array `invoices` vale no máximo `App\Rules\InvoiceFile::MAX_FILES_PER_REQUEST` por requisição, com
o mesmo número em toda superfície que o aceita (web do proprietário, do lojista e da oficina, e
`StoreMaintenanceRequest`/`UpdateMaintenanceRequest` da API). A mensagem é única, em
`lang/pt_BR/validation.php` (`custom.invoices.max`). Sem o teto, uma requisição com dezenas de
arquivos força `InvoiceFile` a ler todos antes de qualquer resposta, e o throttle só limita o número
de requisições, não o trabalho de cada uma.
