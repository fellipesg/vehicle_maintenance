---
paths:
  - 'routes/*.php'
---

# Routes

O glob cobre `routes/*.php` inteiro, não só `web.php` e `api.php`: `routes/api-two-factor.php`
também declara rotas e não casava com nenhuma linha do índice.

## Rota de portal ou de API que recebe arquivo entra no balde `uploads`

Toda rota de portal (proprietário, lojista, oficina) ou da API que aceita upload leva
`throttle:uploads` (10/min por conta ou IP, `App\Providers\AppServiceProvider`) — não só as rotas
cujo nome fala de upload. Nas OS isso vale para o store nas quatro superfícies (proprietário,
lojista, oficina e API) e para o update nas três que têm update (proprietário, oficina e API; o
portal do lojista não tem rota de update de OS), porque o formulário inteiro vem com `invoices`:
`App\Rules\InvoiceFile` lê o arquivo (MIME por conteúdo, cabeçalho do PDF e uma passada de
`XMLReader`), então uma requisição sem limite paga parsing de verdade. Na API as rotas de manutenção
ficam com os dois baldes: `throttle:api` do grupo e `throttle:uploads` por cima.

Hoje estão no balde: CRLV-e (importar e vincular nos dois portais do dono do veículo, mais importar
na edição, que só existe no do proprietário), store de OS (proprietário, lojista, oficina, API),
update de OS (proprietário, oficina, API), capas e edição de veículo (nos dois portais), consignação
(procuração em PDF, nos dois portais), cadastro e edição da oficina (logo),
`POST /api/v1/me/avatar`, `POST /api/v1/vehicles/{id}/cover`,
`POST /api/v1/maintenances/{maintenance}/photos` e `POST /api/v1/invoices/upload`. A lista de
verdade é o código, não esta frase: `grep -n 'throttle:uploads' routes/*.php` (ou
`php artisan route:list --except-vendor -v`, procurando `ThrottleRequests:uploads`). Rota nova de
portal ou de API que receba arquivo entra junto. Telas GET de conferência ficam fora de propósito
(`.ai/rules/vehicles-entry.md`).

### O grupo `admin` é exceção consciente

As rotas do grupo `admin` que recebem arquivo ficam **fora** do balde, de propósito:
`admin.outreach.import` (CSV de até 5 MB) e `admin.blog.store` / `admin.blog.update` (imagem de capa
de até 4 MB). O balde existe contra a conta comum que repete upload; atrás do middleware `admin` só
passa um punhado de contas nossas, e a importação de prospecção é feita em lote por uma pessoa, que
10/min atrapalharia. Se um dia o /admin abrir para fora dessas contas, as três voltam para o balde.

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

Tela que exige login não divide esse balde: numa chave por IP a cota de quem tem conta é gasta por
estranhos, e `GET /oficina/manutencoes/nova` levava 429 na tela que registra serviço por causa de
tráfego anônimo do mesmo NAT (ou de um colega de oficina atrás do mesmo IP). Essas vão para
`account-vehicle-search`, 20/min por `user()?->id ?: ip()`: hoje `GET /oficina/manutencoes/nova`
(`?license_plate=`, `?chassis=`) e `/buscar-veiculo` (`?identifier=`), o portal do proprietário e do
lojista. Um balde só para as duas de propósito — é a mesma política, e enumerar por duas telas não dá
duas cotas. A consulta por chassi da API tem o `workshop-chassis-lookup`, com a mesma forma de chave.
O teto continua valendo contra sondagem — conta é exatamente o que o visitante não tem — e uma conta
não cala a outra.

`account-vehicle-search` devolve `Limit::none()` quando o pedido não traz `identifier`,
`license_plate` nem `chassis`: formulário abrindo vazio não acha veículo nenhum, e cobrar ficha por
isso fazia F5, "Trocar veículo" e o retorno de um erro de validação gastarem cota de consulta.

O limite não basta sozinho: o que cada perfil pode ver do veículo achado está em
`.ai/rules/public-lookup.md`.

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
