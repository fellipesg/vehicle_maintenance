---
paths:
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php,app/Models/MaintenancePhoto.php,app/Services/Maintenance/**,app/Support/Maintenance/MaintenanceRedactor.php'
---

# Models

## Procedência só via MaintenanceVerificationStamper
registered_by_type, verified_at, verified_workshop_id e verification_code não são fillable. Selo (workshop) só quando o ator é oficina e workshop_id da manutenção é o da oficina. Código RVL-XXXX-XX gerado com retry. Manutenção verificada não é editável/apagável por não-oficina.

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
no backfill `photos:generate-variants` e o arquivo velho é apagado. Apagar a foto (evento `deleting` do
model) apaga o diretório inteiro; a exclusão da OS apaga as fotos uma a uma no evento `deleting` da
`Maintenance`, porque o cascade do banco não dispara eventos. Não monte caminho de foto com o nome
enviado.
