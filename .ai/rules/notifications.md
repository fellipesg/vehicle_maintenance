---
paths:
  - 'app/Notifications/**'
---

# Notifications

## MailMessage salutation is RevisaLog
MailMessage defaults the footer to Regards + APP_NAME (vehicle_maintenance on Cloud). Always chain ->salutation('RevisaLog') on toMail(). From is noreply@revisalog.com.br / RevisaLog. The brand is spelled RevisaLog (capital L) in every user-visible string; the domain and addresses stay lowercase (revisalog.com.br). Gmail SMTP still rewrites the From address; production must use MAIL_MAILER=resend so mail to suporte@ (Zoho inbox) is not a self-send.

## workshop_records_pending
WorkshopRecordsPendingNotification (mail + database, type workshop_records_pending, payload vehicle_id e count) é enviada por WorkshopRecordsArrivalNotifier, que também faz o push FCM com try/catch. Conta de lojista não recebe: a tela de registros é do Proprietário.

Gatilho: o proprietário vincular um veículo que já tinha registros pendentes
(`VehicleOwnershipService`, `Api\VehicleController`) — carro que não tinha dono quando a oficina
registrou.

## workshop_record_registered
WorkshopRecordRegisteredNotification (mail + database + push) avisa o dono atual de que uma oficina
registrou um serviço no veículo **dele** (`WorkshopRecordsArrivalNotifier::notifyOwnerOfWorkshopRecord`,
chamado pelo store da oficina na web e na API). O registro já entrou no histórico com o selo: o aviso
existe para ele **saber** e poder contestar, não para autorizar. Conta de lojista não recebe.

## workshop-approval-decided
WorkshopApprovalDecidedNotification (mail + database) é a resposta da análise do **cadastro** da
oficina (`App\Services\Workshop\WorkshopReview`, ver `.ai/rules/garage.md`). Não confundir com
WorkshopReviewDecidedNotification, que é a resposta da oficina sobre uma manutenção que alguém
declarou citando ela.

## maintenance_disputed
MaintenanceDisputedNotification (mail + database, type maintenance_disputed) vai para a conta da
**oficina** quando o proprietário contesta uma OS dela (`MaintenanceOwnerDecisionService::dispute`).
É de propósito: a oficina é a única que pode corrigir ou excluir o registro, e sem o aviso a
contestação ficaria invisível para quem resolve. O payload leva maintenance_id, vehicle_id e reason.

O texto diz o que a contestação faz — **marca, não esconde** (`.ai/rules/public-lookup.md`): a OS
continua na consulta pública com a ressalva, o selo não é revogado, e o que muda é o km sair do
hodômetro. Este e-mail já afirmou o contrário ("não aparece na consulta pública nem no histórico de
terceiros") logo antes de "corrija ou exclua a OS", e a oficina autora pode excluir a própria OS
mesmo selada (`MaintenancePolicy::canChange`): o texto antigo empurrava quem tem NF-e e serviço
feito a apagar registro legítimo — a remoção unilateral que o fluxo recusa dar ao proprietário,
conseguida por engano pelo outro lado. Por isso a mensagem separa os casos: engano de digitação se
corrige, serviço que aconteceu não se toca. `tests/Unit/MaintenanceDisputedNotificationTest.php`
guarda as duas metades.

A contestação não se denuncia sozinha, justamente porque não esconde nada: além do e-mail, ela
aparece na tela da OS (via `x-provenance-seal`, componente compartilhado), com o selo "Contestada" e
`data-disputed` na lista de OS da oficina (`workshop/maintenances/_table.blade.php`, que o Início
também usa) e como primeira pendência do Início, apontando para
`workshop.maintenances.index?situacao=contestadas`
(`WorkshopMaintenanceFilters::SITUATION_OPTIONS`).
