---
paths:
  - 'app/Http/Controllers/Web/Admin/**'
---

# Admin

## Admin web fleet scope
Admin web list endpoints (vehicles, maintenances, workshops) must not scope by tenant or current owner; they show platform-wide data. Maps expose only id, name, lat, lng, city, and a short label in JSON—no email, document, or phone in browser payloads.

## One error bag per form on pages with dialogs
When a page has more than one form for the same fields (Nova marca/Editar marca, categories, models), validate with $request->validateWithBag(self::CREATE_BAG | updateBag($model), ...) so the error and old input come back only in the dialog that was sent (see views-admin.md).

## Recursos por conta: interruptores no admin
Liga/desliga de recurso por conta (hoje, as fotos das manutenções) é `MaintenancePhotosFeatureController`: um PUT por escopo (usuário, oficina, loja) com `UpdateMaintenancePhotosFeatureRequest`, log de quem mudou e `back()` com aviso. Valores lidos por MaintenancePhotoAccess::isActiveFor / preload (nada de uma consulta por linha); ver features.md.
