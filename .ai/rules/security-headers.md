---
paths:
  - 'config/security.php'
  - 'app/Http/Middleware/SetSecurityHeaders.php'
  - 'app/Support/Security/**'
  - 'app/Http/Controllers/Web/CspReportController.php'
  - 'resources/views/layouts/**'
  - 'resources/views/components/analytics.blade.php'
  - 'resources/views/components/adsense.blade.php'
  - 'resources/views/components/notification-bell.blade.php'
  - 'resources/views/components/terms-scroll-accept.blade.php'
---

# Cabeçalhos de segurança e CSP

## Script inline leva `nonce="@cspNonce"`, nunca `'unsafe-inline'`

`SetSecurityHeaders` gera um nonce por pedido **antes** da view renderizar
(`ContentSecurityPolicy::useNonce()`, que guarda o valor em `Vite::useCspNonce`) e manda o
Content-Security-Policy nas respostas HTML. Todo `<script>` inline de Blade sai com
`nonce="@cspNonce"` — a diretiva está em `AppServiceProvider` e imprime o nonce do pedido, ou vazio
fora de um pedido web. Bloco inline novo entra com o atributo; `'unsafe-inline'` em `script-src`
devolveria quase toda a proteção e não entra em nenhuma hipótese.

Os oito blocos inline de hoje: `layouts/partials/shell-script`, `layouts/admin` (dois),
`components/{analytics,adsense,notification-bell,terms-scroll-accept}` e `user/vehicles/_form`.

## `'strict-dynamic'`: tag externa estática **também** precisa do nonce

A política é `'nonce-…' 'strict-dynamic'`. Nos navegadores atuais o `'strict-dynamic'` **desliga a
lista de hosts** do `script-src`: quem carrega é o nonce, e script que um script confiável injeta
herda a confiança. É isso que deixa o gtag.js e o adsbygoogle.js puxarem os próprios arquivos de
vários domínios do Google sem enumerar nenhum — medido: a página do blog termina com 3 scripts
Google no DOM, mais que as 2 tags escritas na view.

A armadilha é a consequência: `<script src="https://…">` escrito na view **não** passa pela lista de
hosts, então precisa do nonce como o inline. Os quatro de hoje já têm — gtag.js, adsbygoogle.js,
leaflet.js (`admin/maps/{users,workshops}`) e o api.js do Turnstile (`legal/contact`). Tag externa
nova entra com `nonce="@cspNonce"`, ou o navegador recusa.

Os hosts continuam listados em `script-src` só como degrau para navegador que entende nonce e ignora
`'strict-dynamic'` (Safari < 15.4): lá a lista é que vale. Não os remova por parecerem inúteis.

As tags do `@vite` recebem o nonce sozinhas, porque o valor mora no `Vite`.

## Bloco de dados não executa e não leva nonce

`<script type="application/json">` (pinos do mapa do admin) e `<script type="application/ld+json">`
(`components/seo/json-ld`) não são executados, então o `script-src` não se aplica a eles — medido com
a política em modo de bloqueio: mapa e JSON-LD na tela, nenhuma violação no console. Não saia
colocando nonce neles.

## O middleware nunca sobrescreve um CSP que a resposta já traz

`InspectionReportController`, `InvoiceDownloadController` e `MaintenancePhotoFileResponse` mandam o
próprio CSP, bem mais apertado: `default-src 'none'; style-src 'unsafe-inline'; sandbox`. O
middleware sai fora quando o cabeçalho já existe, e só mexe em resposta `text/html` — um `set()`
cego trocaria aquela política pela da página, que libera script com nonce, e o arquivo enviado por
terceiro voltaria a ter alcance dentro da origem do site. Resposta JSON da API não recebe CSP de
página.

## Duas políticas: anúncio e portal não cabem na mesma

Páginas com AdSense (`GoogleAdsense::isActiveOnCurrentPage()`, mesma fonte que decide o anúncio —
não duplique a lista de rotas) liberam `img-src`, `frame-src` e `connect-src` para `https:`, porque
criativo e iframe de anúncio vêm de domínio que não se enumera. Todo o resto — e todo portal
autenticado, onde estão chassi, RENAVAM, documento do proprietário e nota fiscal — fica na lista
fechada. Mesmo no perfil do anúncio o `script-src` continua preso ao nonce, que é o vetor que
interessa.

## Report-Only primeiro; ligar o bloqueio é uma linha

`security.csp.enforce` (env `CSP_ENFORCE`) escolhe entre
`Content-Security-Policy-Report-Only` — o navegador relata e não bloqueia — e
`Content-Security-Policy`. Nasce em Report-Only de propósito: um CSP que quebra os portais é pior que
nenhum. As violações vão para `report-uri`/`report-to`, por padrão a rota `csp-report`
(`CspReportController`), que grava no log do Laravel e descarta ruído de extensão de navegador
(`chrome-extension:` e parentes). A rota aceita os dois formatos — o objeto `csp-report` do
`report-uri` e a lista da Reporting API do `report-to`, que renomeou as chaves —, está fora do CSRF e
tem balde largo (`csp-report`, 120/min por IP). `CSP_REPORT_URI` aponta para outro destino, como o
endpoint de segurança do Sentry, que agrupa e aplica cota.

`security.csp.extra_hosts` libera uma origem nova por diretiva sem mexer em código.

## Duas coisas que parecem defeito e não são

`upgrade-insecure-requests` entra só quando o pedido é https: em http local ele transformaria
`http://localhost:5173` em https e derrubaria o `npm run dev`.

Em local com `APP_DEBUG=true` o **Debugbar** viola a política — ele injeta script inline e o
`Sfdump` do VarDumper, e não há onde pôr nonce em pacote de terceiro. É ferramenta de
desenvolvimento, `require-dev`, que não existe em produção. Em Report-Only isso é só barulho no
console; para conferir a política em modo de bloqueio no local, suba com `DEBUGBAR_ENABLED=false`.

## Ao mexer, confira no navegador

Os testes (`tests/Feature/ContentSecurityPolicyTest.php`) travam o cabeçalho, o nonce por pedido, os
dois perfis e o CSP dos downloads, mas não provam que a tela funciona. Com `CSP_ENFORCE=true` e
`DEBUGBAR_ENABLED=false`, o console do navegador é o juiz: home, blog com anúncio, login, os quatro
dashboards, o formulário de veículo, os mapas do admin e uma página com Turnstile. Confirme que o
terceiro **executou** (`window.turnstile`, `window.adsbygoogle`, `window.L`, tiles do mapa
carregados), não só que o console está limpo — script bloqueado e script ausente deixam o mesmo
silêncio.
