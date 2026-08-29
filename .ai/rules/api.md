---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## Enterprise API patterns
Use ApiResponse for all JSON responses with success/data/message/errors/meta/links envelope. Use Eloquent API Resources in app/Http/Resources/Api/V1/ for explicit field exposure. Use Form Requests in app/Http/Requests/Api/V1/ instead of inline Validator. Paginate all list endpoints (per_page default 15, max 100). Enforce Sanctum abilities on protected routes via ability middleware; session-authenticated web clients bypass ability checks.
