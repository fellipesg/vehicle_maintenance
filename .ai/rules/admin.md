---
paths:
  - 'app/Http/Controllers/Web/Admin/**'
---

# Admin

## Admin web fleet scope
Admin web list endpoints (vehicles, maintenances, workshops) must not scope by tenant or current owner; they show platform-wide data. Maps expose only id, name, lat, lng, city, and a short label in JSON—no email, document, or phone in browser payloads.
