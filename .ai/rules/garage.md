---
paths:
  - 'resources/views/garage/**'
  - 'app/Http/Controllers/Web/Garage/**'
---

# Garage

## Stock screens authorize with canViewStockVehicleHistory
Garage vehicle and maintenance screens authorize with User::canViewStockVehicleHistory (stock of this dealer: current owner, or consignment with an approved power of attorney). Editing and registering a maintenance need the current owner (VehiclePolicy::update / addMaintenance); consignment is read-only. The stock list goes through App\Support\Vehicle\DealerStock (search, filters, order and view in the query string).

## Maintenance form returns to the vehicle
Coming from the vehicle page (?vehicle_id=), the maintenance form sends return_to=vehicle and saving redirects to the vehicle page with #manutencao-{id}; initVehicleDetails (resources/js/vehicle-timeline-portal.js) opens the tab that holds it and scrolls to it. Kilometers are never prefilled with today's odometer nor get min: the form shows the accepted range for the date (data-mileage from App\Support\Vehicle\MaintenanceMileageContext, resources/js/garage-maintenance-form.js), and VehicleMileageService validates on the server.

## Lojista não decide registros de oficinas
A tela e o aviso de registros de oficinas são do Proprietário (/usuario/registros-de-oficinas). WorkshopRecordsArrivalNotifier ignora conta de lojista.

## O portão da loja vale na web e na API
`EnsureGarageApproved` protege os dois clientes: na web a loja em análise só abre `garage.profile.*`
(lista de liberação, então rota nova nasce fechada) e na API, com `garage.approved:api` no grupo
`auth:sanctum` de `routes/api.php`, ela só lê — qualquer método que não seja GET/HEAD/OPTIONS volta
403 com a mensagem em inglês do envelope. O modo é parâmetro explícito, não `expectsJson()`: cliente
sem `Accept: application/json` receberia o redirect da web. Só na web o portão não bastava — o app
fala pela API e era caminho livre para criar veículo, escrever no histórico de um chassi e mandar
convite para terceiros (`/maintenances/{id}/invites`).
