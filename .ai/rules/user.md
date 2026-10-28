---
paths:
  - 'app/Services/User/**'
  - 'app/Models/User.php'
  - 'app/Http/Requests/Web/Account/UpdateAccountPreferencesRequest.php'
---

# User

## Account deletion anonymizes; VIN history stays
Never User::delete() for in-app account deletion. maintenances.user_id cascades and would wipe VIN history registered by that user, including records other owners still need. Anonymize PII, revoke tokens/FCM, detach only that user's vehicle pivots, and leave vehicles plus all maintenances on the chassis untouched.

## A PII do dono também mora fora de `users`
`vehicle_consignments` guarda cópia de `owner_name`, `owner_email`, `owner_phone` e `owner_document`
(a loja declara o contato ao registrar a consignação) e o tenant individual nasce com o nome da
pessoa (`TenantService::createForUser`). `DeleteUserAccount` limpa as duas coisas, casando a
consignação por `owner_user_id` **ou** pelo e-mail original — a loja pode ter declarado antes de o
dono criar conta. Ao acrescentar qualquer tabela que copie contato do usuário, limpe-a ali também:
apagar só a linha de `users` deixa a cópia intacta e a exclusão passa a prometer mais do que cumpre.

A consignação **não** é encerrada: o carro segue no pátio da loja, que continua registrando serviço
(é o histórico que valoriza o veículo). A loja recebe `ConsignmentOwnerAccountDeletedNotification`,
que fala só do veículo — nunca repete o contato recém-apagado.

## A exclusão exige reautenticação em todos os caminhos
`DELETE /api/v1/me` pede `current_password` (`App\Http\Requests\Api\V1\DeleteAccountRequest`), como
a tela `/conta` já pedia: a exclusão é irreversível e um token roubado não pode bastar. A senha é
conferida com `Hash::check` dentro do request, e não pela regra `current_password`, porque só o
guard `web` existe em `config/auth.php` — mesmo motivo do `UpdatePasswordRequest`.

Conta que entra só por Google/Apple nunca escolheu senha (o cadastro social grava uma aleatória em
`AuthController::resolveSocialUser`) e por isso **não tem caminho pela API**: o caminho dela é o
link assinado de `/excluir-conta`, de 1 h, que é o mesmo fluxo exigido pelo Google Play. O erro 422
diz isso e traz a URL. Não tente adivinhar "conta social" por `provider`: quem se cadastrou por
e-mail e vinculou um provedor depois tem `provider` preenchido e sabe a senha.

Exigir senha **quebra o app Flutter** enquanto ele mandar o DELETE sem corpo. A ordem de implantação
é app primeiro, backend depois, como em "Endereço é da empresa, não da conta" (`.ai/rules/api.md`).

## A sessão web morre com a conta
`DeleteUserAccount::handle()` apaga as linhas de `sessions` da pessoa (driver `database`), o que
tira os outros aparelhos na hora em vez de esperar a próxima requisição deles. Isso não basta para
quem está excluindo pela própria sessão: o framework regrava a linha ao fim da resposta e o
`App\Http\Middleware\AuthenticateWebSession` guarda o hash da senha nova logo depois. Por isso os
três pontos de entrada (`/conta`, o link assinado de `/excluir-conta` e `DELETE /api/v1/me`, que o
portal chama com sessão) usam `App\Http\Controllers\Concerns\EndsDeletedAccountSession`. Ao criar um
quarto caminho de exclusão, use a trait: sem ela o navegador segue autenticado sobre a linha
anonimizada.

## O que mais sai junto com a conta
Além de `users`, tokens, FCM, pivôs de veículo, contato de consignação e nome do tenant, a mesma
transação apaga:
- `notifications` da pessoa (canal `database`): o texto cita nome, veículo e placa;
- `vehicle_pdf_exports` dela **e os arquivos**. O `vehicle-pdf-exports:cleanup` só passa quando
  `expires_at` vence, até 24 h depois — tarde demais para uma exclusão de conta.

Ao acrescentar qualquer tabela que copie dado pessoal ou gere arquivo do usuário, apague-a aqui
também.

## Notas fiscais e fotos de manutenção ficam — é decisão, não esquecimento
A exclusão **não** apaga notas fiscais nem fotos das manutenções, mesmo quando foi a própria pessoa
que as anexou. Elas são prova do serviço feito no chassi, não cadastro de quem o declarou: apagá-las
esvaziaria o histórico que o próximo dono compra junto com o carro e que o selo de oficina atesta —
exatamente o que `.ai/rules/user.md` já protege em "Account deletion anonymizes; VIN history stays".
Quem aparece nesses arquivos não é mais identificável pela plataforma: o registro continua ligado ao
veículo, sem nome, e-mail, telefone ou documento.

Isso está escrito na política de privacidade (`config/legal.php`, seção 7 "Conservação",
`privacy_version` 2026-10-27), junto com a saída para quem discordar: pedido individual em
`suporte@revisalog.com.br` (LGPD, art. 18), analisado caso a caso. O que já tinha prazo próprio
continua com ele: anexo pendente de OS sem proprietário some em 90 dias ou na recusa
(`.ai/rules/policies.md`).

## Preferência do PDF mora em users.export_include_photos
Coluna booleana nullable (null = nunca perguntado), no cast `boolean` e em `$fillable`. Quem lê é o VehiclePdfMaintenancePhotos, sobre o proprietário atual do veículo, e não sobre quem pediu o PDF. Não é PII: a exclusão de conta não precisa limpá-la.

## Preferência do PDF só para quem tem as fotos ligadas
O cartão "PDF do histórico" em /conta e o PUT /conta/preferencias exigem as fotos das manutenções ligadas na conta (UpdateAccountPreferencesRequest::authorize; MaintenancePhotoAccess, features.md). Sem o recurso o cartão some e o PUT responde 403.
