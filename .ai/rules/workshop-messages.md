---
paths:
  - 'app/Services/Workshop/WorkshopMessageTemplateDispatcher.php'
  - 'app/Enums/WorkshopMessageTrigger.php'
  - 'app/Enums/WorkshopPlan.php'
  - 'app/Support/Workshop/WorkshopPlanLimits.php'
  - 'app/Support/WorkshopMessageTemplateRenderer.php'
  - 'config/workshop_messages.php'
  - 'app/Http/Requests/Web/Workshop/*MessageTemplateRequest.php'
  - 'app/Http/Requests/Api/V1/*WorkshopMessageTemplateRequest.php'
  - 'resources/views/workshop/message-templates/**'
---

# Mensagens automáticas da oficina

## Só gatilho com rodada entra num modelo
WorkshopMessageTrigger tem quatro casos e o WorkshopMessageTemplateDispatcher sabe disparar dois (ScheduledRevision, por quilometragem estimada, e CorrectiveFollowUp, por data). WarrantyExpiring e Reactivation existem no enum e nos planos do config, mas não têm rodada: WorkshopMessageTrigger::isDispatchable() é a única fonte dessa verdade, e dela saem o select do formulário (dispatchableOptions()), a validação das quatro rotas (Rule::enum(...)->only(dispatchableCases())) e o filtro da rodada (whereIn trigger, dispatchableValues()). Implementar um deles é acrescentar a rodada e trocar o false por true no enum — nunca só o contrário. O `match` do dispatcher tem default => 0 porque um gatilho marcado com rodada antes de a rodada existir derrubava a rodada diária de *todas* as oficinas com UnhandledMatchError.

## O canal é de reengajamento, não transacional
Nenhum gatilho sai no fechamento da OS: o follow-up por data sai min_days_since_service dias depois (padrão 60) e o de revisão sai pela quilometragem estimada. "Carro pronto, vem buscar" não tem como sair daqui. O destino é sempre o dono atual vinculado ao veículo (user_type user ou garage), por e-mail, sino e push ao mesmo tempo; carro sem dono vinculado não tem destinatário e não gera nada.

## As variáveis do texto são sete, e o que não existe sai literal
WorkshopMessageTemplateRenderer::buildContext monta {{workshop_name}}, {{customer_name}}, {{vehicle}}, {{estimated_km}}, {{next_due_km}}, {{days_since_service}} e {{last_service}}. O render usa strtr: tag desconhecida ({{proprietario}}, {{list_servicos}}) **não** é trocada e chega assim ao cliente, e nada na validação nem no formulário avisa. Não há lista de itens da OS no contexto. O renderer devolve texto puro: quem exibe escapa (Blade do sino e do e-mail, assunto do MailMessage, payload do FCM), então escapar aqui dobra o escape de "José & Filhos".

## As guardas do config são três, e as outras chaves não têm leitor
Impostas: `plans.*.active_templates` (WorkshopPlanLimits, nas quatro rotas que criam ou ativam um modelo), `send_window_days` (o follow-up por data só pega OS entre min_days_since_service e min_days + janela, senão a oficina que liga o modelo hoje dispara para todo o histórico dela de uma vez) e `min_days_between_messages_per_user` (intervalo mínimo por cliente, somando todas as oficinas e todos os modelos; o índice user_id+sent_at de workshop_message_dispatches existe para essa consulta, memoizada por rodada em WorkshopMessageTemplateDispatcher::isWithinQuietPeriod). Sem leitor: `plans.*.triggers`, `warranty_lead_days_default`, `reactivation_days_default` e `return_window_days` — não as cite como controle sem implementá-las. O intervalo mínimo não grava nada quando bloqueia: a oportunidade continua livre para a próxima rodada. O teto de ativos vale na *transição* para ativo (EnforcesWorkshopMessageTemplateLimit), nunca no estado: uma oficina pode estar acima dele sem ter burlado nada — plano rebaixado pelo admin, ou dado de antes de o teto existir — e aí contar os outros ativos barrava toda edição dos modelos que ela já tem, inclusive na única tela onde se desativa um deles, que é a saída dela.
