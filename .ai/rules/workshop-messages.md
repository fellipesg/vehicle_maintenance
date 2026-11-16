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
  - 'app/Rules/KnownMessageTemplateTags.php'
---

# Mensagens automáticas da oficina

## Só gatilho com rodada entra num modelo
WorkshopMessageTrigger tem quatro casos e o WorkshopMessageTemplateDispatcher sabe disparar dois (ScheduledRevision, por quilometragem estimada, e CorrectiveFollowUp, por data). WarrantyExpiring e Reactivation existem no enum e nos planos do config, mas não têm rodada: WorkshopMessageTrigger::isDispatchable() é a única fonte dessa verdade, e dela saem a validação das quatro rotas (Rule::enum(...)->only(dispatchableCases())) e o filtro da rodada (whereIn trigger, dispatchableValues()). O select do formulário sai de WorkshopPlanLimits::triggerOptions(), que recorta isso pelo plano; dispatchableOptions() existia para o select e foi removido junto, para não sobrar atalho que ignore o plano. Implementar um deles é acrescentar a rodada e trocar o false por true no enum — nunca só o contrário.
Até lá, nenhuma cópia em tela pode prometê-los: a dica de "Dias após o serviço" dizia "para o gatilho
de retorno após serviço **ou garantia vencendo**", um gatilho que o select não oferece e que nada lê
desse campo (só `dispatchCorrectiveTemplate` o lê). O `match` do dispatcher tem default => 0 porque um gatilho marcado com rodada antes de a rodada existir derrubava a rodada diária de *todas* as oficinas com UnhandledMatchError.

## O canal é de reengajamento, não transacional
Nenhum gatilho sai no fechamento da OS: o follow-up por data sai min_days_since_service dias depois (padrão 60) e o de revisão sai pela quilometragem estimada. "Carro pronto, vem buscar" não tem como sair daqui. O destino é sempre o dono atual vinculado ao veículo (user_type user ou garage), por e-mail, sino e push ao mesmo tempo; carro sem dono vinculado não tem destinatário e não gera nada.

## As variáveis do texto são sete, e a lista é uma só
`WorkshopMessageTemplateRenderer::TAGS` é a fonte única: o `render()` troca exatamente essas sete
(`workshop_name`, `customer_name`, `vehicle`, `estimated_km`, `next_due_km`, `days_since_service`,
`last_service`), `buildContext` as monta, o formulário da oficina as lista em tela e
`App\Rules\KnownMessageTemplateTags` recusa qualquer outra nas quatro rotas. Acrescentar uma é mexer
nos três lugares; `WorkshopMessageTemplateRendererTest` falha se a lista e o `render()` divergirem.
Não há lista de itens da OS no contexto.

O `render()` usa `strtr`, então o que não casa **continua no texto e chega ao cliente como está** —
`{{cliente}}`, `{{list_servicos}}` e também `{{ customer_name }}`, porque o espaço dentro das chaves
faz a chave não casar. Antes nenhuma tela listava as sete e nada na validação avisava: a oficina
inventava o nome e descobria pelo cliente. A regra recusa com o nome do que foi escrito e a lista do
que vale.

O recorte da regra vale na **transição**, como o teto de ativos e o plano de gatilhos: texto reenviado
igual ao que está gravado passa. Sem isso, um modelo gravado antes dela trancaria a própria tela onde
a oficina o corrige ou o desativa — e no update da web título e corpo vão no mesmo POST, então
desativar reenvia os dois. `WorkshopMessageTemplateTagsTest` guarda as duas direções.

O renderer devolve texto puro: quem exibe escapa (Blade do sino e do e-mail, assunto do MailMessage,
payload do FCM), então escapar aqui dobra o escape de "José & Filhos".

## As guardas do config são quatro, e as outras chaves não têm leitor
Impostas: `plans.*.active_templates` e `plans.*.triggers` (WorkshopPlanLimits, nas quatro rotas que criam ou alteram um modelo), `send_window_days` (o follow-up por data só pega OS entre min_days_since_service e min_days + janela, senão a oficina que liga o modelo hoje dispara para todo o histórico dela de uma vez) e `min_days_between_messages_per_user` (intervalo mínimo por cliente, somando todas as oficinas e todos os modelos; o índice user_id+sent_at de workshop_message_dispatches existe para essa consulta, memoizada por rodada em WorkshopMessageTemplateDispatcher::isWithinQuietPeriod). Sem leitor: `warranty_lead_days_default`, `reactivation_days_default` e `return_window_days` — não as cite como controle sem implementá-las. O intervalo mínimo não grava nada quando bloqueia: a oportunidade continua livre para a próxima rodada. O teto de ativos vale na *transição* para ativo (EnforcesWorkshopMessageTemplateLimit), nunca no estado: uma oficina pode estar acima dele sem ter burlado nada — plano rebaixado pelo admin, ou dado de antes de o teto existir — e aí contar os outros ativos barrava toda edição dos modelos que ela já tem, inclusive na única tela onde se desativa um deles, que é a saída dela.

## O plano estreita os gatilhos, nunca amplia
`plans.*.triggers` ficou no config desde o começo sem nada a ler, e por sorte: os dois gatilhos com
rodada eram exatamente os dois do Free, então não havia vazamento — a armadilha era para o futuro.
Implementar "Garantia vencendo" é trocar um false por true em `isDispatchable()`, e isso liberaria
para o Free um gatilho que o config diz ser do Pro, sem ninguém notar. Agora WorkshopPlanLimits
lê a chave, nas quatro rotas (EnforcesWorkshopMessageTemplateLimit::addMessageTemplateTriggerPlanRule),
no select do formulário e na rodada diária.

A lista do plano é **cruzada** com `dispatchableCases()`: ela só tira, nunca acrescenta. Plano não
liga gatilho sem rodada — `warranty_expiring` está no Pro e continua não valendo nada —, porque um
modelo ativo com gatilho sem rodada não manda nada e caía no `default => 0` do dispatcher. Plano sem
a chave `triggers` fica sem recorte, igual a `active_templates` null.

Como o teto de ativos, o recorte vale na **transição** e não no estado: o gatilho que o modelo já
tem passa sempre, porque o plano é trocado pelo admin e trancar a edição trancaria a tela onde a
oficina corrige o texto e desativa o modelo. O select do edit mantém o gatilho herdado pelo mesmo
motivo — sem ele, abriria em branco e salvar trocaria o gatilho sem a oficina pedir. A porta de
saída não é buraco: o gatilho herdado passa nesse modelo e em nenhum outro. E, porque a validação
só guarda a transição, quem impede o disparo depois do rebaixamento é a rodada
(`WorkshopPlanLimits::allowsTrigger` no loop de `dispatchFollowUps`), que não grava nada ao barrar:
se a oficina voltar ao plano, a oportunidade continua livre.
`tests/Feature/Workshop/WorkshopMessageTemplateTriggerPlanTest.php` guarda as duas direções.
