---
paths:
  - config/queue.php
  - config/cache.php
  - config/session.php
  - app/Jobs/**
  - docker-compose.yml
---

# Mensageria e trabalho assíncrono

## A fila é `database` no Postgres e isso é uma decisão, não uma pendência
`QUEUE_CONNECTION`, `CACHE_STORE` e `SESSION_DRIVER` são todos `database` (ver `.env.example`); o `docker-compose.yml` diz explicitamente "mirrors prod: PHP 8.4 + Postgres + database queue, no Redis". Não troque por Kafka, RabbitMQ, SQS ou Redis sem aprovação: o volume assíncrono do app é de dezenas de mensagens por dia (5 Jobs, 1 evento de domínio — `UserRegistered` —, 15 notifications `ShouldQueue`, 10 pontos de `dispatch()` em todo o `app/`, e `outreach.daily_limit` = 10 e-mails/dia). A fila na mesma transação do Postgres é garantia que o código já usa: `UserRegistered` depende disso (ver `.ai/rules/listeners.md` sobre `ShouldDispatchAfterCommit` e `RefreshDatabase`), e os testes usam `Queue::fake()`/`Event::fake()` sem subir infra. Um broker externo troca isso por dual-write (precisaria de outbox pattern para não perder nem duplicar evento) e, no caso do Kafka, por extensão C (`php-rdkafka`) no Dockerfile mais um consumer long-running com group rebalancing que o Laravel Cloud não supervisiona como `queue:work` — num app que já tem a divergência de conexão de worker descrita em `.ai/rules/jobs.md`.

## Procedência é tabela append-only, não stream
O histórico auditável (selo de `MaintenanceVerificationStamper` com código `RVL-XXXX-XX`, transições de `owner_status`/`attachments_status`, troca de posse em `VehicleConsignment`) tem natureza event-sourced e por isso atrai a ideia de log de eventos distribuído. Isso é modelo de dados, não transporte: a trilha de auditoria, o replay e a prova pertencem a uma tabela imutável no Postgres com ator e timestamp. Não introduza broker para resolver auditoria.

## Degraus antes de qualquer broker
Na ordem: (1) Redis para cache/sessão/fila — hoje todo page view e todo polling de worker bate no Neon, e esse é o gargalo previsível; (2) corrigir a divergência de conexão do worker de `.ai/rules/jobs.md` em vez de conviver com processo manual; (3) Horizon para observabilidade de fila (depende de 1); (4) tabela de eventos de domínio da procedência. Kafka não é o primeiro degrau.

## Gatilhos para reabrir a discussão de mensageria
Reavalie quando **pelo menos dois** forem verdade: ERPs/oficinas de terceiros passam a enviar OS por integração (ingestão contínua de fontes externas); três ou mais consumidores independentes precisam do mesmo stream com reprocessamento histórico (timeline, stamping, notificação, BI); o monolito se quebra em serviços com donos diferentes; a vazão sustentada passa de alguns milhares de eventos/minuto; há exigência regulatória de replay do stream bruto por anos. Com dois ou mais satisfeitos, o degrau intermediário é SQS/SNS para desacoplar serviços — Kafka só com fan-out real de alta vazão.
