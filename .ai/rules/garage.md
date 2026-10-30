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

## O portão da oficina é o mesmo da loja, e por um motivo mais forte
`EnsureWorkshopApproved` ('workshop.approved' na web, 'workshop.approved:api' na API) espelha o portão
do lojista: oficina em análise só abre `workshop.profile.*` na web e só lê na API. O motivo é o do
lojista elevado — a oficina escreve no histórico permanente de um chassi **com o Selo da oficina**,
que é o que o comprador confere em /v/{código}, move o hodômetro do veículo e manda convite por
e-mail e WhatsApp para terceiros; e um selo emitido não volta atrás. Estado em `workshops.status`
(`App\Enums\WorkshopStatus`, + reviewed_by/reviewed_at/review_notes), decidido por
`App\Services\Workshop\WorkshopReview` na aba "Em análise" de /admin/oficinas, com
`WorkshopApprovalDecidedNotification` para a conta (não confundir com
`WorkshopReviewDecidedNotification`, que é a resposta da oficina sobre uma declarada).

`status` **não** é fillable e a migration marca 'approved' tudo o que já existia; a WorkshopFactory
nasce aprovada (`pending()`/`rejected()` para a fila). Diferenças deliberadas em relação ao lojista:
conta de oficina **sem** linha em `workshops` passa pelo portão (não há o que conferir, e sem ela não
se registra OS nem sai selo), /admin/oficinas abre em "Todas" porque a página também é o diretório, e
não existe `resetForDocumentChange` porque "Minha oficina" não edita o CNPJ — há teste guardando isso.

## O portão da loja vale na web e na API
`EnsureGarageApproved` protege os dois clientes: na web a loja em análise só abre `garage.profile.*`
(lista de liberação, então rota nova nasce fechada) e na API, com `garage.approved:api` no grupo
`auth:sanctum` de `routes/api.php`, ela só lê — qualquer método que não seja GET/HEAD/OPTIONS volta
403 com a mensagem em inglês do envelope. O modo é parâmetro explícito, não `expectsJson()`: cliente
sem `Accept: application/json` receberia o redirect da web. Só na web o portão não bastava — o app
fala pela API e era caminho livre para criar veículo, escrever no histórico de um chassi e mandar
convite para terceiros (`/maintenances/{id}/invites`).
