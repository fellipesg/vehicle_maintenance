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

## Hodômetro: quilometragem implausível é recusada na entrada
`VehicleMileageService::assertMaintenanceKilometers` não confere só os registros vizinhos: acima
deles vale o **teto de plausibilidade** derivado do ano do veículo
(`VehicleMileageService::plausibilityCeiling`, 422 em pt-BR). Ele é o único teto no caso comum — uma
oficina registrando pela primeira vez, sem OS posterior —, em que antes `max:9999999` deixava passar
500.000 km num carro 2024. O número não fica só no registro: vira o `current_kilometers` e o piso de
toda OS seguinte, então um dígito a mais travava o veículo até um admin ocultar o registro
(`MaintenancePublicVisibilityService`). Recusar na entrada é mais barato que desfazer.

O teto é `PLAUSIBILITY_BASE_KILOMETERS` (50.000 km) + `PLAUSIBILITY_KILOMETERS_PER_YEAR`
(100.000 km) por ano de vida, com o ano-modelo contando inteiro. A folga é grande de propósito —
~274 km/dia, todos os dias, contra os 13.000 km/ano da média brasileira
(`config('maintenance_intervals.average_kilometers_per_year')`) e os 50.000 a 80.000 de um táxi: o
teto só pega quem digitou um dígito a mais, porque recusar um veículo real é pior que aceitar um
número alto. **Não aperte a folga sem o caso de uso na mão.**

Dois casos em que o teto **não** recusa um veículo real, os dois com teste
(`tests/Feature/VehicleMileagePlausibilityTest.php`):

- **veículo sem ano conhecido** (ano zero/nulo): não há teto nenhum — não há de onde derivar, e
  chutar um ano seria recusar por chute;
- **veículo que já roda acima do teto derivado do ano**, legitimamente (frota, caminhão, veículo antigo com rodagem
  alta) ou não (erro ainda não suprimido): o teto fica um ano de folga **acima** do que o veículo já
  registra (piso da data, `current_kilometers`, `odometer_at_registration`). Parar na leitura atual
  deixaria a faixa vazia — o veículo não somaria nem um km — e seria exatamente recusar quem roda
  muito, o que a regra existe para não fazer.

O guard é **uma** implementação no serviço: as quatro superfícies que gravam km
(`StoreMaintenanceRequest`, `UpdateMaintenanceRequest`, formulário do proprietário e da oficina na
web) chamam o serviço e não repetem a regra na validação. `max:9999999` continua lá só como limite
de coluna. O teto entra na faixa que o formulário mostra: `MaintenanceMileageContext` manda o `year`
e `resources/js/utils/maintenance-kilometers.js` espelha as duas constantes — mude os dois juntos
(`.ai/rules/js.md`).

Teste que grava km alto **fixa o ano do veículo**. `VehicleFactory` sorteia
`year` entre 2010 e o ano corrente, e o teto sai desse ano: com 300.000 km o teste passava no
sorteio velho e era recusado em 2025 e 2026, ou seja, quebrava em ~12% das rodadas — foi assim que
`WorkshopSealOnThirdPartyVehicleTest` ficou intermitente depois de o teto entrar. Fixe
`'year' => now()->year - N` no factory (relativo, para não vencer com o calendário) ou mantenha o km
abaixo de 150.000, que é o teto do pior sorteio.

## Garantia emitida só acaba por escolha explícita e válida
`MaintenanceWarrantyApplicator` resolve o modelo pedido **antes** de apagar a garantia que a OS já
tem, e não ao contrário. Apagar primeiro fazia um modelo que não resolve (`resolveTemplate` exige
`workshop_id` + escopo + `is_active`, enquanto a validação só exige `exists:warranty_templates,id`)
deixar a OS **sem garantia nenhuma**, com "OS atualizada." e sem erro: basta a oficina desativar o
modelo em outra aba e salvar a OS com ele selecionado. Garantia é promessa ao cliente — encerrar uma
só vale por escolha explícita e válida ("Sem garantia", ou outro modelo que existe).

Com garantia emitida em jogo, modelo que não resolve agora é 422 (`refuseWhenIssued`, chave
`general_warranty_template_id` ou `items`). Sem nada emitido, o pedido impossível continua ignorado
em silêncio e a OS fica sem garantia: é o que
`test_deactivated_general_template_cannot_be_linked_to_a_new_os` descreve, e recusar ali derrubaria a
criação inteira da OS, na web e na API (as duas chamam o applicator dentro da transação). As
garantias por item são resolvidas todas antes de qualquer gravação: resolver linha por linha deixava a
OS pela metade quando a última falhava.

A recusa só é segura porque a edição também roda em transação, na web
(`Web\Workshop\MaintenanceController::update`) e na API (`Api\MaintenanceController::update`): OS,
itens, garantias e a recomputação das datas num bloco só. Fora da transação o 422 chegava com a OS já
gravada — a data nova salva e a garantia com as datas calculadas da data antiga — e **salvar de novo não
consertava**, porque `$previousDate` já era igual à data salva e a recomputação não rodava: a janela da
garantia ficava permanentemente fora da data do serviço mostrada ao cliente. Notas fiscais e fotos
continuam **fora** da transação (decisão própria, em `.ai/rules/invoices.md`), e nenhuma das duas chega a
rodar quando a recusa sobe. Testes: `test_a_refused_edit_rolls_back_the_os_and_a_new_save_still_recomputes_the_warranty`
e `ApiMaintenanceWarrantyTest::test_a_refused_update_rolls_back_the_maintenance`.

Modelo de garantia não se edita enquanto a oficina tem garantia vigente
(`ValidatesWarrantyTemplateImmutability`, `Workshop::hasActiveWarranties`), e a comparação só aceita
valor escalar: castar para string o que veio na requisição fazia um campo travado chegando como array
emitir "Array to string conversion", que o `HandleExceptions` lança como `ErrorException` — 500 em vez
de 422, na web e na API. Mesma armadilha do recorte de gatilho por plano
(`.ai/rules/workshop-messages.md`): `after()` roda mesmo com a regra do campo já reprovada, então
nada antes dele barra o array.

## OS sem proprietário: tenant nulo, estado na própria manutenção
Uma OS que a oficina registra num veículo sem dono atual nasce com tenant_id nulo (VehicleTenantResolver não decide mais isso; OwnerlessMaintenanceService::tenantIdFor) e owner_status pending. As colunas owner_status (pending|linked|declined), attachments_status (none|pending|accepted|declined|revoked), hidden_from_public_at e owner_decided_* não são fillable: só OwnerlessMaintenanceService e MaintenanceOwnerDecisionService gravam. owner_status nulo = OS comum. Invoice e MaintenancePhoto criados voltam attachments_status a pending (nunca depois de accepted).

## Só dono comprovado decide sobre registro de oficina
MaintenanceOwnerDecisionService::decide exige propriedade verificada (ownership_verified_at, via CRLV-e) para qualquer escolha: vincular, recusar, anexar ou ocultar. Recusar apaga as notas pendentes e ocultar some com o registro para os próximos donos, e o cadastro manual no app só pelo chassi não prova nada. Sem verificação a API devolve 422 (UNVERIFIED_DECISION_MESSAGE), o recurso expõe can_decide=false e a tela mostra só "Enviar o CRLV-e".

## Forma mínima é cópia em memória
MaintenanceRedactor::redact/redactAll devolve um clone sem descrição, valores, garantias, notas e fotos (pending/declined esconde detalhes; qualquer status diferente de accepted esconde anexos) e tira as ocultas. Use em toda superfície de histórico (VehicleTimelineBuilder, x-vehicle.detail, maintenances/_detail, MaintenanceResource, PublicVehicleSearchResource, PDF). Nunca salve o clone. Só a oficina autora (workshop_id) vê tudo; quem ocultou (owner_decided_by_user_id) ainda vê a oculta.

## Arquivos enviados nunca usam o nome original no caminho
O nome que o cliente envia pode conter nome de pessoa ou CPF (LGPD). O caminho guardado no storage é aleatório (`invoices/<Str::random(40)>.pdf`, via InvoiceUploadProcessor::storeFile), só com extensão minúscula da lista permitida (pdf, xml) ou a extensão deduzida. O nome original fica apenas nas colunas `file_name`/`original_name` para exibir a quem tem acesso. Nunca monte caminho com getClientOriginalName().

**A extensão também não é do cliente: ela sai do conteúdo.** Logo, capa e avatar passam por
`App\Support\UploadedImageExtension::for`, que lê `guessExtension()` (finfo) e aceita só jpg, jpeg,
png e webp — a mesma lista do `->types([...])` das regras —, caindo em `jpg` fora dela. Use em qualquer
upload novo que monte caminho: `WorkshopLogoService`, `VehicleCoverService` (landscape e portrait),
`BlogCoverService` e `Api\ProfileController::uploadAvatar` foram os quatro que usavam
`getClientOriginalExtension() ?: 'jpg'`.

O motivo não é estética: esses caminhos são públicos e servidos direto pelo servidor web
(`asset('storage/...')`) ou pelo CDN do bucket, e nos dois a **extensão** decide o Content-Type.
`File::image()` confere o conteúdo e deixa o nome passar, então um PNG de verdade chamado
`payload.html` era aceito e gravado como `workshop-logos/1_123.html` — medido, 302 e sem erro de
validação. Servido como `text/html`, e um PNG carrega texto arbitrário depois do IEND, o navegador
executava o que estivesse lá; com a disk local isso é na origem do app, com a sessão do visitante.
`.svg` tinha o mesmo caminho. Só `.php` e parentes eram barrados, e não por nós: é o
`shouldBlockPhpUpload` que o Laravel aplica dentro de `mimes`. Testes:
`UploadedImageExtensionTest` (Feature, as quatro superfícies; Unit, o fallback).

Caminhos gravados antes disso mantêm a extensão antiga: a correção na gravação não alcança o que já
está no storage, e um `.html` lá continua saindo como `text/html`. Quem limpa é
`php artisan storage:fix-image-extensions [--dry-run] [--limit=N]`, que lê os bytes de cada caminho,
deduz a extensão com `UploadedImageExtension::forBytes` e, quando difere, grava no caminho corrigido,
confere, aponta a coluna e só então apaga o antigo — nessa ordem, para que uma falha no meio deixe a
coluna apontando para um objeto que existe. É seguro rodar de novo, não toca no que não consegue
identificar, ignora `users.avatar` com URL de provedor e nada fora do prefixo da família. Como o
backfill de variantes de foto (`.ai/rules/jobs.md`), é manual e não faz parte do deploy.
`cover_photo_thumb_path` fica de fora: a miniatura sempre nasceu `.jpg`.

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
