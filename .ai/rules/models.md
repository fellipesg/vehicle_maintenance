---
paths:
  - 'app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php,app/Services/Maintenance/**,app/Support/Maintenance/MaintenanceRedactor.php'
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
