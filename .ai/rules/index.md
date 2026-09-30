# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

Paths starting with `frontend/` are **not in this repository**. They belong to the Flutter app
([vehicle_maintenance_frontend](https://github.com/fellipesg/vehicle_maintenance_frontend)), which some
setups check out as a sibling `frontend/` directory. Those rules are kept here because the two clients
must agree on the same contract; they are listed in a separate table below.

## This repository (Laravel)

| Applies to | Rule file |
| --- | --- |
| resources/views/admin/maintenances/**,resources/views/admin/vehicles/show.blade.php | .ai/rules/admin-vehicles.md |
| app/Http/Controllers/Web/Admin/** | .ai/rules/admin.md |
| app/Http/Controllers/Api/**, app/Http/Controllers/Api/VehicleController.php | .ai/rules/api.md |
| app/Http/Controllers/Web/BlogController.php,app/Http/Controllers/Web/Admin/BlogPostController.php,app/Models/BlogPost.php,resources/views/blog/**,resources/views/admin/blog/** | .ai/rules/blog.md |
| resources/views/components/vehicle-cover.blade.php | .ai/rules/components.md |
| config/legal.php, config/mail.php | .ai/rules/config.md |
| app/Jobs/** | .ai/rules/jobs.md |
| resources/js/user-portal.js | .ai/rules/js.md |
| resources/views/components/landing/**,resources/views/home.blade.php | .ai/rules/landing-views.md |
| resources/views/layouts/** | .ai/rules/layouts.md |
| app/Listeners/** | .ai/rules/listeners.md |
| app/Mail/** | .ai/rules/mail.md |
| app/Models/Vehicle.php,app/Support/VehiclePlateSearch.php,database/migrations/*vehicle_plates* | .ai/rules/migrations.md |
| app/Services/Maintenance/MaintenanceVerificationStamper.php,app/Models/Maintenance.php | .ai/rules/models.md |
| app/Notifications/** | .ai/rules/notifications.md |
| resources/views/pdfs/vehicle_maintenance_export.blade.php | .ai/rules/pdfs.md |
| scripts/crop-brand-assets.php | .ai/rules/scripts.md |
| app/Support/DemoWorkshopLogoGenerator.php | .ai/rules/support.md |
| resources/css/provenance.css (mirrored in the Flutter `provenance.dart`) | .ai/rules/theme.md |
| resources/views/user/vehicles/** (mirrored in the Flutter vehicle views) | .ai/rules/user-vehicles.md |
| app/Services/User/** | .ai/rules/user.md |
| resources/views/{user,garage,public}/**/*.blade.php | .ai/rules/usergaragepublic.md |
| app/Services/Vehicle/VehicleMaintenancePdfExporter.php, app/Services/Vehicle/VehicleCoverService.php | .ai/rules/vehicle.md |
| resources/views/vendor/mail/** | .ai/rules/vendor-mail.md |
| resources/views/layouts/**,resources/views/admin/** | .ai/rules/views-admin.md |
| resources/views/**, resources/views/home.blade.php | .ai/rules/views.md |
| app/Http/Controllers/{Web,Api}/AuthController.php | .ai/rules/web-api.md |

## Sibling Flutter repo (`frontend/`, not checked out here)

Read these only when working on the mobile app, or when a change here alters a contract the app mirrors
(provenance tokens and wording, vehicle covers, `/my-vehicles` caching).

| Applies to (Flutter repo) | Rule file |
| --- | --- |
| frontend/lib/models/maintenance_item.dart | .ai/rules/lib-models.md |
| frontend/lib/widgets/provenance/** | .ai/rules/provenance.md |
| frontend/lib/repositories/vehicle_repository.dart | .ai/rules/repositories.md |
| frontend/lib/views/vehicles/** | .ai/rules/vehicles.md |
| frontend/lib/theme/provenance.dart | .ai/rules/theme.md (shared with `resources/css/provenance.css`) |
