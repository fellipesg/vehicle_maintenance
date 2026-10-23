---
paths:
  - 'app/Http/Requests/Api/**'
---

# Requests Api

## País do usuário não aceita nulo
users.country é NOT NULL com padrão Brasil. Cadastro e PUT /api/v1/me gravam Brasil quando o cliente manda o campo vazio ou null. Omitir o campo no update preserva o país atual. Não tornar a coluna nullable.

## Requests de registros de oficinas
LookupWorkshopVehicleRequest, StoreWorkshopVehicleRequest, OwnerDecisionRequest, InviteEmailRequest e InviteWhatsappRequest. Chassi é normalizado antes de validar e tem 17 caracteres sem I, O ou Q. StoreVehicleRequest aceita o chassi de um veículo criado pela oficina (sem dono, placa e RENAVAM) em vez de recusar como duplicado.

## Endereço em PUT /api/v1/me é do proprietário
Os campos de endereço deste request servem só a conta de proprietário; loja e oficina têm o endereço
nos cadastros delas. Antes de tirá-los daqui, leia "Endereço é da empresa, não da conta" em
.ai/rules/api.md: a ordem é app primeiro, backend depois.

## export_include_photos em PUT /api/v1/me
UpdateProfileRequest valida `export_include_photos` como `sometimes|boolean` (sem nullable: só o servidor cria o estado nulo). Não torne `name` obrigatório de novo: o app manda só este campo.
