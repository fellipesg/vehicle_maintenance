---
paths:
  - 'config/outreach.php'
  - 'app/Mail/WorkshopProspectInviteMail.php'
  - 'app/Jobs/SendWorkshopProspectInvite.php'
  - 'app/Services/Outreach/**'
  - 'app/Console/Commands/ExtractReceitaProspects.php'
  - 'app/Console/Commands/ImportWorkshopProspects.php'
  - 'app/Console/Commands/SendWorkshopProspectInvites.php'
  - 'app/Http/Controllers/Web/OutreachController.php'
  - 'app/Http/Controllers/Web/Admin/OutreachController.php'
  - 'app/Models/WorkshopProspect.php'
  - 'app/Models/EmailSuppression.php'
  - 'resources/views/emails/outreach/**'
  - 'resources/views/outreach/**'
  - 'resources/views/admin/outreach/**'
---

# Outreach (prospecção de oficinas por e-mail)

## Mailer próprio, nunca o padrão
O envio sempre usa Mail::mailer(config('outreach.mailer')) (SMTP próprio em config/mail.php, mailer 'outreach'). Em produção sai de contato@revisalog.com.br pela caixa Zoho (OUTREACH_SMTP_HOST=smtppro.zoho.com, porta 587, senha de app do Zoho só nas variáveis do Laravel Cloud); as respostas das oficinas caem nessa mesma caixa. Nunca o mailer padrão (Resend de noreply@): e-mail frio mancharia a reputação do e-mail transacional (boas-vindas, redefinição de senha). Local e testes ficam com OUTREACH_MAILER=log ou Mail::fake(); OUTREACH_ENABLED é false por padrão e nenhum segredo vai no .env versionado.

## E-mail simples de propósito
WorkshopProspectInviteMail não usa o tema Markdown da marca: e-mail frio precisa parecer escrito por uma pessoa, e HTML pesado piora a entrega. Há versão texto e um HTML mínimo (fonte do sistema, sem imagem), sem pixel de rastreio e sem rastreio de abertura. O único dado medido é o clique no link /o/{token} (scanners de e-mail podem gerar cliques falsos). Cabeçalhos List-Unsubscribe e List-Unsubscribe-Post (RFC 8058) saem em todo envio.

## No máximo duas mensagens, sem nova tentativa
Um convite e um follow-up por oficina, para sempre. O follow-up só sai se o status ainda é Sent (sem clique, resposta, conversão, descadastro ou devolução) e passaram follow_up_after_business_days dias úteis. O job tem tries=1 e ShouldBeUnique por prospect: falha vira status Failed com last_error e não é reenviada sozinha, porque e-mail frio duplicado é pior que um perdido. O job trava a linha (lockForUpdate) e confere status e supressão antes de enviar.

## Supressão é permanente e conferida antes de todo envio
email_suppressions (e-mail em minúsculas, único) nunca é apagada. Descadastro (link ou admin), devolução e descadastro manual escrevem nela; o importador, o outreach:send e o job a consultam. Quem está nela não é inserido na importação (decisão: não inserir em vez de Skipped; só Pending que entra na supressão depois vira Skipped).

## Janela e fila
outreach:send roda a cada 15 min, em dias úteis, 9h-17h America/Sao_Paulo, enfileira UM prospect (follow-ups vencidos antes do pendente mais antigo) com atraso aleatório de 0 a 600 s, e respeita OUTREACH_DAILY_LIMIT (envios de primeiro contato e follow-up no dia de São Paulo, mais jobs ainda na fila). O comando também confere a janela por dentro, não só o schedule. A pausa do painel é Cache::forever('outreach.paused'). Os jobs vão para a conexão database; em produção o worker `queue:work database` precisa estar rodando (ver jobs.md).

## Dados e rotas públicas
A origem é o CNPJ aberto da Receita (outreach:extract-receita, local, em streaming; descarta e-mail compartilhado por 3 ou mais estabelecimentos do município e e-mail com palavra de contabilidade). O descadastro (/descadastrar/{token}) é URL assinada, idempotente, e o POST fica fora do CSRF (bootstrap/app.php) para funcionar como um clique no provedor de e-mail. A conversão rastreada é e-mail enviado, link clicado, formulário de contato com ref, e depois conta criada pela equipe (WorkshopObserver converte a prospect cujo e-mail é o da oficina nova).

## Importação em lote
WorkshopProspectImporter confere CNPJ, supressão, clientes e e-mails repetidos com poucas consultas em lote (blocos de 500) e insere em blocos de 200. O upload do admin não importa na requisição: vai para a fila database (App\Jobs\ImportWorkshopProspectsCsv, com o conteúdo do CSV no job, tries=1) e quem enviou recebe o resultado por e-mail (WorkshopProspectImportFinishedMail), inclusive em falha. O comando outreach:import continua síncrono. Não volte a consultar linha a linha: eram seis consultas por oficina e o upload de ~1.200 linhas no admin estourava o tempo do Cloudflare (504). Para comparar e-mail sem diferenciar maiúsculas, selecione lower(email) com alias (selectRaw ... as normalized_email): pluck(DB::raw(...)) funciona no SQLite dos testes mas não no Postgres de produção.

## Teste de envio e rótulo no painel
`php artisan outreach:send-test {emails*} [--follow-up] [--name=]` manda o convite real pelo mailer de outreach para e-mails de teste, sem gravar prospecto, sem fila e sem contar no limite, mesmo com a prospecção desligada (em produção: `cloud command:run production --cmd=...`). O painel mostra "Sem nome fantasia" e as mensagens do admin citam "Oficina CNPJ ..." (WorkshopProspect::adminLabel); "sua oficina" é só para o texto do e-mail.
