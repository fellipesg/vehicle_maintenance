---
paths:
  - 'app/Providers/AppServiceProvider.php'
  - 'app/Http/Requests/Web/StoreContactRequest.php'
  - 'config/services.php'
---

# Proteções contra abuso

## Rota que manda e-mail para o endereço digitado tem balde por destinatário

Um teto só por IP não protege quem recebe: trocar de IP é barato, e um formulário público que
dispara e-mail para o endereço informado vira ferramenta de assédio sem um limite que some todos os
IPs. Por isso toda rota desse tipo leva, além do balde por IP, um `Limit::perDay` cuja chave é o
**destinatário** — hoje `POST /excluir-conta` no limiter `account-deletion`
(`App\Providers\AppServiceProvider`): 5/min e 20/h por IP, mais 5/dia por
`hash('sha256', Str::lower($email))`. Rota nova que mande e-mail para endereço digitado entra junto,
com balde próprio.

A distinção que decide se a regra vale: no `/contato` o endereço digitado vira `Reply-To` e o
destinatário é sempre o suporte, então não há caixa de terceiro para encher e o balde `contact`
(5/min por IP) basta. No `/excluir-conta` o endereço digitado **é** o destinatário.

Cada rota tem o seu balde, nunca um compartilhado: enquanto `/contato` e `/excluir-conta` dividiam o
`contact`, mensagens ao suporte gastavam a cota de quem precisava do link de exclusão.

### A chave é o que foi digitado, nunca a conta encontrada

O balde por destinatário usa o e-mail do formulário, não o resultado da consulta, e o throttle roda
no middleware, antes de qualquer ida ao banco. É isso que impede o 429 de virar conferidor de
cadastros: o limite estoura no mesmo pedido para endereço cadastrado e não cadastrado. Vale a mesma
regra da resposta única que `AccountDeletionController` e
`App\Http\Controllers\Web\Auth\PasswordResetController` já seguem — mensagem igual exista ou não a
conta. Mexer no limite sem manter isso reintroduz o oráculo que a resposta única evita.

O preço aceito é poder gastar a cota de um endereço alheio. Fica de pé porque quem precisa excluir a
conta ainda tem o caminho pelo app e o e-mail do suporte, os dois citados na própria página.

Ao testar: 6 tentativas com um IP novo em cada uma provam o teto por destinatário (nenhum balde por
IP chega perto); IPs iguais com e-mails diferentes provam o teto por IP. Comparar as duas sequências
(cadastrado × inexistente) pelos mesmos IPs, afirmando status, destino e corpo idênticos, é o teste
que trava o oráculo (`tests/Feature/Web/AccountDeletionTest.php`).

## A chave do balde é quem responde pela rota

O mesmo defeito aparece na chave, não só no balde compartilhado: num balde por IP, tela que exige
login tem a cota gasta por estranhos. `GET /oficina/manutencoes/nova` e `/buscar-veiculo` dividiam o
`search` (20/min por IP) com as buscas abertas e levavam 429 por tráfego anônimo do mesmo NAT — a
primeira é a tela que registra serviço — até as duas irem para `account-vehicle-search` pela conta.
Rota que exige login usa `$request->user()?->id ?: $request->ip()`, como `uploads`, `vehicle-link`,
`workshop-invites` e `workshop-chassis-lookup` já fazem; o IP fica para quem não tem conta. Rota que
não consulta nada — formulário abrindo vazio — devolve `Limit::none()` em vez de gastar ficha.

## Captcha do /contato é obrigatório em produção

`StoreContactRequest` exige `cf-turnstile-response` sempre que `App::isProduction()`, **mesmo sem**
`TURNSTILE_SECRET_KEY`: sem a chave o envio é recusado e a falta vira `RuntimeException` reportada,
em vez de a proteção desaparecer em silêncio junto com a variável de ambiente. Fora de produção, sem
a chave, o captcha segue desligado — honeypot e throttle continuam, e nenhum ambiente local precisa
de conta na Cloudflare.

Não troque isso por uma exceção no boot: derrubar o site inteiro por causa da chave de um formulário
de contato é raio de ação errado. Um formulário fechado chega ao suporte em minutos; um formulário
aberto não chega nunca, que era o defeito.

`php artisan about --only=revisalog` diz se a chave está no lugar (`configurado` / `SEM CHAVE`),
registrado só quando `runningInConsole()` porque `AboutCommand::add` guarda o resolvedor num
estático.

### Proteção não se autodesliga por falta de chave

`if (filled(config(...)))` em volta de uma proteção é o padrão que criou esse defeito: a variável
faltando em produção desliga a guarda e nada avisa. Quem depende de segredo falha fechado e deixa
rastro — `SentryWebhookController` responde 503 sem `services.sentry_webhook.client_secret`, e
`Api\AuthController::redirectToProvider` responde 500 sem as credenciais do provedor. Guarda nova
que leia segredo do ambiente segue os três.
