---
paths:
  - 'app/Models/VehicleInspection.php,app/Services/Inspection/**,app/Support/Inspection/**,app/Enums/Inspection*,app/Policies/VehicleInspectionPolicy.php,app/Features/VehicleInspections.php,app/Support/Maintenance/VerificationCode.php'
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

## O que ainda não existe
Fase 1 é só o núcleo. **Não** existe ainda: tela da oficina, rota, FormRequest (é lá que mora
`before_or_equal:today` para `inspected_at`, como nas quatro superfícies de OS), página pública
`/laudo/{código}`, recurso de API, serviço de contestação e de supressão (as colunas existem, quem
escreve não), marcador na faixa de procedência, selo no estoque do lojista, laudo no PDF do histórico
e interruptor do admin para a flag. Ao acrescentar qualquer um, varra também os lugares que
*sugerem* a ação — pendência, filtro, dica, item de menu —, não só o que a executa
(`.ai/rules/features.md`).
