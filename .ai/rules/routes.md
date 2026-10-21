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

## `invoices` tem teto de quantidade

O array `invoices` vale no máximo `App\Rules\InvoiceFile::MAX_FILES_PER_REQUEST` por requisição, com
o mesmo número em toda superfície que o aceita (web do proprietário, do lojista e da oficina, e
`StoreMaintenanceRequest`/`UpdateMaintenanceRequest` da API). A mensagem é única, em
`lang/pt_BR/validation.php` (`custom.invoices.max`). Sem o teto, uma requisição com dezenas de
arquivos força `InvoiceFile` a ler todos antes de qualquer resposta, e o throttle só limita o número
de requisições, não o trabalho de cada uma.
