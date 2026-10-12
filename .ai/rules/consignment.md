---
paths:
  - 'app/Models/VehicleConsignment.php,app/Services/Vehicle/VehicleConsignmentService.php,app/Http/Controllers/Web/Concerns/HandlesVehicleConsignment.php,app/Http/Controllers/Web/ConsignmentOwnerController.php,app/Http/Controllers/Web/Admin/ConsignmentController.php,resources/views/vehicles/entry/power-of-attorney.blade.php,resources/views/garage/vehicles/_consignment-status.blade.php'
---

# Consignação

## Declaração libera escrita; histórico anterior é do proprietário
A loja declara a consignação no passo do assistente (contato do proprietário + aceite de autorização,
com IP e data gravados) e **já registra manutenções**: ela informa um serviço que ela mesma pagou e o
proprietário é avisado de cada um. O histórico que o veículo já tinha continua fechado até o
proprietário liberar (um clique no link do e-mail, `consignments.owner.*`, sem login — o dono de carro
consignado quase nunca tem conta) ou a equipe aprovar a procuração, que é anexo **opcional**. Contestar
congela a loja na hora (`owner_disputed_at`), sem encerrar a consignação.

Estado em `vehicle_consignments`; `vehicle_access_grants` é legado e não é mais lido. Não reintroduza
procuração obrigatória: foi exatamente o que impedia a loja de trabalhar.

## Consignação é só de lojista
`VehicleConsignmentService::start` recusa qualquer conta que não seja garagem, e `VehiclePolicy`
devolve null para não-lojista. Conta de proprietário com consignação não é cenário válido.

## Restringir histórico pré-carrega a relação, não filtra em maintenances()
`Vehicle::restrictHistoryTo` grava a relação `maintenances` já limitada, e `VehicleTimelineBuilder`
usa `loadMissing` (nunca `load`) para não desfazer isso. Filtrar dentro de `maintenances()` **não
funciona**: o eager loading do Eloquent resolve a relação num `newInstance()` do model e perde
qualquer estado guardado na instância — o vazamento volta silencioso pelo builder e por
`<x-vehicle.detail>`.

## Contato de proprietário com conta nunca vai para o navegador
Quando o veículo já é de uma conta, o formulário da consignação não pede nem envia e-mail/telefone: a
tela mostra só o mascarado (`App\Support\ContactMask`) e o servidor resolve pelo `owner_user_id`.

## CRLV-e obrigatório no Lojista
`VehicleEntryFlow::allowsManualEntry()` é falso no Lojista, com trava equivalente em
`RegistersVehicleWithOwnership::registerVehicle`. Sem o documento a loja se cadastraria como dona de
um carro de terceiro, e é o CPF/CNPJ do CRLV-e que separa estoque próprio de consignação.

## Propriedade não confirmada aparece, não some
Cadastro manual (só no Proprietário) deixa `ownership_verified_at` nulo: ficha, consulta de procedência
e PDF mostram "Propriedade não confirmada" com convite para enviar o CRLV-e. O veículo **não** sai da
consulta nem do PDF — esconder apagaria junto as manutenções com Selo da oficina, que são confirmadas
por quem prestou o serviço e não dependem de quem é o dono.

## Anexo de OS sem proprietário exige propriedade verificada
Aceitar notas e fotos de uma OS de oficina sem dono exige ownership_verified_at (CRLV-e); cadastro manual não basta (422). Vale também para conta de proprietário com propriedade "não confirmada": ela pode vincular o registro mínimo, recusar e ocultar.
