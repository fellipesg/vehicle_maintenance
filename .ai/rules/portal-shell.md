---
paths:
  - 'resources/views/layouts/**'
  - 'app/Enums/Portal.php'
---

# Portal Shell

## Portal enum is the shell source
Menu items, profile label, Início route, primary CTA, login route and vehicle route come from App\Enums\Portal (User::portal(), Portal::current() for the area being viewed, Portal::homeFor() for where the account lands). No view or controller runs its own match on user_type; App\Support\PortalAccess delegates home, label and login to Portal. The account menu lives in layouts.partials.account-menu; the admin sidebar renders Portal::Admin->navigationItems() through <x-ui.nav variant="vertical">.

## O teto de itens do menu é largura medida, não gosto
A topbar é uma linha só com `flex-nowrap` (logo · destinos · ação principal · busca · sino · conta).
Em **1024px** — o `lg`, onde ela aparece — sobram **557px** para os destinos, e o texto é Inter 14px.
Medido no limiar: os cinco itens antigos da oficina ("Ordens de serviço" inteiro) ocupavam 590px —
ou seja, **a barra já transbordava 33px em 1024px** e "Minha oficina" passava por cima da área do
botão. Com o rótulo curto "OS" (a palavra que o produto usa em "Nova OS" e "OS registrada") os
**seis** itens de hoje ocupam 574px: ainda 17px além em 1024px, e a faixa com sobreposição encolheu
de ~1024–1057px para ~1024–1041px (a caixa cresce 1:1 com a janela, porque o resto da linha é fixo).
Melhorou; não está resolvido. O conserto de verdade é da casca — deixar os destinos quebrarem linha,
rolarem ou esconderem rótulo entre `lg` e `xl` —, e vale para os quatro portais. Antes de somar item,
meça: rótulo longo custa mais que item. Grupo não é saída para portal, porque a topbar devolve `[]`
quando qualquer entrada tem `items` (os grupos são a sidebar do admin) e o menu ficaria vazio.

`Portal::navigation(?User $user)` recebe a conta para os itens que dependem dela: hoje só Vistorias,
que aparece para quem pode emitir laudo (`App\Support\Inspection\InspectionAccess`) e some para quem
não pode — menu que leva a um 403 é oferta falsa (`.ai/rules/features.md`). `navigationItems()` passa
`$request->user()`, e topbar, menu mobile e paleta de comandos leem da mesma lista.

## Maps are views of a list, not menu items
Admin map screens are not menu entries: the list | map switch lives on the page, and Portal activePatterns make admin.maps.users activate Usuários and admin.maps.workshops activate Oficinas.

## Each layout owns the toaster and the confirm dialog
layouts.app, layouts.admin and layouts.guest include <x-ui.toaster /> and <x-ui.confirm-dialog /> exactly once, at the end of the body before the scripts. The <title> is composed by App\Support\DocumentTitle ("{Página} · {Área} · RevisaLog"); views pass only the page name in @section('title').
