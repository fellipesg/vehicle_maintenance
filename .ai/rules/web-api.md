---
paths:
  - 'app/Http/Controllers/{Web,Api}/AuthController.php'
---

# Web Api

## Login portals include oficina
Web and API login portals are admin, lojista, usuario, and oficina. Workshop users match oficina and redirect to workshop.dashboard. Dev accounts live in DevPortalUsersSeeder (local/testing only).

## Lojista signs up through a social provider, not through e-mail and password
The two sign-up paths differ on purpose, so do not "fix" one to match the other without deciding the product question first.

`RegisterRequest` (POST /api/v1/register and the web form) stays user-only: it rejects `user_type` of `garage` or `workshop` with "Public registration is only available for vehicle owners", and `AuthController::register` writes `user_type = 'user'` regardless of what was sent.

Native social login does create a store account: `loginWithGoogle` and `loginWithApple` pass `userType: $portal === 'lojista' ? 'garage' : null` to `findOrCreateSocialUser`, and only `admin` and `oficina` are refused for an account that does not exist yet. So a lojista account is born verified by Google or Apple, which is the reason the asymmetry is acceptable — an e-mail nobody verified is a weaker basis for a store.

The app matches this: `LoginPortal.canRegister` is true only for `usuario`, so the lojista portal shows the social buttons and hides "Cadastre sua loja". Reactivating that button needs `RegisterRequest` to accept `garage` first, which reopens store verification and onboarding.
