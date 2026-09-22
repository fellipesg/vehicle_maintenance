# Plano: e-mail transacional, caixa suporte@, rodapé Revisalog, termos e contato

Status: **plano atualizado** — e-mail só na infra de produção. Nada de Mailpit, SMTP local, chave Resend no `.env` da máquina, nem disparo de teste a partir do laptop.  
Data: 2026-09-21  
Site: https://revisalog.com.br

## Restrição (2026-09-21)

**Suba nada local. Use a infra de prod.**

- Não levantar Mailpit, SMTP em `127.0.0.1`, nem qualquer servidor de e-mail na máquina.
- Não colocar `RESEND_API_KEY` no `.env` local e não enviar e-mail real a partir do laptop.
- Não publicar/uploadar assets ou config de e-mail “da máquina” para o CDN/R2 como parte deste trabalho — marca e arquivos já estão na infra de prod (`cdn.revisalog.com.br`, Laravel Cloud).
- Conta Resend, DNS (SPF/DKIM/DMARC/MX), caixa `suporte@` e variáveis `MAIL_*` / `RESEND_API_KEY` entram **só** no Laravel Cloud + DNS de `revisalog.com.br`.
- Na máquina: `MAIL_MAILER=log` (e `Mail::fake()` nos testes) para o código nunca sair. Teste real só depois do deploy, no ambiente de produção.

## Decisão resumida

Não usar SendGrid agora. O Laravel Cloud não envia e-mail sozinho. Tudo que entrega ou recebe e-mail passa pela infra de produção.

| Problema | Ferramenta | Por quê |
| --- | --- | --- |
| Sistema **enviar** (notificações, PDF, relatórios) | **Resend** | Driver nativo no Laravel 12, setup rápido, ~3 mil e-mails/mês no gratuito, cabe no lançamento |
| Humano **receber** `suporte@revisalog.com.br` | **Cloudflare Email Routing** (zona de prod de `revisalog.com.br`) | Registro.br não cria caixa. MX/DNS só na infra de prod. Sem servidor de e-mail local. |

São dois sistemas. Não misturar caixa humana com disparo automático.

---

## 1. Enviar e-mail (app → usuário)

O pipeline já existe:

- Lembrete de km: `MaintenanceKmReminderNotification` (`mail` + `database`)
- Follow-up de oficina: `WorkshopFollowUpNotification`
- Relatório PDF: `VehicleMaintenancePdfMail` via job `EmailVehicleMaintenancePdf` (fila `database`)

O código já aponta para Resend + `noreply@` / `suporte@`. O que falta é a conta/DNS/variáveis **no Laravel Cloud** — não na máquina.

### Comparativo

| Opção | Vale? | Motivo |
| --- | --- | --- |
| **Resend** | **Sim — escolha** | `MAIL_MAILER=resend`. Cadastro rápido, domínio verificado com DNS |
| Amazon SES | Depois, se o volume explodir | Já usamos AWS/S3; barato, mas sandbox e pedido de produção são ruins na primeira vez |
| Postmark | Alternativa cara | Melhor entregabilidade transacional; pago desde o dia 1 |
| **SendGrid** | **Não agora** | Sem driver nativo no Laravel 12 (só SMTP). Mais caro, feito para marketing + transacional. Complexidade sem ganho neste estágio |

### Remetentes

- **De:** `Revisalog <noreply@revisalog.com.br>` (ou `notificacoes@`)
- **Responder para:** `suporte@revisalog.com.br`

### Variáveis no Laravel Cloud

```env
APP_NAME=Revisalog
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=noreply@revisalog.com.br
MAIL_FROM_NAME=Revisalog
```

Na máquina: `MAIL_MAILER=log` apenas (nunca Mailpit, nunca SMTP local, nunca a API key). A key e `MAIL_MAILER=resend` ficam só no Laravel Cloud.

Laravel 12: transportes nativos incluem `smtp`, `ses`, `postmark`, `resend`, `mailgun`. SendGrid não está na lista.

Mailables/notificações já usam `ShouldQueue` / fila `database`. Manter envio assíncrono. PDF worker em produção: `queue:work database --timeout=300 --tries=2`.

---

## 2. Receber `suporte@revisalog.com.br`

Registro.br **não cria caixa de e-mail**. Lá se registra o domínio. Caixa é outro produto, via registros **MX** no DNS.

Caminho de lançamento: **Cloudflare Email Routing** (já na conta de prod do domínio / R2) para criar `suporte@revisalog.com.br`. Destino do encaminhamento é um e-mail que você já usa — a caixa e os MX ficam no DNS de produção, não na máquina.

Não criar conta de e-mail “local”, não usar Registro.br como host de mailbox, não apontar MX para um servidor em casa.

### Passo a passo (primeira vez — só painéis de prod)

1. Abrir o DNS de **produção** de `revisalog.com.br` (Cloudflare ou onde o domínio aponta).
2. Ativar Email Routing na zona de prod.
3. Verificar o e-mail de destino no próprio painel da Cloudflare.
4. Criar o endereço `suporte@`.
5. Publicar os MX (e o TXT de SPF) que a Cloudflare mostra — **sem apagar** os registros do site no Laravel Cloud.
6. Mandar um teste de um endereço externo e confirmar que chegou. Não usar a máquina local como remetente.

O `AddressGeocoder` já usa `admin@revisalog.com.br` no User-Agent; `suporte@` é a caixa pública de contato.

---

## 3. O que fazer no Registro.br e na Cloudflare

Checagem em 2026-09-21: o domínio **já usa nameservers da Cloudflare** (`aspen.ns.cloudflare.com` e `odin.ns.cloudflare.com`). O site (`A` no apex → Laravel Cloud; `www` → `to.laravel.cloud.`) e o CDN (`cdn` atrás da Cloudflare) já estão no ar. **Ainda não existem MX, SPF, DKIM nem DMARC.**

### Registro.br — quase nada

Lá o domínio está registrado (titular Felipe). **Não cria caixa de e-mail.** Como o DNS já foi delegado à Cloudflare, qualquer MX/TXT que você criar na zona do Registro.br **é ignorado**.

Faça só isto:

1. Entrar em [registro.br](https://registro.br) → domínio `revisalog.com.br`.
2. Confirmar **servidores DNS** = `aspen.ns.cloudflare.com` e `odin.ns.cloudflare.com` (já está assim). **Não mude.**
3. Não contratar e-mail, hospedagem ou “criar conta suporte@” nesse painel.
4. Manter o domínio em dia (renovação).

Pronto. O resto é Cloudflare (+ depois Resend e Laravel Cloud).

### Cloudflare — tudo de e-mail

Painel: [dash.cloudflare.com](https://dash.cloudflare.com) → zona **revisalog.com.br**.

**Não apague nem edite** os registros que já mantêm o site: `A`/`AAAA` do apex, `www` → `to.laravel.cloud`, `cdn`. Se um registro do site estiver com nuvem laranja (proxy), deixe como está.

#### A) Receber `suporte@` (agora)

O item **não fica no menu do domínio** (lá, em Email, só aparece Gerenciamento de DMARC). O Routing mudou para o nível da conta:

1. No topo esquerdo, **Back to Domains** (sair da zona `revisalog.com.br`).
2. Menu da conta: **Compute** (ou **Compute & AI**) → **Email Service** → **Email Routing**.  
   Atalho: na busca do painel, digite `Email Routing`.  
   URL: `https://dash.cloudflare.com/<account_id>/email-service/routing/`
3. **Onboard Domain** → escolher `revisalog.com.br`. A Cloudflare cria sozinha os **MX** de recebimento. Aceite; não apague MX depois.
3. Em **Destination addresses**, adicionar o e-mail que deve receber as mensagens (Gmail etc.) e **confirmar o link** que chegar nesse e-mail.
4. Em **Custom addresses**, criar `suporte` → destino = o e-mail verificado. Status Active.
5. Se ela oferecer um TXT SPF (`include:_spf.mx.cloudflare.net`), deixe como está. O Resend **não** entra nesse registro: ele envia pelo subdomínio `send.revisalog.com.br` e publica o SPF dele lá (ver B).

Teste: de outro e-mail (não o de destino), mandar para `suporte@revisalog.com.br` e ver se cai na caixa verificada.

#### B) Enviar pelo sistema (quando a conta Resend existir)

No Resend: **Domains** → Add `revisalog.com.br`. Ele mostra 2–4 registros. Volte à Cloudflare → **DNS** → **Add record**, um por um, **DNS only** (nuvem cinza), sem mexer no resto:

| Tipo | O que o Resend pede (exemplo) | Nota |
| --- | --- | --- |
| TXT | DKIM (`resend._domainkey`) | Copiar do painel Resend |
| MX | `send` → `feedback-smtp.<região>.amazonses.com` | Bounces do Resend; não conflita com os MX do Email Routing, que ficam no apex |
| TXT | `send` → `v=spf1 include:amazonses.com ~all` | SPF do Resend, **no subdomínio `send`**. Não mexer no SPF do apex |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:suporte@revisalog.com.br` no começo |

No Laravel Cloud (não é Registro.br nem Cloudflare): `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS=noreply@revisalog.com.br`.

Envio (`noreply@` via Resend) e recebimento (`suporte@` via MX da Cloudflare) convivem no mesmo domínio.

---

## 4. Rodapé, termos e contato (código)

### Estado atual

- Rodapé (`resources/views/layouts/partials/footer.blade.php`) ainda diz **Vehicle Maintenance** (ícone de chave + texto).
- Navbar já usa lockup Revisalog via `AppStorage::brandUrl('lockup-horizontal.png')` (CDN `cdn.revisalog.com.br`).
- Termos existem em `config/legal.php` (versão `2026-08-21`) e na API `GET /api/v1/legal/terms-of-use`. O app Flutter e o componente Blade `terms-scroll-accept` consomem esse texto.
- **Não há página pública** `/termos` nem `/contato`.
- O mailable do PDF ainda diz “gerado automaticamente pelo Vehicle Maintenance”.
- Notificações Laravel usam `config('app.name')` no rodapé do template padrão.
- Testes `HomePageTest` e `ExampleTest` ainda esperam o texto `Vehicle Maintenance`.

### Implementação

1. **Rodapé** — lockup Revisalog (mesmo padrão da navbar, `AppStorage::brandUrl()`, sem `asset()` em brand). Coluna Legal: Termos, Contato, `suporte@revisalog.com.br`.
2. **`/termos`** — página pública com o texto de `config('legal.terms_of_use')`, reescrito para Revisalog (uma fonte só: config → API + app + web).
3. **`/contato`** — página com o e-mail e, quando o Resend estiver no ar, formulário que envia para `suporte@`.
4. **Marca no e-mail** — atualizar view do PDF mail; `APP_NAME=Revisalog` no código/`.env.example`. `MAIL_MAILER=resend` e a API key **só** no Laravel Cloud.
5. **`/privacidade` (LGPD)** — recomendado no mesmo pacote. O produto guarda e-mail, CRLV, NF-e e histórico do veículo.
6. **Testes** — atualizar asserts antigos; cobrir rotas públicas novas (happy path + formulário de contato quando existir).

Regras: `backend/.ai/rules/views.md` (brand via CDN), `landing-views.md`, `layouts.md`. Mail: queued, `assertQueued()` se o mailable implementar `ShouldQueue`.

---

## 5. Ordem de execução

```
DNS + caixa suporte@ (Cloudflare)          ← em andamento
        →  conta Resend + verificar revisalog.com.br (noreply@)
        →  variáveis MAIL_* / RESEND_API_KEY no Laravel Cloud
        →  código: boas-vindas + aviso de cadastro + marca nos templates
        →  teste real só em produção
```

Nada disso roda na máquina além dos testes automatizados com `Mail::fake()` / mailer `log`. DNS/caixa precisa de acesso ao painel de prod.

Prompt do Composer 2.5: [`prompt-composer-emails-transacionais.md`](prompt-composer-emails-transacionais.md).

---

## 7. Como funciona o `noreply@`

`noreply@revisalog.com.br` **não é uma caixa**. Ninguém entra nela. É só o **remetente** que o Resend usa para o sistema disparar.

| Endereço | Papel |
| --- | --- |
| `noreply@revisalog.com.br` | **From** de tudo que o app envia (boas-vindas, lembrete, PDF, follow-up da oficina, contato interno) |
| `suporte@revisalog.com.br` | **Reply-To** e caixa humana (Cloudflare encaminha para os Gmails). Também **To** dos avisos internos (novo cadastro, formulário `/contato`) |

Fluxo: Laravel Cloud (fila) → Resend → inbox do usuário, com `From: Revisalog <noreply@…>` e `Reply-To: suporte@…`. Se a pessoa clicar em Responder, a resposta cai no Gmail de `suporte@`, não no noreply.

O Resend só envia depois que o domínio `revisalog.com.br` estiver verificado (DKIM no apex + MX/SPF no subdomínio `send`). Sem isso, Gmail/Outlook rejeitam.

Não criar `noreply@` no Email Routing. Não enviar From `suporte@`.

---

## 8. E-mails transacionais a implementar

Já existe: PDF (`VehicleMaintenancePdfMail`), lembrete de km, follow-up da oficina (`WorkshopFollowUpNotification` + templates `{{workshop_name}}` etc.), formulário `/contato` → `ContactMessageMail`.

Falta:

1. **Boas-vindas (e-mail)** no cadastro novo — web, API e OAuth novo. Hoje só tem push FCM na API, texto “Vehicle Maintenance”, e dispara também no **login** (não repetir isso em e-mail).
2. **Aviso interno** para `suporte@` a cada cadastro novo (nome, e-mail, origem web/api/oauth).
3. **Marca Revisalog** nos templates que já saem (MailMessage / PDF). Templates da oficina já funcionam; não recriar o CRUD. Reply-To do follow-up: e-mail da oficina se existir, senão `suporte@`.

Um evento `UserRegistered` (ou `Registered` do Laravel) disparado depois do tenant, com listeners em fila. Não mandar e-mail de “bem-vindo de volta” no login.

---

## 6. Confirmações pendentes

1. **Resend** como provedor de envio (não SendGrid)?
2. **Qual e-mail pessoal** recebe o encaminhamento de `suporte@`?
3. ~~DNS na Cloudflare?~~ **Sim** — NS já são `aspen`/`odin`. Registro.br só titularidade.
4. Incluir **`/privacidade` (LGPD)** agora, ou só termos + contato?

---

## Arquivos-chave

| Área | Caminho |
| --- | --- |
| Mail config | `config/mail.php` |
| Env exemplo | `.env.example` (`MAIL_*`, `APP_NAME`) |
| Termos | `config/legal.php`, `app/Http/Controllers/Api/LegalController.php` |
| Rodapé | `resources/views/layouts/partials/footer.blade.php` |
| Navbar (referência de marca) | `resources/views/layouts/partials/navbar.blade.php` |
| PDF mail | `app/Mail/VehicleMaintenancePdfMail.php`, `resources/views/emails/vehicle-maintenance-pdf.blade.php` |
| Job PDF | `app/Jobs/EmailVehicleMaintenancePdf.php` |
| Notificações | `app/Notifications/MaintenanceKmReminderNotification.php`, `WorkshopFollowUpNotification.php` |
| Testes da home | `tests/Feature/Web/HomePageTest.php`, `tests/Feature/ExampleTest.php` |
