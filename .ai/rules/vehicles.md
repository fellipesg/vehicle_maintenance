---
paths:
  - 'frontend/lib/views/vehicles/**'
---

# Vehicles

> Flutter app (`vehicle_maintenance_frontend`), not this repository. Paths are relative to a sibling `frontend/` checkout. The Blade equivalents live in `.ai/rules/user-vehicles.md`.

## Dual vehicle covers (landscape + portrait)
Cadastro pede capa paisagem (celular deitado, 16:9) e retrato (celular em pé, 9:16). Hero largo / desktop usa paisagem; telas <768px, avatares e PDF usam retrato. Sempre cropper antes do upload. Cancelar mantém a foto anterior daquela orientação.

## Open vehicle history PDF in-app
After the history PDF is saved, push PdfViewerPage (printing PdfPreview). Do not use url_launcher + Uri.file — iOS simulator cannotLaunch file:// and only shows the snackbar path. OpenFilex is for invoice files; the vehicle history export opens in-app.

## PDF export snackbar must be cancellable
The Gerando PDF snackbar must include a Cancelar action that increments the export generation and stops polling/opening the viewer. Do not use a 5-minute snackbar without an action — iOS users cannot dismiss it.

## Brand and model come from the catalog, in both clients
Lista fechada nos dois: o portal web usa `<select>` (user/vehicles/_form) e o app usa CatalogAutocomplete sobre GET /api/v1/vehicle-catalog/brands e /models. O app busca em vez de rolar, porque são vinte e tantas marcas e os modelos de uma marca grande passam disso, mas o contrato é o mesmo do `<select>` — nada fora do catálogo. O que falta entra pelo /admin/marcas e /admin/modelos, que existem para isso; não libere texto livre para contornar catálogo incompleto.

O campo de modelo fica desabilitado enquanto não há marca, e trocar a marca descarta o modelo escolhido para a anterior (o `fillModels` do web repovoa o select pelo mesmo motivo).

O endpoint de modelos resolve a marca por chave exata (`VehicleCatalogService::all()[$brand]`), então mande o nome vindo do catálogo, não o que o usuário digitou — "fiat" não acha os modelos de "Fiat".

Dois escapes, iguais nos dois clientes:
- Valor legado continua válido na edição. O catálogo pode ter perdido uma marca ou modelo que algum veículo já usa; o web injeta o valor atual na lista (`unshift`) e o app faz o mesmo, senão a edição fica impossível.
- Catálogo vazio desliga a checagem **no app**: sem lista não há contra o que validar, e travar o cadastro por falha de rede seria pior. O web não tem esse escape — com o catálogo vazio ele renderiza um select só com o placeholder e `required`, e o formulário fica impossível de enviar.

Catálogo vazio não é hipótese: os dados vêm de `VehicleCatalogSeeder` (lê `database/data/vehicle_catalog.php`), e fora dos testes nada o executa — nem o `DatabaseSeeder`, nem o `make install`. Ambiente local novo nasce sem marca nenhuma. Rode `php artisan db:seed --class=VehicleCatalogSeeder`; ele é idempotente e já limpa o cache `vehicle_catalog` (TTL 1h) no fim.

## Veículo sem placa
license_plate e renavam podem ser null no app: mostrar "Placa não informada" nas telas privadas. Na oficina, a busca é pelo chassi de 17 caracteres e, com has_owner true, o app não mostra marca/modelo/ano (a API nem manda).

## Oficina usa só o site, por enquanto
O app não tem portal de oficina no login, então o código de oficina do PR #17 (OS por chassi, "Avisar o cliente") fica sem ponto de entrada no app até existirem um portal de oficina e uma lista "Minhas OS" no app. Não apague esse código: as telas continuam só na web (`/oficina`).
