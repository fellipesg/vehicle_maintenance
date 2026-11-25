---
paths:
  - 'app/Support/Vehicle/IssuerIndependence.php'
  - 'app/Exceptions/SelfAttestationException.php'
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php'
  - 'app/Services/Maintenance/WorkshopReviewService.php'
  - 'app/Services/Inspection/VehicleInspectionService.php'
  - 'app/Policies/MaintenancePolicy.php'
  - 'app/Policies/VehicleInspectionPolicy.php'
  - 'app/Support/Inspection/InspectionAccess.php'
---

# Independência de quem atesta

## Selo e laudo não saem sobre veículo do próprio tenant
O Selo da oficina e a vistoria cautelar são as duas primitivas de confiança do produto, e as duas
afirmam a mesma coisa debaixo do texto: **um terceiro** atestou algo sobre este carro ("eu fiz este
serviço", "eu examinei e atesto o que encontrei"). Quem atesta o próprio carro não está atestando —
está se auto-declarando, que é exatamente o que a procedência **declarada** já diz, com o rótulo
honesto. Então: oficina não sela OS nem emite laudo de veículo que o tenant dela possui.

A doutrina já existia pela metade. `App\Support\Inspection\InspectionAccess` escrevia "deixá-lo
atestar o carro que ele mesmo vende é o conflito de interesse que o laudo existe para resolver" —
mas barrava **tipo de papel** (lojista não emite laudo), não **dono do veículo**. A checagem de dono
é esta regra.

Isto virou urgente antes de existir caso alcançável: avaliar deixar um mesmo e-mail ter papel de
proprietário e de oficina torna o cenário trivial, e o invariante tem de estar de pé **antes** disso,
não depois do primeiro selo mentiroso.

A pergunta única é `App\Support\Vehicle\IssuerIndependence::workshopMayAttest($workshopId, $vehicle)`:
resolve o `tenant_id` da oficina e procura vínculo de posse **atual** daquele tenant com o veículo.
Oficina desconhecida ou veículo ausente respondem **não**: sem os dois lados não há como afirmar
independência.

### Três decisões de escopo, todas falhando fechado
- **Posse não verificada conta** (`user_vehicles.ownership_verified_at` nulo). Verificação é prova de
  *quem é o dono*; conflito de interesse é sobre *interesse*, e quem pôs o carro na própria conta já
  declarou o interesse. Exigir CRLV-e aqui transformaria "não enviei o documento" em licença para
  selar o próprio carro — incentivo exatamente invertido. É a única guarda do projeto que **não**
  segue a régua de `ownership_verified_at` de `.ai/rules/consignment.md`, e é de propósito.
- **Consignação ativa do mesmo tenant conta** (`vehicle_consignments.tenant_id`, status `active`).
  Hoje consignação é só de lojista e um tenant é de um tipo só (`TenantService`), então o cruzamento
  é inalcançável; entra porque é barato e porque é precisamente o caso que a doutrina do laudo já
  nomeia — quem tem o carro para vender não atesta o carro.
- **Tenant nulo não possui nada.** OS de oficina em carro sem dono é o caso central do produto
  (`.ai/rules/models.md`, "O selo não espera o proprietário") e segue selando. Barrar ali
  reintroduziria a regra que o `WorkshopVehicleLink` perdeu de propósito. Só a posse **atual**
  conflita: a oficina que vendeu o carro volta a ser terceiro nele.

## O gargalo é `applyWorkshopSeal`, e cada chamador decide o que fazer com a recusa
Todo selo nasce em `MaintenanceVerificationStamper::applyWorkshopSeal`, então é **ali** que o
invariante é inegociável: o método confere e lança `App\Exceptions\SelfAttestationException`
(`LogicException`) quando a oficina não pode atestar. Essa asserção não é o caminho de erro de
ninguém — é a rede para o caminho que **esquecer** de perguntar, inclusive o próximo. Um selo que
mente é pior que uma exceção.

Os quatro chamadores perguntam antes (`mayAttest`) e tratam a recusa com o que faz sentido em cada
um. As quatro respostas são diferentes porque as perguntas são diferentes:

- **`stamp()`** (caminho principal: as quatro telas de OS, a API e `MaintenanceOwnerDecisionService`)
  cai na **procedência declarada**. O serviço existiu e o histórico pertence ao chassi: recusar a OS
  inteira apagaria histórico real, e "Declarada" é a afirmação verdadeira. `registered_by_type` sai
  `owner`, porque o tenant da oficina é o dono. O atalho de QA (`autoVerifyEnabled`) está dentro do
  mesmo `elseif`: nem ele fura o invariante.
- **`sealAfterOwnerLink()`** devolve a OS **sem selo**, sem recusar o vínculo. Vincular é decisão do
  proprietário e continua valendo; o que não nasce é o selo retroativo.
- **`confirmDeclared()`** deixa a exceção subir, porque o caminho de tela já foi barrado antes: a
  fila de validação não chega lá (abaixo).
- **`verifyLinkedWorkshopMaintenances()`** (backfill) **pula** a linha. Uma OS conflitada no meio da
  tabela não pode travar as outras.

## Confirmar uma declarada é emitir selo, então a fila responde ao mesmo invariante
`MaintenancePolicy::review` exige independência além de "cita a minha oficina": sem isso a conta
declarava o serviço do próprio carro por um lado e o confirmava pelo outro — o selo pelo caminho mais
longo. E `WorkshopReviewService::syncAfterDeclaration` trata "não pode atestar" como trata "não cita
oficina": não enfileira o pedido e limpa um `workshop_review_status` pendente que virou
inconfirmável. Enfileirar o que a própria tela se nega a responder é pedir para a oficina olhar um
item morto.

## No laudo são três guardas, e a independência é a primeira
`VehicleInspectionService::assertCoherent` ganhou `assertIndependentIssuer`, que roda junto com
`$claimChanged` como as outras duas (`.ai/rules/inspections.md`, "As duas garantias do laudo são
assimétricas"): é o veredito e os achados que o conflito contamina, e corrigir só a data de um laudo
legítimo não reabre a pergunta. A recusa é 422 em `verdict`, onde a afirmação mora e onde o
formulário desenha o erro.

`VehicleInspectionPolicy` responde a parte de autorização, com o código
`DENIED_OWN_VEHICLE` separado de `DENIED_FEATURE_OFF` porque a resposta é outra — não é "pede para o
admin ligar", é "este carro é seu":

- **`create($user, ?Vehicle $vehicle = null)`**: o veículo é opcional porque a pergunta aparece nas
  duas formas. Sem veículo (item de menu, botão "Nova vistoria", a tela antes da etapa 1) vale o que
  valia; com veículo resolvido, no envio, é a que decide se o laudo nasce.
  `Workshop\InspectionController::store` confere depois de `resolveWorkshopVehicle` e recusa em
  `license_plate` — é o único caminho que chega a veículo com dono, porque pelo chassi
  `ResolvesWorkshopVehicle` já recusa carro que tem proprietário.
- **`update`/`delete`**: negados quando o carro passou a ser do tenant do emissor. As duas ações
  viram a fraude que o laudo existe para impedir — editar reescreve o veredito do carro que ele está
  vendendo, excluir apaga o "Reprovado" que o comprador precisava ler. O laudo não fica refém:
  suprimir é da equipe (`/admin/vistorias`). O preço é a oficina não arrumar nem a data depois de
  comprar o carro, e é o preço certo.

`InspectionAccess::allowsIssuingAbout($user, ?Vehicle)` é a nova pergunta com veículo (flag **e**
independência); `allowsIssuing` segue respondendo a pergunta sem veículo. Quem sabe sobre qual
veículo o laudo sai usa a primeira.

## O que este invariante NÃO cobre: duas contas, o mesmo humano
A comparação é por **`tenant_id`**. Oficina num e-mail e carro em outro são tenants diferentes, e
nada aqui enxerga que é a mesma pessoa ou a mesma empresa — o selo sai, e sai "válido". **A
procedência não está blindada contra fraude deliberada**; está blindada contra o conflito de
interesse visível na mesma conta.

Fechar a fraude de duas contas exigiria cruzar `user_vehicles.owner_document` (encrypted) com o CNPJ
da oficina, e é trabalho separado — com decisões próprias sobre comparar documento encriptado, CPF de
sócio × CNPJ da empresa e o que fazer com o falso positivo. Não presuma que esta regra já resolve
isso, e não descreva o selo como prova de independência: ele é prova de que *a conta que atestou não
é a conta que possui*.

## Testes
`tests/Feature/Maintenance/SealIssuerIndependenceTest.php` e
`tests/Feature/Inspection/InspectionIssuerIndependenceTest.php`. Cada caso tem o **par**: o caminho
conflitado negado e o caminho legítimo de terceiro intacto. Guarda que também barra o caso central
não é guarda, é regressão — foi literalmente o que derrubou o `WorkshopVehicleLink`, e um teste que
só prova a recusa não enxerga essa falha.
