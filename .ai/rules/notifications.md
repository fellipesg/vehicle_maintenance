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

O texto **não** pode prometer que a OS sai de circulação enquanto a contestação está aberta: ela não
sai, e o e-mail dizia isso até 19/11/2026 (o mesmo erro estava no cartão de Pendências do admin).
Contestar marca e não esconde (.ai/rules/models.md); quem tira é a equipe.

## inspection_issued
VehicleInspectionIssuedNotification (mail + database + push) avisa o dono atual de que uma oficina
emitiu um laudo de vistoria cautelar sobre o veículo **dele**
(`WorkshopRecordsArrivalNotifier::notifyOwnerOfInspection`, chamado pelo store da oficina na web — a
vistoria não tem rota de API). É a irmã de workshop_record_registered e existe pelo mesmo motivo: o
laudo já entrou no histórico do chassi, com código de conferência pública, e o aviso existe para o
dono **saber** e poder contestar, não para autorizar. Conta de lojista não recebe. O payload leva
inspection_id e vehicle_id, e o link vai para a aba Vistorias da ficha (`#vistorias`), que é onde ele
encontra o laudo e o botão de contestar.

Sem este aviso o par que o produto promete ficava pela metade: a contestação existia desde o PR #78,
mas o proprietário só achava um "Reprovado" sobre o próprio carro se abrisse a aba por iniciativa
própria.

O texto **não** promete que o laudo sai de circulação enquanto a contestação está aberta: não sai
(contestar marca e não esconde), o mesmo cuidado de maintenance_disputed.

**Não confere workshop_message_opt_outs**, e nem o irmão de OS confere. Aquele descadastro (PR #79,
`App\Support\Workshop\WorkshopMessageConsent`) é das mensagens que a **oficina escreve e dispara**
ao cliente — WorkshopFollowUpNotification e o push de WorkshopMessageTemplateDispatcher. Este aviso
não é da oficina: é o produto informando o proprietário de que alguém afirmou algo sobre o bem dele, e
é a única porta de entrada para o direito de resposta. Calá-lo por um opt-out de marketing de uma
oficina qualquer tiraria em silêncio a chance de contestar um laudo, que é o oposto do que o
descadastro quer dizer. O canal de e-mail segue a supressão permanente de email_suppressions, como
todo o resto.

## inspection_disputed
VehicleInspectionDisputedNotification (mail + database, type inspection_disputed) é a irmã da de
cima para a vistoria cautelar: vai para a conta da **oficina emissora** quando o proprietário
contesta um laudo dela (`App\Services\Inspection\InspectionDisputeService::dispute`), porque é ela
quem corrige ou exclui o que assinou. O payload leva inspection_id, vehicle_id e reason, e o texto
diz o que de fato acontece: o laudo continua no histórico e na conferência pública, com a
contestação à vista.

**O aviso sai uma vez por contestação, não por POST.** `InspectionDisputeService::dispute` já era
idempotente na data, mas não no aviso: cada POST regravava `owner_dispute_reason` (500 caracteres
livres) e disparava **outro** e-mail para a caixa da oficina. Agora notifica na primeira contestação e
quando o motivo realmente muda — motivo novo é informação nova para quem corrige o laudo; reenviar o
mesmo texto não é. `MaintenanceOwnerDecisionService::dispute` recebeu a mesma correção, e as quatro
rotas de contestação entraram no balde `owner-disputes` (.ai/rules/abuse-guards.md).
