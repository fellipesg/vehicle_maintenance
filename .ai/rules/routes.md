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
