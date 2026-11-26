---
paths:
  - 'app/Http/Controllers/Web/Public*'
  - 'resources/views/public/**'
  - 'app/Http/Resources/Api/V1/**'
  - 'app/Support/Vehicle/VehicleIdentifierVisibility.php'
  - 'app/Support/Vehicle/VehicleIdentifierMask.php'
  - 'app/Support/Workshop/WorkshopVehicleVisibility.php'
  - 'app/Http/Controllers/Web/Workshop/MaintenanceController.php'
  - 'app/Http/Controllers/Web/Workshop/InspectionController.php'
  - 'app/Support/Workshop/WorkshopVehicleStep.php'
  - 'app/Http/Controllers/Web/Concerns/ResolvesWorkshopVehicle.php'
  - 'resources/views/workshop/maintenances/**'
  - 'resources/views/workshop/inspections/**'
  - 'resources/views/workshop/_vehicle-step.blade.php'
---

# Public Lookup

## Vehicle search and seal lookup protect the owner
Vehicle search and /v/: chassis and RENAVAM go through App\Support\Vehicle\VehicleIdentifierMask for anyone who does not pass Gate update on the vehicle. That includes the public API search (GET /api/v1/vehicles/search/{identifier}, no auth middleware): PublicVehicleSearchResource reads the viewer from the web session or the sanctum guard ($request->user('sanctum')) and only the current owner gets the full numbers. Every API VehicleResource does the same (vehicle detail, maintenance.vehicle, admin list), through App\Support\Vehicle\VehicleIdentifierVisibility: the chassis and renavam keys stay, only the value changes, and identifiers_masked (bool) tells the app which one it got. Lists loaded through currentVehicles() are decided from the loaded pivot, without a query per vehicle; UserResource passes the account itself as viewer (login has no request user yet). /verificar and /v/{code} use throttle:search (per IP); /buscar-veiculo requires login and uses throttle:account-vehicle-search (per account), see .ai/rules/routes.md. The seal code typed or scanned is normalized by App\Support\Maintenance\VerificationCode. /verificar is the only lookup open to visitors: it is in the sitemap and in the footer ("Conferir selo da oficina"); /v/{code} and the invalid-code page carry noindex.

## Every surface for a non-owner masks, short chassis included
VehicleIdentifierMask::chassis keeps 3 + 4 characters only on a 17-character VIN; a shorter (pre-1990) chassis shows at most 40% of it, at the end ("BA1234567" -> "••••••567"), so the public search (exact chassis match) is not a guessing oracle. The same Gate update rule masks the web vehicle pages (garage.vehicles.show and user.vehicles.show pass identifiersMasked to x-vehicle.detail) and the history PDF (the export and e-mail jobs decide with VehicleIdentifierVisibility::showsFullIdentifiers for the requester).

## A busca de placa da oficina segue o mascaramento público
`GET /oficina/manutencoes/nova` (etapa 1 da OS) e `GET /oficina/vistorias/nova` (etapa 1 do laudo,
mesma conta de `App\Support\Workshop\WorkshopVehicleStep`) buscam veículo por placa e por chassi
para qualquer oficina **aprovada** (o prefixo leva `workshop.approved`): a rota leva
`throttle:account-vehicle-search`, balde pela conta e não o `search`, que é por IP
(`.ai/rules/routes.md`). Aprovação é cadastro conferido, não vínculo com carro nenhum — uma oficina
aprovada do outro lado do país não tem mais direito ao histórico deste veículo do que um visitante —,
então ela não substitui nada do que vem abaixo. Sem vínculo com o veículo — a oficina não registrou
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
(`resources/views/workshop/_vehicle-step.blade.php`, compartilhado pela OS e pelo laudo via
`$context`, e `partials/workshop-maintenance-form.blade.php`): quem esquecer recebe o card reduzido.
A conta em si mora em um lugar só, `WorkshopVehicleStep::resolve`, justamente para a tela nova não
reimplementá-la pela metade. Note que o POST ainda revela o piso do hodômetro na mensagem de erro de
`VehicleMileageService::assertMaintenanceKilometers`, com o custo de `throttle:uploads` e de uma
tentativa de OS.

## O mascaramento não acaba na etapa 1: as telas de laudo também respondem a ele
A etapa 1 era o único lugar que chamava a conta, e isso deixou um furo nas **três** telas de vistoria
da oficina — `/oficina/vistorias` (lista), o detalhe e a edição. Elas imprimiam
`$vehicle->license_plate`, a placa de **hoje**, direto da tabela `vehicles`. O que fecha o furo não é
ter emitido o laudo: **emitir laudo não cria OS**, então o emissor não ganha `workshop_id` nem
`verified_workshop_id` em nada, e `WorkshopVehicleVisibility::showsFullVehicle` continua devolvendo
false para ele. Tratar a emissão como vínculo faria dela um **oráculo de rotação de placa pelo preço
de um laudo**: a oficina digita uma placa antiga que conhece (a etapa 1 aceita e só ecoa o digitado,
porque `VehiclePlateSearch::findByPlate` resolve o veículo pela placa antiga), emite o laudo e passa a
ler na própria lista a placa atual daquele carro — e continua lendo depois de o carro ser vendido.

A régua é a de `VehicleInspection::showsFullReportTo` partida em duas: o **laudo** é sempre inteiro
para quem o assinou (foi ela que escreveu a prosa e é ela quem corrige, mesmo sem vínculo e mesmo
suprimido), e o **veículo de terceiro** não é. São perguntas diferentes, e a edição afirmava
`'showsFullVehicle' => true` com o comentário "a edição só abre para quem emitiu, então o vínculo
existe" — a premissa errada, em uma linha.

**O laudo não guarda a placa da emissão, e não é hora de guardar.** Ele desnormaliza `workshop_name`
(para sobreviver à exclusão da oficina), mas placa nenhuma, e as três razões se somam: laudo já
emitido não tem backfill honesto — a única fonte seria `vehicles.license_plate`, a placa de hoje, que
é justamente o vazamento; `/laudo/{código}` não mostra placa nenhuma (a seção abaixo), então a coluna
só seria convidada para lá; e o veículo que a oficina cadastra pelo chassi nasce **sem** placa de
propósito (`WorkshopVehicleRegistrar`, LGPD art. 6º III), que é exatamente o caso em que a oficina
mais precisaria do rótulo. Sem vínculo, então, a placa atual **sai da tela**, como em toda superfície
pública — e sem máscara parcial: não existe máscara de placa no projeto, e o docblock de
`VehicleIdentifierMask::chassis` já explica por que revelar pedaço de identificador curto vira
oráculo de tentativa. Sete caracteres não sobrevivem a isso.

**Vínculo de oficina não é a conta inteira: é a união dele com a posse de hoje.** Só o vínculo
esconde a placa do **próprio dono**, em dois caminhos que o código já prevê. A oficina que emitiu o
laudo sobre carro de terceiro e cuja tenant **comprou o carro depois** continua com o laudo na tela
(`VehicleInspectionPolicy::canChange` trata esse estado com `DENIED_OWN_VEHICLE`) e lê a placa na
ficha do veículo — esconder ali seria incoerência visível. E a conta que ainda **não** cadastrou
oficina (o onboarding, que `EnsureWorkshopApproved` deixa passar de propósito) chega ao detalhe pelo
ramo de tenant de `isVisibleInHistoryTo`, que `VehicleInspectionPolicy::view` só consulta nesse caso.
Esse ramo compara o tenant do dono **na emissão**, então aceitá-lo como resposta entregaria a placa de
hoje a quem já vendeu o carro: o mesmo furo pelo outro lado. Por isso a pergunta é uma só,
`WorkshopVehicleVisibility::fullVehicleIdsFor` (vínculo ∪ posse atual), usada pela lista **e** pelo
detalhe — duas contas separadas é como as duas telas passam a discordar, e foi assim que a placa sumiu
para o dono na primeira versão disto. O termo de posse é mais estreito que
`VehiclePolicy::viewFullHistory` de propósito: nunca mostra mais, e os outros braços daquele gate
(admin, consignação do lojista) não alcançam uma rota com `user.type:workshop`.

A explicação da placa oculta é condicional (`$viewerIsWorkshop`): dizer "a sua oficina não tem OS
deste veículo" a quem não tem oficina é afirmação falsa na tela.

Duas armadilhas de desenho ficaram fixadas em teste
(`tests/Feature/Inspection/WorkshopInspectionScreenTest.php`):

- **Nada de `$foundByFullChassis` depois da etapa 1.** A posse dos 17 caracteres libera o card no
  instante da busca, e nada a registra para depois — e uma lista é sempre o depois. Passá-la ali
  entregaria a placa que o **dono** cadastrou meses depois no veículo que a oficina criou pelo
  chassi, que é a metade "carro vendido" do mesmo furo.
- **"Placa não exibida" não é "Placa não informada".** A segunda é o veículo que não tem placa (o
  cadastrado pelo chassi); usar a mesma frase para o mascaramento afirmaria que o carro não tem
  placa. O card compartilhado distingue as duas (`$plateWithheld`).

O SQL do vínculo mora em um lugar só, como a conta da etapa 1: `WorkshopVehicleVisibility`
`::linkedVehicleIds` resolve a página inteira da lista em **uma** consulta e `showsFullVehicle`
delega — chamar a versão singular por linha seria uma consulta por laudo, e duas definições de
"vínculo" é como a próxima tela esquece uma delas.

As telas de **OS** da oficina (lista, detalhe, edição, fila de validações) imprimem a placa e estão
certas por construção, não por sorte: todas filtram por `workshop_id` (`$workshop->maintenances()`,
`maintenancesAwaitingReview`), que é a própria condição de vínculo — não há caso sem vínculo para
alcançar. `/admin/vistorias` mostra a placa de propósito, e o PDF do histórico fica atrás de
`Gate viewFullHistory` em `VehiclePdfExportService::createExport`, que oficina sem vínculo não passa.

## A conferência pública tem dois documentos, e um campo só
`/verificar` é o único campo público de código, e o produto tem dois: `RVL`, o Selo da oficina
(`/v/{código}`), e `LDO`, o laudo de vistoria cautelar (`/laudo/{código}`,
`App\Http\Controllers\Web\PublicInspectionController`, mesmo balde `search`). Com prefixo não há
dúvida e a página de destino é que responde se existe. **Sem** prefixo o código é ambíguo — seis
caracteres servem aos dois —, e aí `PublicVerificationController::resolveTypedCode` desempata pela
existência, na ordem de `VerificationCode::prefixes()`: selo, depois laudo; nenhum dos dois cai na
página de código não encontrado, que é compartilhada.

A página do laudo mostra marca, modelo, ano e o **chassi mascarado**, nunca a placa, e o hodômetro
que sai é o **lido na vistoria**, não `vehicles.current_kilometers` (que soma registros que a
consulta pública não lista). Quem não tem o histórico do veículo recebe a forma mínima de
`App\Support\Inspection\InspectionRedactor` — sem resumo, sem observação de eixo, sem PDF e sem o
texto da contestação —, e **com** os oito status: é o que serve a quem está comprando. A página diz
que a prosa existe e com quem ela fica, em vez de fingir que o laudo não tem nada.

## OS contestada aparece marcada, não sai
Registro que o proprietário contestou (`owner_disputed_at`, ver models.md) **continua** na busca
pública, no histórico, no PDF e em `/v/{código}`, com o aviso "Contestada pelo proprietário" — a busca
pública leva só o booleano `disputed`, nunca o texto que o dono escreveu. O selo não é revogado por
uma contestação: em `/v/` a página segue dizendo que o selo é autêntico e acrescenta a ressalva.

Laudo contestado segue a mesma regra — `/laudo/{código}` continua respondendo, com o aviso e sem o
texto que o dono escreveu —, e laudo **vencido** também: aparece marcado, porque histórico pertence
ao chassi. Laudo suprimido pela equipe sai para o mundo e **continua respondendo para a oficina que
o assinou** (`VehicleInspection::hiddenFrom`, não `scopePubliclyVisible`): é ela quem corrige, e
trancá-la fora trancaria a correção.

Sair da consulta pública é só com `hidden_from_public_at`, que agora **apenas a equipe** escreve
(`MaintenancePublicVisibilityService`, via `admin.maintenances.hide`). O proprietário perdeu essa
alavanca de propósito: era ela que permitia suprimir a retífica de motor antes de revender.

## Consulta pública de OS sem proprietário
Busca pública, /v/{código} e PDF só mostram o mínimo (data, km, tipo, itens, oficina, selo) de OS pending/declined, nunca anexos nem descrição, e nada do que o proprietário ocultou (/v/ responde 404). A consulta por chassi da oficina (GET /api/v1/workshop/vehicles/lookup, throttle workshop-chassis-lookup) devolve só {id, has_owner:true} quando o carro tem dono.
