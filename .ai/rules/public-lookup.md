---
paths:
  - 'app/Http/Controllers/Web/Public*'
  - 'resources/views/public/**'
  - 'app/Http/Resources/Api/V1/**'
  - 'app/Support/Vehicle/VehicleIdentifierVisibility.php'
  - 'app/Support/Vehicle/VehicleIdentifierMask.php'
  - 'app/Support/Workshop/WorkshopVehicleVisibility.php'
  - 'app/Http/Controllers/Web/Workshop/MaintenanceController.php'
  - 'resources/views/workshop/maintenances/**'
---

# Public Lookup

## Vehicle search and seal lookup protect the owner
Vehicle search and /v/: chassis and RENAVAM go through App\Support\Vehicle\VehicleIdentifierMask for anyone who does not pass Gate update on the vehicle. That includes the public API search (GET /api/v1/vehicles/search/{identifier}, no auth middleware): PublicVehicleSearchResource reads the viewer from the web session or the sanctum guard ($request->user('sanctum')) and only the current owner gets the full numbers. Every API VehicleResource does the same (vehicle detail, maintenance.vehicle, admin list), through App\Support\Vehicle\VehicleIdentifierVisibility: the chassis and renavam keys stay, only the value changes, and identifiers_masked (bool) tells the app which one it got. Lists loaded through currentVehicles() are decided from the loaded pivot, without a query per vehicle; UserResource passes the account itself as viewer (login has no request user yet). /buscar-veiculo, /verificar and /v/{code} use throttle:search. The seal code typed or scanned is normalized by App\Support\Maintenance\VerificationCode. /verificar is the only lookup open to visitors: it is in the sitemap and in the footer ("Conferir selo da oficina"); /v/{code} and the invalid-code page carry noindex.

## Every surface for a non-owner masks, short chassis included
VehicleIdentifierMask::chassis keeps 3 + 4 characters only on a 17-character VIN; a shorter (pre-1990) chassis shows at most 40% of it, at the end ("BA1234567" -> "••••••567"), so the public search (exact chassis match) is not a guessing oracle. The same Gate update rule masks the web vehicle pages (garage.vehicles.show and user.vehicles.show pass identifiersMasked to x-vehicle.detail) and the history PDF (the export and e-mail jobs decide with VehicleIdentifierVisibility::showsFullIdentifiers for the requester).

## A busca de placa da oficina segue o mascaramento público
`GET /oficina/manutencoes/nova` (etapa 1 da OS) busca veículo por placa e por chassi para qualquer
conta de oficina, que nasce por autocadastro: a rota leva `throttle:search`, o mesmo balde de
`/buscar-veiculo`, `/verificar` e `/v/{code}`. Sem vínculo com o veículo — a oficina não registrou
(`workshop_id`) nem selou (`verified_workshop_id`) nenhuma OS dele —
`App\Support\Workshop\WorkshopVehicleVisibility::showsFullVehicle` devolve false e a tela mostra só
o que a consulta pública mostra: marca, modelo, ano, cor e capa. Fora ficam **a placa atual** (o card
devolve a placa que foi digitada, mesmo quando ela é uma placa antiga, e o `input hidden` do store
leva essa mesma; `VehiclePlateSearch::findByPlate` resolve o veículo pela placa antiga) e **o
hodômetro do veículo** (`current_kilometers`), que soma registros ocultos e pendentes que a consulta
pública não lista — ele sai do card, do valor inicial do campo "Quilometragem" e da dica dele. O
fluxo legítimo continua: a OS para cliente novo abre, e a oficina digita o hodômetro que leu no
painel, como no portal do lojista (`.ai/rules/garage.md`). **Veículo sem dono vinculado é mascarado
igual** nessa busca: ele continua sendo o carro de alguém, e essa população cresce justamente porque
oficinas registram OS de carro não reivindicado. O que libera o card sem vínculo é a *posse* — a
consulta exata de 17 caracteres (`?chassis=`), o fluxo em que a oficina está com o carro e cadastra
o veículo —, nunca a falta de conta do proprietário; chassi de veículo com dono não mostra nada do
carro, e chassi curto nem resolve veículo. `$showsFullVehicle` é sempre explícito nos includes
(`_vehicle-step.blade.php`, `partials/workshop-maintenance-form.blade.php`): quem esquecer recebe o
card reduzido. Note que o POST ainda revela o piso do hodômetro na mensagem de erro de
`VehicleMileageService::assertMaintenanceKilometers`, com o custo de `throttle:uploads` e de uma
tentativa de OS.

## Consulta pública de OS sem proprietário
Busca pública, /v/{código} e PDF só mostram o mínimo (data, km, tipo, itens, oficina, selo) de OS pending/declined, nunca anexos nem descrição, e nada do que o proprietário ocultou (/v/ responde 404). A consulta por chassi da oficina (GET /api/v1/workshop/vehicles/lookup, throttle workshop-chassis-lookup) devolve só {id, has_owner:true} quando o carro tem dono.
