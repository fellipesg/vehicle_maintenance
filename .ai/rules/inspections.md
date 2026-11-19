---
paths:
  - 'app/Models/VehicleInspection.php,app/Services/Inspection/**,app/Support/Inspection/**,app/Enums/Inspection*,app/Policies/VehicleInspectionPolicy.php,app/Features/VehicleInspections.php,app/Support/Maintenance/VerificationCode.php,app/Http/Controllers/Web/Workshop/InspectionController.php,app/Http/Controllers/Web/InspectionReportController.php,app/Http/Requests/Web/Workshop/*VehicleInspectionRequest.php,app/Http/Requests/Concerns/ValidatesVehicleInspection.php,resources/views/workshop/inspections/**,resources/views/components/inspection/**,app/Services/Vehicle/VehicleMaintenancePdfExporter.php'
---

# Vistoria cautelar (laudo)

## Laudo é entidade irmã da OS, não uma categoria de serviço
`vehicle_inspections` existe separada de `maintenances` porque uma manutenção é algo que se **fez no
carro** e uma vistoria é uma **afirmação sobre** o carro. Modelar como valor de
`App\Enums\ServiceCategory` foi considerado e recusado, por quatro coisas concretas:

1. **o veredito não teria onde morar**: `maintenance_type` é texto livre de 100 caracteres e
   `description` é prosa — "aprovado com restrições" viraria string solta, sem filtro, sem selo
   desenhável e sem resposta para "este carro foi reprovado?";
2. **inflaria o histórico**: a faixa de procedência desenha um ponto por manutenção, e carro com três
   vistorias e zero serviços leria como "3 serviços" — o oposto do que o produto vende;
3. **diluiria o selo**: `MaintenanceVerificationStamper::stamp` só sela quando a oficina que registra
   é a da OS, e os rótulos dizem isso ("Selo da oficina", "Registrada pela oficina"). O selo afirma
   "eu fiz este serviço"; o laudo afirma "eu examinei e atesto o que encontrei". São afirmações
   diferentes, e o selo é a única primitiva de confiança que o comprador confere em `/v/{código}`;
4. **garantia e NF-e não se aplicam**: o formulário ofereceria modelo de garantia para uma vistoria e
   o parsing da nota tentaria virar "peças" o que é taxa de serviço.

Por isso `VehicleInspection::provenance_label` **nunca** diz "Selo da oficina": diz
"Vistoria cautelar — <veredito>", e a faixa de procedência pede marcador próprio, não um ponto de
serviço. A relação `checklists` (initial/final, itens em JSON) é parente do laudo em formato, mas não
serve de casa: é sub-parte de uma OS, e hoje existe só na API — sem tela, sem formulário e fora do
PDF.

## O hodômetro da vistoria não move o do veículo, e isso é permanente
`vehicle_inspections.odometer_km` é gravado, mostrado e **não** alimenta
`vehicles.current_kilometers`. Não é adiamento por risco; é a forma errada para o dado:

`VehicleMileageService::refreshCurrentKilometers` é um `max()` **recalculado do zero** a partir de
`maintenances`, chamado em dez lugares (as quatro telas de OS, a API, `VehicleController`,
`MaintenanceOwnerDecisionService`, `MaintenancePublicVisibilityService` e
`vehicles:refresh-kilometers`). Escrever a leitura da vistoria na coluna faria o próximo recálculo
apagá-la em silêncio, disparado por alguém sem relação com a vistoria (um admin ocultando outra OS, o
dono contestando outra). E, mais fundo: a leitura **mais valiosa** de uma vistoria é a que vem
*abaixo* do máximo — o indício de hodômetro rodado para trás —, e `max()` descarta exatamente essa.
Alimentar só saberia subir o número, nunca relatar a queda.

O único acoplamento que faria sentido — leitura **acima** do máximo subindo o `current_kilometers` —
exige ensinar os quatro métodos a ler duas tabelas e um equivalente de
`Maintenance::scopeCountsTowardOdometer` do lado do laudo, que depende de contestação e supressão de
laudo existirem. Sem isso, um dígito errado numa vistoria viraria o piso de toda OS futura sem
alavanca de desfazer: é o acidente que `.ai/rules/models.md` descreve em "Hodômetro: sai da conta o
que foi contestado e o que a equipe suprimiu".

**A validação da leitura também não é a da OS.** `assertMaintenanceKilometers` impõe monotonicidade
(piso em `minimumAllowedKilometers`), e usá-la na vistoria **proibiria o laudo de relatar
adulteração**. A vistoria chama `VehicleMileageService::assertInspectionKilometers`, que confere só
negativo e o teto de plausibilidade derivado do ano. O piso virou detector:
`VehicleMileageService::countedMaintenanceAbove` devolve a OS que a leitura contradiz, e é com ela
que o serviço recusa um "sem apontamento" no eixo do hodômetro.

## As duas garantias do laudo são assimétricas, e guardam a transição
`App\Services\Inspection\VehicleInspectionService` é a **única** escrita de `verdict`, `findings` e
`verification_code` — nenhum dos três é `fillable`, justamente para que nenhum `update()` de
controller contorne as garantias:

- **"Aprovado" não convive com eixo reprovado** (422 em `verdict`);
- **o eixo do hodômetro não diz "sem apontamento"** quando a própria leitura do laudo está abaixo de
  uma OS que conta para o hodômetro (422 em `findings.odometer.status`).

As duas recusam a afirmação que **engana quem compra** e deixam passar a que só prejudica quem vende:
"aprovado com restrições" sem restrição nenhuma é incoerente e é aceito, porque o vistoriador assina
o que quiser e ninguém é lesado. Não aperte essa segunda direção sem o caso de uso na mão.

As guardas rodam **por campo que mudou**, não por gravação (`assertCoherent`: `$claimChanged` e
`$readingChanged` separados). Reavaliar tudo a cada `save` faria um laudo gravado antes da regra
trancar a própria tela de edição — a oficina não corrigiria nem a data sem antes "arrumar" um
hodômetro que não está mexendo. É o mesmo padrão de `EnforcesWorkshopMessageTemplateLimit` e de
`App\Rules\KnownMessageTemplateTags`. Cada uma das guardas tem teste de mutação:
`tests/Feature/Inspection/VehicleInspectionServiceTest.php`.

`App\Support\Inspection\InspectionFindings::normalize` devolve **sempre todos** os eixos de
`App\Enums\InspectionFinding`, na ordem do enum. Eixo ausente seria ambíguo — "não olhei" e "olhei e
está bem" viram a mesma coisa —, e é justamente a diferença que um laudo precisa sustentar: o padrão
é `not_assessed`.

## Emitir laudo é flag por conta, não consequência de estar aprovada
`App\Features\VehicleInspections` (Pennant, desligada por padrão) decide quem emite, e
`App\Support\Inspection\InspectionAccess::allowsIssuing` é a única resposta — nunca o Pennant direto
(`.ai/rules/features.md`). Aprovação de oficina diz "esta empresa é quem diz ser"; não diz que ela
atesta a condição de um carro de terceiro, e no Brasil a cautelar é feita por empresa especializada.
`VehicleInspectionPolicy::create` exige **as duas coisas**, e a recusa por flag carrega o código
`DENIED_FEATURE_OFF` para a tela explicar em vez de devolver 403 seco — a recusa por análise **não**
carrega esse código, senão a tela diria "pede para o admin ligar" a quem só precisa esperar a análise.

Os escopos são o usuário e a oficina. **`Garage` não entra**: o lojista é quem *lê* o laudo ao
comprar, e deixá-lo atestar o carro que ele mesmo vende é o conflito de interesse que o laudo existe
para resolver.

## Ver o laudo segue o histórico; o emissor sempre vê o próprio inteiro
`VehicleInspection::scopeVisibleInHistoryTo` e `Vehicle::inspectionsVisibleTo` são o par consulta/
memória de `isVisibleInHistoryTo`, com a mesma exigência de `.ai/rules/policies.md`: autorizar um
laudo pela rota de detalhe tem de deixar entrar exatamente quem o veria na listagem. Como em
`Vehicle::maintenancesVisibleTo`, a listagem fecha por tenant para quem não passa em
`viewFullHistory` — então a oficina emissora **não** alcança o laudo pela listagem do veículo de
terceiro, e sim pelas telas da própria oficina (a policy tem o ramo dela).

`VehicleInspection::showsFullReportTo` decide forma inteira ou mínima, e a oficina emissora está
sempre na inteira, mesmo sem vínculo com o veículo e mesmo com o laudo suprimido: foi ela que
escreveu a prosa e é ela quem corrige. `App\Support\Inspection\InspectionRedactor` tira do visitante
o resumo, as observações de cada eixo, o PDF e o texto da contestação — texto livre de vistoria
descreve o carro de alguém com o detalhe que a LGPD cobra — e **mantém** veredito, data, hodômetro,
validade, emissor, código e o **status de cada eixo**: "Reprovado: chassi e numeração do motor" sem a
prosa. O comprador fica com mais informação, não menos, como em `.ai/rules/public-lookup.md`.

**Laudo vencido não desaparece**: `isExpired()` marca, a listagem continua mostrando. Sumir com laudo
velho seria a alavanca que o produto tirou do proprietário de propósito.

## O código do laudo é LDO, e um código sem prefixo é ambíguo
`App\Support\Maintenance\VerificationCode` passou a receber o prefixo por parâmetro
(`normalize($input, $prefix)`, `random($prefix)`), com `PREFIX = 'RVL'` como padrão, e é quem gera o
código dos dois tipos — `MaintenanceVerificationStamper` perdeu o alfabeto e o gerador próprios.
Prefixo errado devolve `null` de propósito: não é só formatação, é outro documento. Um código **sem**
prefixo (6 caracteres) é ambíguo entre RVL e LDO, então quem resolve um código digitado tenta os dois
na ordem de `prefixes()`.

O laudo em PDF tem pipeline próprio: `inspections/<Str::random(40)>.pdf`, só `pdf`, caminho nunca
montado com `getClientOriginalName()` (o nome fica em `report_original_name`), e substituir apaga o
arquivo anterior. Não entra pelo de nota (que é NF-e com parsing) nem pelo de foto (que é imagem com
variantes).

## A tela da oficina começa pelo veículo, e a etapa 1 é a mesma da OS
`/oficina/vistorias` (lista, nova, detalhe, edição) espelha a OS de propósito, e a etapa 1 — placa
(`?license_plate=`) ou chassi (`?chassis=`) — é **código compartilhado**:
`App\Support\Workshop\WorkshopVehicleStep::resolve` do lado do GET,
`App\Http\Controllers\Web\Concerns\ResolvesWorkshopVehicle` do lado do POST, e a view
`resources/views/workshop/_vehicle-step.blade.php` com `$context` ('maintenance' ou 'inspection')
decidindo rota e texto. Duas cópias da mesma conta é exatamente a forma de a segunda tela esquecer
uma linha do mascaramento de `.ai/rules/public-lookup.md` — e é mascaramento que decide se a placa
atual e o hodômetro do veículo saem na tela. `WorkshopVehicleStep::none()` existe para a tela que
**não** vai mostrar resultado (conta sem a funcionalidade): consultar placa para depois não mostrar
nada seria dar o oráculo de enumeração de graça a quem a tela já recusou.

**O veredito vem depois dos achados, não antes.** O formulário é: 2 Vistoria (data, validade,
hodômetro lido) · 3 Achados (os oito eixos) · 4 Veredito e laudo (resultado, resumo, PDF). Perguntar
o resultado antes convida a marcar "Aprovado" e só então olhar item por item, que é a ordem que a
guarda de `assertCoherent` existe para desfazer; embaixo, a recusa ("o laudo reprova X, então não
pode ser Aprovado") aparece logo abaixo do que a causou. Data e hodômetro ficam em cima porque são
leitura, não juízo — e o eixo "Hodômetro" dos achados fala justamente da leitura registrada ali.

**Veículo criado pelo chassi junto com o laudo nasce sem hodômetro.** `WorkshopVehicleRegistrar`
recebe quilometragem opcional, e a vistoria não passa nenhuma: a única leitura que ela tem é a do
laudo, e essa não vira `vehicles.current_kilometers` (a regra acima). Zero é o certo aqui — um
`odometer_at_registration` com a leitura da vistoria seria piso de toda OS futura.

**A tela não mostra a OS que a leitura contradiz.** `countedMaintenanceAbove` só aparece na mensagem
da recusa, que custa uma tentativa de gravação com `throttle:uploads`; desenhar "90.000 km em
12/03/2025" numa tela GET entregaria de graça o hodômetro e a data de uma OS de outra oficina, que é
o que o mascaramento esconde de quem não tem vínculo com o veículo.

**O PDF nunca sai pelo caminho do storage.** `GET /laudos/{inspection}`
(`App\Http\Controllers\Web\InspectionReportController`, nome `inspections.report`) fica atrás de
`VehicleInspectionPolicy::viewReport`, que é a **mesma** pergunta de `showsFullReportTo` usada pela
redação: rota de arquivo mais frouxa que a tela devolveria por download o que a tela escondeu. Vale
para os três portais, como a nota fiscal e a foto (`.ai/rules/views.md`).

**Emitir não é oferecido a quem não pode.** Sem a flag: não há item de menu
(`App\Enums\Portal::navigation`, que agora recebe a conta), não há botão "Nova vistoria" na lista, e
`/oficina/vistorias/nova` **explica** em vez de devolver 403 — a oficina está aprovada, o que falta é
a equipe ligar. A lista continua mostrando os laudos já emitidos: desligar a funcionalidade não
apaga laudo nenhum, como nas fotos (`.ai/rules/features.md`).

## A página pública do laudo não é o Selo da oficina
`GET /laudo/{código}` (`App\Http\Controllers\Web\PublicInspectionController`, nome
`inspections.public`, `throttle:search`) é irmã de `/v/{código}` e **não usa o contrato visual de
procedência**: `.prov-seal`, `.prov-verified` e `<x-provenance-marker>` dizem "Selo da oficina", que
é a afirmação "eu fiz este serviço". O laudo afirma outra coisa, e emprestar o anel teal diluiria a
única primitiva de confiança do produto (`.ai/rules/theme.md`). Quem carrega o significado aqui é o
veredito, com a variante de badge do próprio enum.

A view recebe o laudo **já redigido** e não decide nada: `InspectionRedactor::redact` roda no
controller, e `showsFullReport` diz se a prosa veio. Código fora da forma canônica redireciona 301
para ela, como `/v/`; código que não existe cai em `public.verification-invalid`, a mesma página dos
dois documentos. O que a página mostra e esconde está em `.ai/rules/public-lookup.md`, com o campo
único de `/verificar`.

## O laudo na ficha do veículo é a outra metade do histórico, e não entra nas contas dele
`<x-vehicle.detail>` ganhou a aba **Vistorias** (`#vistorias`), com um `<x-inspection.card>` por
laudo, da mais recente à mais antiga. Vale para os quatro portais e a busca de uma vez, porque a
ficha é uma só (`.ai/rules/domain-components.md`). Antes dela o laudo existia e ninguém o via: a
oficina emitia, `/laudo/{código}` respondia a quem tinha o código na mão, e o proprietário não
encontrava em tela nenhuma o laudo do próprio carro.

Os laudos ficam **fora** de toda conta de manutenção — do badge da aba Histórico, do `x-ui.stat`
"Procedência", da faixa de pontos e do filtro Todas/Selo/Declaradas. É a razão de a vistoria ser
tabela própria: carro com três laudos e zero serviços não pode ler como "3 serviços". Por isso a
faixa de procedência continua com um ponto por manutenção e **sem** um terceiro termo visual.

O card **não usa** nada de `.prov-*` nem `<x-provenance-marker>`, pelo mesmo motivo da página
pública: o trilho e o anel teal afirmam "eu fiz este serviço". Quem carrega o significado é o
veredito, com a variante de badge do enum. O card mostra só os eixos **com algo a dizer** (atenção e
reprovado); a lista dos oito, com "Não avaliado" item por item, é o corpo de `/laudo/{código}`, não
de um card de lista.

**A aba só existe quando há laudo.** Vazia em quase todo veículo, ela ensinaria a ignorar as abas, e
não há ação a oferecer no estado vazio: vistoria é algo que o comprador encomenda, não algo que se
registra por essa tela.

Quem monta a listagem passa a coleção por `App\Support\Inspection\InspectionRedactor::redactAll`,
que agora **rejeita** o que o leitor não pode ver antes de redigir o resto — o mesmo contrato de
`MaintenanceRedactor::redactAll`. Confiar só na redação deixaria o laudo suprimido pela equipe
aparecer na forma mínima, que é justamente o que a supressão existe para impedir. A relação já
carregada manda (`Vehicle::restrictHistoryTo` pode tê-la limitado); sem ela, a ficha consulta.

## O laudo no PDF do histórico é onde ele vale mais
O PDF que o proprietário exporta e manda para quem está comprando leva o laudo: bloco na capa e uma
página por vistoria, depois das OS. É a superfície que alcança quem não tem conta e não vai abrir
`/laudo/{código}` — e, como o arquivo circula fora do RevisaLog, é também a que mais pede a régua de
quem pediu: `VehicleMaintenancePdfExporter::inspectionsForPdf` passa por
`Vehicle::inspectionsVisibleTo` **e** por `InspectionRedactor::redactAll`, nessa ordem, e sem
`viewer` vale a forma mínima. O desenho, as cores e as armadilhas de quebra de página estão em
`.ai/rules/pdfs.md`.

O PDF **do laudo** (`report_path`) continua fora: o arquivo do vistoriador não é mesclado no
histórico como a nota fiscal da OS. O teto de tamanho existe porque o e-mail anexa o histórico
(`.ai/rules/pdfs.md`), e um laudo de 10 MB estouraria isso sozinho; quem tem acesso baixa o arquivo
pela rota `inspections.report`, que responde a mesma pergunta da redação.

## O que ainda não existe
**Não** existe ainda: recurso de API, serviço de contestação e de supressão (as colunas existem e as
telas já as desenham, quem *escreve* não), marcador na faixa de procedência, selo no estoque do
lojista, o cartão "Este carro é seu?" na página pública (hoje só `/v/`,
porque `verification.claim` nasce de uma OS) e interruptor do admin para a flag (hoje a liberação é
por `tinker`/seed, com `App\Support\Inspection\InspectionAccess::set`). Ao acrescentar qualquer um,
varra também os lugares que *sugerem* a ação — pendência, filtro, dica, item de menu —, não só o que
a executa (`.ai/rules/features.md`).

A API espera o serviço de contestação de propósito, e não por tamanho: expor laudo no aplicativo
antes de existir contestação publicaria no celular de todo mundo uma afirmação sobre o carro que o
proprietário não tem como contradizer.
