---
paths:
  - 'app/Http/Requests/Api/**'
---

# Requests Api

## País do usuário não aceita nulo
users.country é NOT NULL com padrão Brasil. Cadastro e PUT /api/v1/me gravam Brasil quando o cliente manda o campo vazio ou null. Omitir o campo no update preserva o país atual. Não tornar a coluna nullable.
