---
paths:
  - 'bootstrap/app.php'
  - 'app/Support/PortalAccess.php'
  - 'app/Support/FriendlyHttpErrors.php'
  - 'app/Http/Controllers/Web/Auth/**'
  - 'app/Http/Controllers/Web/AuthController.php'
---

# Portal Access

## Wrong area redirects to the account's Início
A web 403 on another profile's area (/usuario, /garagem, /oficina, /admin) becomes a redirect to the account's Início with a notice, done in bootstrap/app.php through App\Support\FriendlyHttpErrors (not in the middleware). A 403 inside the account's own area keeps the pt-BR error page. 419 on login, sign-up, password, contact and logout forms returns to the form with a friendly message. The API keeps answering JSON.

## url.intended only inside the account's area
After login, url.intended is followed only when it points to this host and to a path the account can open (App\Support\PortalAccess::allowsIntendedUrl); otherwise the login goes to the Início. Guests opening a protected link go to the login of the portal that serves it. Admin accounts land on admin.dashboard (Portal::homeFor), while Portal::current() only shows the Admin area on admin.* routes.
