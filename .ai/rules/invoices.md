---
paths:
  - 'app/Services/Invoice/**'
  - app/Http/Controllers/Web/Concerns/StoresMaintenanceInvoices.php
  - app/Http/Controllers/Api/InvoiceController.php
  - app/Rules/InvoiceFile.php
---

# Invoices

## Os inserts das notas ficam fora de transação, de propósito
Gravar arquivo e linha dentro de um `DB::transaction` foi tentado e desfeito (`eaf94f0`): no pooler do
Neon (PgBouncer) um erro de query engolido vira `SQLSTATE 25P02`, aborta o bloco inteiro e esconde a
falha real — e a transação ainda ficaria aberta durante o I/O do S3/R2. Então `storeUploads` grava os
arquivos **antes**, e as linhas entram uma a uma, sem transação em volta. Não volte a cercar esse
trecho com transação sem reler essa decisão e o aviso de booleanos do README (Postgres/Neon).

A consequência é que o que entrou está commitado. `createInvoiceRecordsOrDiscard` apaga só os arquivos
que ficaram sem linha (`array_slice` do índice que falhou) e nunca os das notas já gravadas: apagar o
arquivo de uma nota existente deixaria a linha apontando para o vazio, e a rota de download só poderia
responder 404 — pior que o objeto órfão que a limpeza evita.

## Falha parcial de nota não derruba a OS salva
`createInvoiceRecordsOrDiscard` **não relança**: reporta a exceção e devolve em `failed` o nome do que
não entrou. A primeira falha vale pelas seguintes (o insert quebrou por algo da conexão ou da tabela),
então o resto é descartado junto. Quando a segunda nota de uma OS falhava e a exceção subia, o usuário
recebia 500 sobre uma OS que já existia — com os itens, o selo e a quilometragem do veículo aplicados —
e o reenvio do formulário criava a OS duplicada.

As três superfícies web (oficina, proprietário e lojista, `store` e `update`) levam esses nomes em
`invoice_failures` até `redirectWithInvoiceFeedback`, que mantém o `success` normal e acrescenta o flash
`error` de `InvoiceParseFeedback::notAttachedMessage` (`role="alert"` no `x-ui.flash`). A mensagem nomeia
os arquivos e diz para não cadastrar a OS de novo, porque é esse reenvio que duplica. Não troque isso por
uma exceção nem por redirect de volta ao formulário de criação.

Reverter em vez de avisar não é alternativa: a OS está commitada junto com o selo (que consome um código
de verificação), a quilometragem do veículo e, no portal da oficina, o veículo recém-cadastrado. A
compensação rodaria contra o mesmo banco que acabou de falhar.

## Gravação recusada pelo storage nunca sai em silêncio
As disks rodam com `throw => false` (`config/filesystems.php`), então uma falha no S3/R2 volta `false` de
`storeAs` e `storeFile` devolve `null` — sem exceção e sem log. Por isso `storeUploads` devolve
`{stored, failed}`, chama `report()` e o nome do arquivo entra na mesma mensagem do usuário. Não volte a
tratar esse `null` com `continue`: a OS nascia sem a nota e nada aparecia na tela nem no log.

## No store da API é transação; no update, não
`POST /api/v1/maintenances` grava tudo dentro de `DB::beginTransaction`, então usa
`createInvoiceRecords` (que relança) e o rollback tira todas as linhas — não existe OS pela metade ali, e
o `catch` descarta todos os arquivos. Um arquivo recusado pelo storage falha a requisição inteira **antes**
de criar qualquer coisa: antes virava 201 com a OS sem a nota. O `catch (\Exception)` ganhou um
`catch (\Throwable)` ao lado porque um `Error` escapava sem rollback nem limpeza.

`PUT /api/v1/maintenances/{id}` **anexa**, e lá não há transação nenhuma. O campo era validado (teto de
10, `InvoiceFile` lendo cada arquivo) e caído no `unset` das relações: 200 com o parsing pago e nada
anexado, e com oficina da rede escolhida a `RequiresInvoiceWhenWorkshopAssigned` ainda exigia a nota que
seria descartada. A ordem é a do portal web, com uma diferença: `storeUploads` roda **antes** de gravar a
OS, então o arquivo que o storage recusar devolve 500 com a edição ainda intacta (como no store, e
diferente do web, que grava a OS primeiro). Depois vêm os itens, e só então
`createInvoiceRecordsOrDiscard` e o parsing. Um `\Throwable` entre o update e as linhas descarta todos os
arquivos, porque nenhuma nota existe ainda; da criação das linhas em diante a limpeza é só do que ficou
sem linha. A OS editada nunca volta atrás: a nota que não entrou sai em `invoice_failures` (nomes dos
arquivos) e a `message` passa a dizer que algo não foi anexado, que é o equivalente do flash `error` da
web. Não troque isso por 500 nem por rollback.

Mensagens da API seguem em inglês (ver `.ai/rules/locale.md`); as da web, em pt-BR.

## Onde o resto da nota fiscal está registrado
Caminho aleatório no storage e nome original só nas colunas: "Arquivos enviados nunca usam o nome
original no caminho", em `.ai/rules/models.md`. Link de download sempre pela rota autorizada, nunca por
`AppStorage::url`: "Nota fiscal nunca pelo caminho do storage", em `.ai/rules/views.md`. A regra
`InvoiceFile` vem depois de `max:10240` nas sete superfícies (`['bail', 'file', 'max:10240', new InvoiceFile]`)
e percorre o XML com `XMLReader` em fluxo — a ordem é o que impede ler 20 MB para depois devolver 422.
