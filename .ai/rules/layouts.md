---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## Admin navbar on admin.* routes
When the authenticated user is admin and the route name matches admin.*, render only nav-admin (no user/garage/workshop portal links such as Meus Veículos). Portal logo link should point to admin.dashboard on those routes.
