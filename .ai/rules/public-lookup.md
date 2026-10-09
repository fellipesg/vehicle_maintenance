---
paths:
  - 'app/Http/Controllers/Web/Public*'
  - 'resources/views/public/**'
  - 'app/Http/Resources/Api/V1/**'
  - 'app/Support/Vehicle/VehicleIdentifierVisibility.php'
  - 'app/Support/Vehicle/VehicleIdentifierMask.php'
---

# Public Lookup

## Vehicle search and seal lookup protect the owner
Vehicle search and /v/: chassis and RENAVAM go through App\Support\Vehicle\VehicleIdentifierMask for anyone who does not pass Gate update on the vehicle. That includes the public API search (GET /api/v1/vehicles/search/{identifier}, no auth middleware): PublicVehicleSearchResource reads the viewer from the web session or the sanctum guard ($request->user('sanctum')) and only the current owner gets the full numbers. Every API VehicleResource does the same (vehicle detail, maintenance.vehicle, admin list), through App\Support\Vehicle\VehicleIdentifierVisibility: the chassis and renavam keys stay, only the value changes, and identifiers_masked (bool) tells the app which one it got. Lists loaded through currentVehicles() are decided from the loaded pivot, without a query per vehicle; UserResource passes the account itself as viewer (login has no request user yet). /buscar-veiculo, /verificar and /v/{code} use throttle:search. The seal code typed or scanned is normalized by App\Support\Maintenance\VerificationCode. /verificar is the only lookup open to visitors: it is in the sitemap and in the footer ("Conferir selo da oficina"); /v/{code} and the invalid-code page carry noindex.

## Every surface for a non-owner masks, short chassis included
VehicleIdentifierMask::chassis keeps 3 + 4 characters only on a 17-character VIN; a shorter (pre-1990) chassis shows at most 40% of it, at the end ("BA1234567" -> "••••••567"), so the public search (exact chassis match) is not a guessing oracle. The same Gate update rule masks the web vehicle pages (garage.vehicles.show and user.vehicles.show pass identifiersMasked to x-vehicle.detail) and the history PDF (the export and e-mail jobs decide with VehicleIdentifierVisibility::showsFullIdentifiers for the requester).

## Consulta pública de OS sem proprietário
Busca pública, /v/{código} e PDF só mostram o mínimo (data, km, tipo, itens, oficina, selo) de OS pending/declined, nunca anexos nem descrição, e nada do que o proprietário ocultou (/v/ responde 404). A consulta por chassi da oficina (GET /api/v1/workshop/vehicles/lookup, throttle workshop-chassis-lookup) devolve só {id, has_owner:true} quando o carro tem dono.
