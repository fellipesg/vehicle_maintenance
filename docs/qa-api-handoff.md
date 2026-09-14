# Vehicle Maintenance — QA API Handoff

Guia para testar a API enterprise em produção (Postman, Insomnia, Swagger ou app Flutter).

## Ambiente

| Item | URL |
| --- | --- |
| Base API | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/api/v1` |
| Swagger UI | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/docs/api` |
| Portal web (proprietário) | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/login/usuario` |

### Swagger — HTTP Basic Auth

A documentação interativa exige Basic Auth quando `API_DOCS_USERNAME` e `API_DOCS_PASSWORD` estão configurados no Laravel Cloud.

> **Enviar ao QA por canal privado** (não commitar): usuário e senha definidos nas variáveis de ambiente `API_DOCS_*`.

---

## Conta demo

```
E-mail: fgoncalves2008@gmail.com
Senha:  password123
```

Conta com veículos e manutenções reais para teste de ponta a ponta.

---

## Autenticação (API mobile / Postman / Insomnia)

### 1. Login

```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "fgoncalves2008@gmail.com",
  "password": "password123"
}
```

### 2. Resposta — guardar o token

```json
{
  "success": true,
  "data": {
    "token": "1|....",
    "token_type": "Bearer",
    "user": { }
  }
}
```

### 3. Demais requests — header obrigatório

```http
Authorization: Bearer {token}
Accept: application/json
```

> Tokens Sanctum exigem **abilities** (`vehicles:read`, `maintenances:write`, etc.). O login demo emite token com todas as abilities do app mobile.

---

## Formato das respostas

Todas as respostas JSON seguem o envelope:

```json
{
  "success": true,
  "data": { },
  "message": "...",
  "errors": { },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

Listagens paginadas: `page` (default 1) e `per_page` (default 15, máx. 100).

---

## Endpoints principais

| Área | Método | Rota | Auth |
| --- | --- | --- | --- |
| Perfil | GET | `/me` | Sim |
| Meus veículos | GET | `/my-vehicles?page=1&per_page=15` | Sim |
| Detalhe veículo | GET | `/vehicles/{id}` | Sim |
| Manutenções do veículo | GET | `/vehicles/{id}/maintenances` | Sim |
| Timeline (km) | GET | `/vehicles/{id}/timeline` | Sim |
| Listar manutenções | GET | `/maintenances?page=1&per_page=15` | Sim |
| Detalhe manutenção | GET | `/maintenances/{id}` | Sim |
| Oficinas | GET | `/workshops?search=curitiba` | Sim |
| Busca veículo | GET | `/vehicles/search/{placa_ou_renavam}` | Não |
| Termos de uso | GET | `/legal/terms-of-use` | Não |
| Catálogo marcas | GET | `/vehicle-catalog/brands` | Não |
| Catálogo modelos | GET | `/vehicle-catalog/models` | Não |

---

## PDF assíncrono (fluxo completo)

O PDF **não** é retornado inline no POST. O cliente enfileira, faz poll e baixa quando `status === completed`.

### 1. Enfileirar exportação

```http
POST /api/v1/vehicles/{vehicleId}/export-pdf
Authorization: Bearer {token}
```

**Resposta (202):**

```json
{
  "success": true,
  "data": {
    "export_id": "894f373e-4a26-4fe8-baa3-61394cd409f1",
    "status": "pending",
    "status_url": "/api/v1/vehicle-pdf-exports/894f373e-..."
  },
  "message": "PDF export queued."
}
```

### 2. Poll (a cada ~2 segundos)

```http
GET /api/v1/vehicle-pdf-exports/{exportId}
Authorization: Bearer {token}
```

Status possíveis: `pending` → `processing` → `completed` | `failed`

Quando `completed`, o JSON inclui `filename`, `download_url` (URL assinada temporária) e `download_api_url` (rota autenticada na mesma origem).

### 3. Download (recomendado para browsers)

```http
GET /api/v1/vehicle-pdf-exports/{exportId}/download
Authorization: Bearer {token}
```

Retorna o arquivo PDF com `Content-Disposition: attachment` — força download sem abrir na mesma aba.

> **Alternativa:** `download_url` no JSON de status (URL assinada do storage). Pode abrir inline em alguns browsers; preferir `/download` para QA de UX web.

---

## Erros comuns

| Código | Significado |
| --- | --- |
| 401 | Token ausente, inválido ou expirado |
| 403 | Sem ability Sanctum ou sem permissão no recurso |
| 404 | Recurso não encontrado |
| 422 | Erro de validação — ver `errors` no body |
| 429 | Rate limit (login/register) |

---

## Portal web (smoke test visual)

1. Acessar `/login/usuario` e entrar com a conta demo.
2. **Dashboard** — cards de veículos e manutenções recentes (carregados via API).
3. **Meus Veículos** — listagem e detalhe com timeline/km.
4. **Manutenções** — listagem e detalhe com itens e valores.
5. **Oficinas** — diretório com busca.
6. **Exportar PDF** no detalhe do veículo — deve **baixar** o arquivo e manter a página aberta.

---

## App Flutter

- **API base:** `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/api/v1`
- Mesmo fluxo de login e token Bearer.
- PDF: mesmo fluxo async; download via `/vehicle-pdf-exports/{id}/download`.

---

## Checklist sugerido para o QA

- [ ] Login e logout
- [ ] GET `/me` após login
- [ ] Listar veículos paginados (`meta` + `links`)
- [ ] Detalhe veículo + manutenções + timeline
- [ ] CRUD manutenção (se escopo incluir escrita)
- [ ] Busca pública de veículo por placa
- [ ] Export PDF: POST → poll → download via `/download`
- [ ] Swagger abre com Basic Auth
- [ ] Respostas 403 sem token / token inválido
- [ ] Portal web: dashboard, veículos, manutenções, oficinas, PDF

---

## O que o responsável deve enviar ao QA (privado)

- Credenciais `API_DOCS_USERNAME` / `API_DOCS_PASSWORD` (Swagger)
- Este documento ou link para o repositório (`backend/docs/qa-api-handoff.md`)
