---
paths:
  - 'app/Features/**,app/Support/Maintenance/MaintenancePhotoAccess.php,app/Http/Controllers/Web/Admin/MaintenancePhotosFeatureController.php,app/Http/Requests/Web/Admin/**,resources/views/components/admin/maintenance-photos-toggle.blade.php,config/pennant.php'
---

# Feature flags (Pennant)

## Pennant é o mecanismo das funcionalidades ligadas por conta
Funcionalidade que o admin liga conta a conta é uma classe em `app/Features` (laravel/pennant, driver `database`, tabela `features`), com `resolve()` devolvendo `false`: **desligada por padrão**, ninguém ganha por existir. Não usamos LaunchDarkly. A próxima flag segue o mesmo caminho: classe em `app/Features`, uma classe de acesso em `app/Support` que responde "esta conta tem?" num lugar só, rota admin com FormRequest e o resto do código perguntando à classe de acesso, nunca ao Pennant direto. Em teste, `Feature::for($escopo)->activate(Classe::class)`; os testes que exercitam o recurso em si, e não a liberação, chamam `enableMaintenancePhotosForEveryone()` (tests/TestCase.php) no setUp.

## Fotos das manutenções (App\Features\MaintenancePhotos)
Envio, visualização e fotos no PDF do histórico. O admin liga em um destes escopos, e a conta tem o recurso se **qualquer um** estiver ligado: o próprio usuário, a oficina dele (`Workshop`) ou a loja dele (`Garage`). Oficina e loja têm uma linha por conta (`user_id`), então ligar a oficina equivale a ligar a conta; os três escopos existem para o admin usar a tela que preferir e para o dia em que oficina ou loja tiver mais de um usuário. `App\Support\Maintenance\MaintenancePhotoAccess::allows(?User)` é a única resposta (visitante = não). Policy, resources, telas, PDF e API perguntam a ela; `isActiveFor($model)` é o valor de um escopo só (o que a tela admin mostra), `set()` grava e `preload()` evita uma consulta por linha nas listas do admin.

O Pennant grava a linha `features` (nome `maintenance-photos`, escopo `Model|id`) na primeira consulta, com `false`. Desligar não apaga foto nenhuma: elas ficam guardadas e voltam quando o recurso for religado. Quem mudou fica no log (`admin_id`, `scope_type`, `scope_id`, `enabled`); a tabela guarda só o estado atual.

Admin: `PUT admin/usuarios/{user}|oficinas/{workshop}|lojas/{garage}/fotos-manutencao` (`MaintenancePhotosFeatureController`, `UpdateMaintenancePhotosFeatureRequest`, `enabled` booleano, só `is_admin`). Interruptor `<x-admin.maintenance-photos-toggle>` no detalhe do usuário (conta, oficina e loja vinculadas) e nos cartões de loja; selo "Fotos" na lista de usuários e coluna na de oficinas. Onde o recurso é aplicado: ver `policies.md`, `api.md`, `pdfs.md` e `views.md`. A busca pública por placa e o cartão público de procedência são exceção documentada e seguem mostrando as fotos públicas (veículo, depois), com ou sem o recurso.
