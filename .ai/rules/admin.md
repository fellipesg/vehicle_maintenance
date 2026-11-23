---
paths:
  - 'app/Http/Controllers/Web/Admin/**'
---

# Admin

## Admin web fleet scope
Admin web list endpoints (vehicles, maintenances, workshops) must not scope by tenant or current owner; they show platform-wide data. Maps expose only id, name, lat, lng, city, and a short label in JSON—no email, document, or phone in browser payloads.

## One error bag per form on pages with dialogs
When a page has more than one form for the same fields (Nova marca/Editar marca, categories, models), validate with $request->validateWithBag(self::CREATE_BAG | updateBag($model), ...) so the error and old input come back only in the dialog that was sent (see views-admin.md).

## Recursos por conta: interruptores no admin
Liga/desliga de recurso por conta (hoje, as fotos das manutenções) é `MaintenancePhotosFeatureController`: um PUT por escopo (usuário, oficina, loja) com `UpdateMaintenancePhotosFeatureRequest`, log de quem mudou e `back()` com aviso. Valores lidos por MaintenancePhotoAccess::isActiveFor / preload (nada de uma consulta por linha); ver features.md.

## OS contestada tem filtro, pendência e é aqui que se esconde
`admin.maintenances.index?contestadas=1` filtra o que o proprietário contestou
(`owner_disputed_at`, ver models.md) e aparece como chip removível junto dos outros filtros de
contexto. O caminho até lá é o cartão de Pendências da visão geral ("OS contestadas").

A contestação **não** tira a OS da consulta pública — ela só marca. Quem tira é a equipe, e só por
aqui: `POST admin/manutencoes/{maintenance}/ocultar` (motivo opcional) e `.../publicar`, via
`App\Services\Maintenance\MaintenancePublicVisibilityService`, que grava `hidden_by_user_id` e
`hidden_reason` e **recalcula o hodômetro**: ocultar tira o registro de circulação com número e tudo
(ver models.md, "Hodômetro"), senão o km de uma OS que ninguém mais vê seguiria público e seguiria
recusando OS nova abaixo dele. Suprimir um registro é tirar de circulação o ativo do produto: tem que ter nome e
motivo. Use para fraude confirmada e erro de chassi, não para acalmar um proprietário incomodado — é
exatamente por isso que a alavanca saiu da mão dele.

## Laudo contestado tem filtro, pendência e é aqui que se suprime
`admin.inspections.index` (`/admin/vistorias`, menu Frota) é a lista de vistorias da plataforma
inteira, com `?contestadas=1`, `?suprimidas=1` e busca por código, placa, chassi ou emissor. O
caminho até ela é o cartão de Pendências da visão geral ("laudos contestados"), como nas OS.

É a **única** tela que mostra o laudo suprimido: a consulta é direta, sem
`Vehicle::inspectionsVisibleTo` e sem `InspectionRedactor`, porque quem suprimiu precisa continuar
vendo o que tirou para poder devolver. A ficha do veículo, o PDF e `/laudo/{código}` seguem sem ele.

Contestar é do proprietário e só marca; suprimir é daqui, por
`App\Services\Inspection\InspectionPublicVisibilityService`, que grava `hidden_by_user_id` e
`hidden_reason` (ver .ai/rules/inspections.md). Diferente da OS, **não há hodômetro para
recalcular**: a leitura da vistoria nunca alimentou `vehicles.current_kilometers`. Use para fraude
confirmada e chassi trocado, não para acalmar um proprietário incomodado — é por isso que a alavanca
não é dele.

## Vínculo de propriedade errado: é a equipe que encerra, pela ficha do veículo
`POST admin/veiculos/{vehicle}/proprietarios/{user}/encerrar` (`admin.vehicles.end-ownership`, botão
"Encerrar vínculo" em `admin.vehicles.show`, com `data-confirm` e motivo **obrigatório**) passa por
`App\Services\Vehicle\VehicleOwnershipEndingService::endByTeam`. O vínculo **não** é apagado:
`is_current_owner` vira falso, `sale_date` recebe o dia e ficam gravados
`ownership_ended_by_user_id` e `ownership_ended_reason` em `user_vehicles`, mais um `Log::info`. O
ex-dono recebe `VehicleOwnershipEndedByTeamNotification` (só e-mail, sem o motivo); as manutenções
continuam no chassi. Sem outro dono atual, o veículo volta a aceitar o vínculo por placa e RENAVAM
(`VehiclePolicy::link`). Use quando o vínculo foi feito na conta errada ou quando o comprador não tem
como enviar o CRLV-e. Quem tem o CRLV-e no nome não precisa da equipe: `claimExisting` já tira o
dono anterior.
