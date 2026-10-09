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
