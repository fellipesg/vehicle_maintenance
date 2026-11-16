---
paths:
  - 'resources/views/garage/**'
  - 'app/Http/Controllers/Web/Garage/**'
  - 'app/Support/Vehicle/DealerStock.php'
---

# Garage

## Stock screens authorize with canViewStockVehicleHistory
Garage vehicle and maintenance screens authorize with User::canViewStockVehicleHistory (stock of this dealer: current owner, or consignment with an approved power of attorney). Editing and registering a maintenance need the current owner (VehiclePolicy::update / addMaintenance); consignment is read-only. The stock list goes through App\Support\Vehicle\DealerStock (search, filters, order and view in the query string).

## Agregado e faixa de procedência também são listagem
O que o lojista não pode ler não entra em contagem, em data nem em ponto da faixa. `DealerStock`
carrega `maintenances_count`, `verified_maintenances_count`, `maintenances_max_maintenance_date` e
`provenanceStripMaintenances` pelo recorte de `Vehicle::maintenancesVisibleTo` (scope
`visibleInHistoryTo` + tenant de quem não tem o histórico liberado), num método só —
`DealerStock::visibleMaintenances` — alimentado por uma consulta memoizada dos ids com histórico
visível, que reusa a condição de `withVisibleHistory` para não existirem duas versões da regra.

Crus, eles vazavam o histórico anterior de um veículo em consignação **sem** liberação, na mesma tela
em que a coluna "Procedência" dizia "Só o que você registrou": medido, o card trazia "Última
manutenção em 15/03/2026" (serviço do proprietário) e a faixa desenhava um ponto por manutenção com
data e selo no `title`/`aria-label` — e, com `href`, um link para o registro. É o mesmo buraco que o
docblock de `Maintenance::isVisibleInHistoryTo` descreve para registro individual: **um agregado é
listagem**, e listagem e autorização têm de responder a mesma coisa.
`tests/Feature/Garage/DealerStockHistoryVisibilityTest.php` guarda os dois lados (fecha sem liberação
e com contestação; abre com o proprietário liberando e no estoque próprio).

O Início do Lojista (`Garage\DashboardController`) escapa por construção, não por cuidado: ele só
lista `$historyVehicles`, filtrado por `canViewStockVehicleHistory`. Mas carrega
`provenanceStripMaintenances` cru para **todo** o estoque — medido, hoje não vaza; no dia em que
alguém desenhar um card a partir de `$vehicles`, vaza. Carregue com o mesmo recorte antes.

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

Com o portão em pé, o selo não depende mais de vínculo provado com o veículo (ver
`.ai/rules/models.md`, "O selo não espera o proprietário"): a identidade conferida aqui é o que
responde "quem é essa oficina?", e foi o que permitiu devolver o selo ao caso legítimo.

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
