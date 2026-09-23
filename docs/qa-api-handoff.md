# Revisalog — QA API Handoff

Guia para testar a API em produção (Postman, Insomnia, Swagger ou app Flutter).

## Ambiente

| Item | URL |
| --- | --- |
| Base API | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/api/v1` |
| Swagger UI | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/docs/api` |
| Portal web (proprietário) | `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/login/usuario` |
| Site público | `https://revisalog.com.br` — `/termos`, `/privacidade`, `/contato` |

Ambiente local (Docker): `make install` e depois `http://localhost:8080/api/v1`.

### Swagger — HTTP Basic Auth

A documentação interativa exige Basic Auth quando `API_DOCS_USERNAME` e `API_DOCS_PASSWORD` estão configurados no Laravel Cloud. Em produção, sem essas variáveis a rota responde **404** (docs fechadas por padrão); `API_DOCS_ENABLED=false` também devolve 404.

> **Enviar ao QA por canal privado** (não commitar): usuário e senha definidos nas variáveis de ambiente `API_DOCS_*`.

---

## Conta demo

> **Enviar ao QA por canal privado** (não commitar): e-mail e senha da conta demo.

Conta com veículos e manutenções para teste de ponta a ponta.

---

## Autenticação (API mobile / Postman / Insomnia)

### 1. Login

```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "<email-da-conta-demo>",
  "password": "<senha-da-conta-demo>"
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

> Tokens Sanctum exigem **abilities** (`vehicles:read`, `maintenances:write`, `profile:write`, `invoices:write`, `workshops:read/write`, `fcm:write`). O login do app emite token com todas as abilities do app mobile. Faltando a ability, a resposta é **403**.

### Verificação em duas etapas (quando a conta tem 2FA)

Se a conta tiver 2FA ativo, o `POST /login` devolve um `challenge_token` em vez do token de acesso. Troque-o por token em:

```http
POST /api/v1/two-factor/challenge
Content-Type: application/json

{ "challenge_token": "...", "code": "123456" }
```

Também existem, autenticados: `POST /two-factor/enable`, `/confirm`, `/disable` e `/recovery-codes`.
Limite: 5 tentativas por challenge por minuto, mais um teto por IP.

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
| Atualizar perfil | PUT | `/me` | Sim |
| Excluir conta | DELETE | `/me` | Sim |
| Meus veículos | GET | `/my-vehicles?page=1&per_page=15` | Sim |
| Detalhe veículo | GET | `/vehicles/{id}` | Sim |
| Placas do veículo | GET | `/vehicles/{id}/plates` | Sim |
| Manutenções do veículo | GET | `/vehicles/{id}/maintenances` | Sim |
| Timeline (km) | GET | `/vehicles/{id}/timeline` | Sim |
| Listar manutenções | GET | `/maintenances?page=1&per_page=15` | Sim |
| Detalhe manutenção | GET | `/maintenances/{id}` | Sim |
| Fotos da manutenção | POST/DELETE | `/maintenances/{id}/photos[/{photo}]` | Sim |
| Oficinas | GET | `/workshops?search=curitiba` | Não |
| Busca veículo | GET | `/vehicles/search/{placa_renavam_ou_chassi}` | Não |
| Termos de uso | GET | `/legal/terms-of-use` | Não |
| Política de privacidade | GET | `/legal/privacy-policy` | Não |
| Catálogo marcas | GET | `/vehicle-catalog/brands` | Não |
| Catálogo modelos | GET | `/vehicle-catalog/models` | Não |

Lista completa e sempre atualizada: `/docs/api` (Swagger) ou, no repositório, `php artisan route:list --except-vendor --path=api`.

### `GET /my-vehicles` responde com ETag

A lista manda `ETag`; reenviando `If-None-Match` com o mesmo valor a resposta é **304** sem corpo. O app usa isso para revalidar o cache — não é erro.

### `DELETE /me` — exclusão de conta

Anonimiza o usuário (dados pessoais, tokens Sanctum e tokens FCM), desvincula os veículos **daquele** usuário e **mantém** as manutenções no chassi. Depois da chamada:

- o token usado deixa de valer (401 nas próximas chamadas);
- o histórico do veículo continua visível pela busca pública, sem os dados pessoais de quem cadastrou.

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
| 304 | `If-None-Match` igual ao ETag em `/my-vehicles` — cache válido, sem corpo |
| 401 | Token ausente, inválido ou expirado |
| 403 | Sem ability Sanctum ou sem permissão no recurso (veículo/manutenção de outro dono) |
| 404 | Recurso não encontrado (ou `/docs/api` sem `API_DOCS_*` em produção) |
| 422 | Erro de validação — ver `errors` no body |
| 429 | Rate limit: login/registro/callback OAuth, busca pública (20/min por IP), uploads (10/min), API autenticada (60/min) |

---

## Validação de quilometragem

A km de uma manutenção é validada **pela data do serviço**, não só pelo maior valor do veículo:

- **Piso**: maior km já registrado até aquela data (e o hodômetro do cadastro, quando o serviço é posterior ao cadastro).
- **Teto**: menor km de um registro com data posterior.
- Fora da faixa → **422** com a mensagem em `errors.kilometers`.

Casos a testar: manutenção retroativa com km menor que o atual (deve passar), manutenção nova com km menor que a anterior (422), manutenção intercalada com km acima da seguinte (422).

---

## Portal web (smoke test visual)

1. Acessar `/login/usuario` e entrar com a conta de teste.
2. **Dashboard** — cards de veículos e manutenções recentes.
3. **Meus Veículos** — listagem e detalhe com timeline/km.
4. **Importar CRLV-e** (`/usuario/veiculos/importar-crlv`) — o PDF vai para uma **tela de conferência** (`.../importar-crlv/preview`) com marca/modelo/placa/chassi lidos; só depois de confirmar o veículo é salvo. Mesmo fluxo em "Vincular" e no portal do lojista (`/garagem/estoque/...`).
5. **Manutenções** — listagem e detalhe com itens e valores; criar uma manutenção exercita a validação de km por data.
6. **Oficinas** — diretório com busca.
7. **Exportar PDF** no detalhe do veículo — deve **baixar** o arquivo e manter a página aberta.
8. **Autorização** — trocar o id na URL (veículo/manutenção de outro usuário) deve dar **403/404**, nunca mostrar o recurso.

### Páginas públicas

- `/termos` e `/privacidade` — texto vem de `config/legal.php` (versão no rodapé da página).
- `/contato` — envia para `suporte@revisalog.com.br`. Tem honeypot e throttle (5 envios por minuto por IP); o captcha Turnstile só aparece quando `TURNSTILE_SITE_KEY` está configurado. Indisponibilidade do Turnstile vira erro de validação na própria página, nunca 500.

> CRLV-e que o parser não consegue ler gera aviso no Sentry **e** e-mail para o suporte — se o QA encontrar um layout de DETRAN não lido, anexe o PDF no report.

---

## App Flutter

- **API base:** `https://vehicle-maintenance-production-l6pnoo.laravel.cloud/api/v1`
- Mesmo fluxo de login e token Bearer.
- PDF: mesmo fluxo async; download via `/vehicle-pdf-exports/{id}/download`.

---

## Checklist sugerido para o QA

- [ ] Login e logout (e challenge 2FA, se a conta tiver 2FA)
- [ ] GET `/me` após login
- [ ] Listar veículos paginados (`meta` + `links`) e revalidação com `If-None-Match` → 304
- [ ] Detalhe veículo + manutenções + timeline
- [ ] CRUD manutenção (se escopo incluir escrita) + validação de km por data (422)
- [ ] Busca pública de veículo por placa, RENAVAM e chassi
- [ ] Export PDF: POST → poll → download via `/download`
- [ ] Swagger abre com Basic Auth
- [ ] Respostas 401 sem token / token inválido e 403 em recurso de outro usuário
- [ ] `DELETE /me` em conta descartável: token invalidado e histórico do chassi preservado
- [ ] Portal web: dashboard, veículos, manutenções, oficinas, PDF, import CRLV-e com preview
- [ ] Páginas públicas: `/termos`, `/privacidade`, `/contato` (envio + honeypot)

---

## O que o responsável deve enviar ao QA (privado)

- Credenciais `API_DOCS_USERNAME` / `API_DOCS_PASSWORD` (Swagger)
- E-mail e senha de uma conta de teste com veículos e manutenções
- Este documento ou link para o repositório (`docs/qa-api-handoff.md`)
