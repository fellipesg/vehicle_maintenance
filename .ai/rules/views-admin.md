---
paths:
  - 'resources/views/layouts/**,resources/views/admin/**'
---

# Views Admin

## Admin shell uses layouts.admin sidebar
All admin.* Blade views must @extends('layouts.admin') with vertical nav in layouts/partials/nav-admin.blade.php (sidebar 240px + topbar). Never use layouts.app horizontal navbar for admin routes.
