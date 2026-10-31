---
paths:
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php,app/Models/MaintenancePhoto.php,app/Services/Maintenance/**,app/Support/Maintenance/MaintenanceRedactor.php'
---

# Models

## Procedência só via MaintenanceVerificationStamper
registered_by_type, verified_at, verified_workshop_id e verification_code não são fillable. Selo (workshop) só quando o ator é oficina e workshop_id da manutenção é o da oficina. Código RVL-XXXX-XX gerado com retry. Manutenção verificada não é editável/apagável por não-oficina.

## O selo não espera o proprietário
Oficina **aprovada** (`.ai/rules/garage.md`) sela a OS que registrou em **qualquer** veículo, com ou
sem proprietário cadastrado, e o registro entra no histórico do chassi na hora. O histórico pertence
ao chassi e é o ativo do produto: ele não depende de um terceiro clicar, e silêncio não vira veto.

Houve um período em que o selo em carro com dono exigia vínculo provado com o veículo
(`WorkshopVehicleLink`, removido). A regra caiu porque cobrava o preço no caso central — oficina
credenciada atendendo cliente novo cujo carro já tem conta — e podia deixar aquela oficina sem selo
para sempre se o dono ignorasse o aviso. O risco que ela endereçava ("quem é essa oficina escrevendo
no carro de terceiro?") passou a ser coberto por outras três coisas, que juntas valem mais:

1. **identidade**: só oficina de CNPJ conferido e aprovada por um admin registra (`EnsureWorkshopApproved`);
2. **aviso**: o dono atual é avisado na hora (`WorkshopRecordRegisteredNotification`);
3. **resposta**: ele pode contestar, sem CRLV-e, e a contestação aparece em todas as superfícies.

`provenance_label` não chama de "Selo da oficina" um registro de oficina sem selo — não existe mais
caminho que crie um, mas dados daquele período continuam valendo.

Em carro **com** dono o registro nasce com `owner_status` nulo (é manutenção comum do histórico
dele); **sem** dono, nasce `pending` e espera quem assumir o veículo (fluxo de convite e decisão).

Data de manutenção futura é recusada na validação das quatro superfícies (`before_or_equal:today`).

## O proprietário contesta; esconder é da equipe
**O dono não oculta mais registro nenhum.** O `hide_from_public` saiu de
`MaintenanceOwnerDecisionService::decide`, e a razão é o próprio produto: o histórico pertence ao
chassi e é o ativo, e aquele ocultar apagava justamente o que o comprador precisa ver — sem deixar
sinal, o que torna "carro sem histórico" indistinguível de "histórico suprimido". Quem escondia era
quem tinha interesse em esconder.

No lugar entrou `dispute`, a única ação que **não** exige `ownership_verified_at`: basta ser o dono
atual, e o registro ser de oficina (`Maintenance::isDisputable` = `registered_by_type` workshop, com
selo ou sem — o que o próprio dono ou o lojista declarou ele resolve editando). Contestar **marca e
não esconde**: grava `owner_disputed_at`/`owner_disputed_by_user_id`/`owner_dispute_reason` (nenhum
fillable), o registro continua em TODAS as superfícies com o aviso "Contestada pelo proprietário"
(`provenance-card`, `provenance-seal`, `/v/{código}`, PDF, busca pública e linha do tempo), a oficina
autora é avisada (`MaintenanceDisputedNotification`, é ela quem corrige ou exclui) e o caso entra na
fila do admin. O comprador fica com **mais** informação, não menos. Vincular depois limpa a
contestação (vincular é aceitar) e `withdrawDispute` desfaz.

O texto do proprietário (`owner_dispute_reason`) **não** vai para a busca pública: lá só a marca. Ele
aparece para o próprio dono, para a oficina (na notificação) e no admin.

Esconder de verdade continua existindo, mas só com um humano responsável:
`App\Services\Maintenance\MaintenancePublicVisibilityService` é a única escrita de
`hidden_from_public_at`, chamada de `admin.maintenances.hide`, e grava `hidden_by_user_id` e
`hidden_reason`. É o fim de linha de uma contestação que a equipe confirmou (fraude, chassi errado) ou
de um pedido de remoção que a empresa honre. `hiddenFrom` e o scope `visibleInHistoryTo` seguem
olhando só `hidden_from_public_at` — contestação não entra ali, e as duas formas continuam casadas
como policies.md exige.

**Registros ocultados antes desta mudança continuam ocultos**: republicar na marra o que alguém
exerceu como direito de oposição é decisão de produto e jurídico, não de migration.

## Hodômetro: sai da conta o que foi contestado e o que a equipe suprimiu
`Maintenance::scopeCountsTowardOdometer` é o filtro de `VehicleMileageService` (apply, refresh e os
limites de km) e deixa de fora duas coisas: **o que o proprietário contestou** (`owner_disputed_at`)
e **o que a equipe tirou de circulação** (`hidden_by_user_id`). Era por aí que uma quilometragem
absurda entrava e não saía mais.

As duas juntas, e nessa ordem, por um motivo: sem a segunda, a ação mais fraca (a declaração de uma
das partes) limpava o número e a mais forte (a supressão decidida depois da análise) não — o km de um
registro que ninguém mais vê continuava público e continuava sendo o **piso de plausibilidade**,
recusando toda OS nova abaixo dele. Um erro de chassi de 500.000 km trancava o veículo para sempre, e
o dono nem podia contestar, porque `hiddenFrom` já tinha tirado o registro das telas dele.

O ocultar do **fluxo antigo**, que era do proprietário, continua contando: ali o serviço aconteceu e
ele só se opôs à publicação. Esses registros têm `hidden_from_public_at` com `hidden_by_user_id`
nulo, e é essa diferença que o scope usa — não o fato de estar oculto.

Toda mudança no que conta recalcula na hora: `dispute`, `withdrawDispute`, `decide`
(MaintenanceOwnerDecisionService) e `hide`/`restore` (MaintenancePublicVisibilityService). Sem isso o
veículo fica com o km velho até a próxima gravação qualquer.

## OS sem proprietário: tenant nulo, estado na própria manutenção
Uma OS que a oficina registra num veículo sem dono atual nasce com tenant_id nulo (VehicleTenantResolver não decide mais isso; OwnerlessMaintenanceService::tenantIdFor) e owner_status pending. As colunas owner_status (pending|linked|declined), attachments_status (none|pending|accepted|declined|revoked), hidden_from_public_at e owner_decided_* não são fillable: só OwnerlessMaintenanceService e MaintenanceOwnerDecisionService gravam. owner_status nulo = OS comum. Invoice e MaintenancePhoto criados voltam attachments_status a pending (nunca depois de accepted).

## Só dono comprovado decide sobre registro de oficina
MaintenanceOwnerDecisionService::decide exige propriedade verificada (ownership_verified_at, via CRLV-e) para qualquer escolha: vincular, recusar, anexar ou ocultar. Recusar apaga as notas pendentes e ocultar some com o registro para os próximos donos, e o cadastro manual no app só pelo chassi não prova nada. Sem verificação a API devolve 422 (UNVERIFIED_DECISION_MESSAGE), o recurso expõe can_decide=false e a tela mostra só "Enviar o CRLV-e".

## Forma mínima é cópia em memória
MaintenanceRedactor::redact/redactAll devolve um clone sem descrição, valores, garantias, notas e fotos (pending/declined esconde detalhes; qualquer status diferente de accepted esconde anexos) e tira as ocultas. Use em toda superfície de histórico (VehicleTimelineBuilder, x-vehicle.detail, maintenances/_detail, MaintenanceResource, PublicVehicleSearchResource, PDF). Nunca salve o clone. Só a oficina autora (workshop_id) vê tudo; quem ocultou (owner_decided_by_user_id) ainda vê a oculta.

## Arquivos enviados nunca usam o nome original no caminho
O nome que o cliente envia pode conter nome de pessoa ou CPF (LGPD). O caminho guardado no storage é aleatório (`invoices/<Str::random(40)>.pdf`, via InvoiceUploadProcessor::storeFile), só com extensão minúscula da lista permitida (pdf, xml) ou a extensão deduzida. O nome original fica apenas nas colunas `file_name`/`original_name` para exibir a quem tem acesso. Nunca monte caminho com getClientOriginalName().

## Prévia do proprietário: dono atual com posse comprovada vê os anexos antes de aceitar
`Maintenance::hidesAttachmentsFrom` não esconde mais notas e fotos de uma OS sem dono do dono atual do
veículo com a posse comprovada (`isVerifiedCurrentOwner`: `user_vehicles.is_current_owner` e
`ownership_verified_at` não nulo, no tenant dele; é a mesma noção de "comprovou a posse" do
`MaintenanceOwnerDecisionService::ownershipVerified`). Assim ele decide vincular/aceitar informado.
Continua sem ver: dono sem comprovação, ex-dono, comprador, outro tenant e visitante. Só os anexos abrem:
descrição, valores e garantias seguem na forma mínima (`hidesDetailsFrom`). `exposesAllPhotosTo` também
libera antes/durante/peça a esse dono; fora do tenant e da oficina autora só veículo + depois.
MaintenancePhotoPolicy e InvoicePolicy::view herdam a regra.

## Foto: master sem EXIF, variantes por job, arquivos juntos
Upload guarda só o **master** (lado maior até 2048px, JPEG q85, orientação do EXIF aplicada, EXIF/GPS
removidos) em `maintenance-photos/{maintenance_id}/{uuid}/master.jpg`; o original nunca é guardado. O
`ProcessMaintenancePhoto` (afterCommit) cria `thumb` (320px, WebP q70) e `display` (1280px, WebP q80) ao
lado, e preenche thumb_path, display_path, processed_at (width/height/bytes/dominant_color já vêm do
master). `MaintenancePhotoImageProcessor` usa Imagick quando a extensão existe e GD caso contrário (os
dois têm teste); sem `imagewebp` as variantes saem em JPEG. Foto antiga (arquivo solto) ganha master novo
no backfill `photos:generate-variants`; o arquivo velho **nunca é apagado** pelo backfill (decisão do dono): fica em `legacy_path`, rastreável, e qualquer limpeza futura é decisão separada e explícita (não existe comando para isso). Apagar a foto (evento `deleting` do
model) apaga o diretório inteiro e o `legacy_path`, se houver (é a vontade do usuário, não limpeza); a exclusão da OS apaga as fotos uma a uma no evento `deleting` da
`Maintenance`, porque o cascade do banco não dispara eventos. Não monte caminho de foto com o nome
enviado.
