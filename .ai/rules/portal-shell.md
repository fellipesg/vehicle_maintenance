---
paths:
  - 'resources/views/layouts/**'
  - 'app/Enums/Portal.php'
---

# Portal Shell

## Portal enum is the shell source
Menu items, profile label, Início route, primary CTA, login route and vehicle route come from App\Enums\Portal (User::portal(), Portal::current() for the area being viewed, Portal::homeFor() for where the account lands). No view or controller runs its own match on user_type; App\Support\PortalAccess delegates home, label and login to Portal. The account menu lives in layouts.partials.account-menu; the admin sidebar renders Portal::Admin->navigationItems() through <x-ui.nav variant="vertical">.

## Maps are views of a list, not menu items
Admin map screens are not menu entries: the list | map switch lives on the page, and Portal activePatterns make admin.maps.users activate Usuários and admin.maps.workshops activate Oficinas.

## Each layout owns the toaster and the confirm dialog
layouts.app, layouts.admin and layouts.guest include <x-ui.toaster /> and <x-ui.confirm-dialog /> exactly once, at the end of the body before the scripts. The <title> is composed by App\Support\DocumentTitle ("{Página} · {Área} · RevisaLog"); views pass only the page name in @section('title').
