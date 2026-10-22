---
paths:
  - 'app/Services/User/**'
  - 'app/Models/User.php'
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

## Preferência do PDF mora em users.export_include_photos
Coluna booleana nullable (null = nunca perguntado), no cast `boolean` e em `$fillable`. Quem lê é o VehiclePdfMaintenancePhotos, sobre o proprietário atual do veículo, e não sobre quem pediu o PDF. Não é PII: a exclusão de conta não precisa limpá-la.
