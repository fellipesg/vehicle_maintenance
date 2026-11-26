---
paths:
  - 'app/Support/TenantContext.php'
  - 'app/Http/Middleware/SetTenantContext.php'
---

# Tenant

## O TenantContext não tem leitor: não construa nada em cima dele
`App\Support\TenantContext` é um holder estático (`set`, `setFromUser`, `id`, `clear`, `tenant`) e o
alias `tenant` (`App\Http\Middleware\SetTenantContext`, em `bootstrap/app.php`) roda em três grupos
de rota — `routes/web.php`, `routes/api.php` e `routes/api-two-factor.php` —, preenchendo o estático
no começo da requisição e limpando no `finally`.

Medido em 26/11/2026 com `git grep TenantContext`: os **únicos** usos no repositório são a escrita e
o `clear()` desse middleware. `TenantContext::id()`, `::tenant()` e `::set()` não têm **nenhum**
chamador, não existe global scope algum em `app/`, e todo recorte por inquilino que de fato roda lê
a coluna direto — `$user->tenant_id`, `->where('tenant_id', ...)`, `Vehicle::maintenancesVisibleTo`
e afins, em dezenas de lugares. Ou seja: o middleware está ligado e não produz efeito observável.

Duas armadilhas, nos dois sentidos:

- **Não presuma que existe um contexto de inquilino ambiente.** Escrever
  `TenantContext::id()` num service ou policy "porque o middleware já preencheu" compila, passa no
  teste que roda pela rota e devolve `null` em qualquer caminho sem HTTP — comando de console,
  job na fila, `php artisan tinker` — porque lá o middleware não roda. Pior: `tenant()` faria um
  `Tenant::find()` por chamada. Para recortar por inquilino, leia `$user->tenant_id`, como o resto
  do código faz.
- **Não trate o estático como garantia de isolamento.** Ele é por processo, não por requisição; o
  `clear()` no `finally` é o que impede vazar de uma requisição para a próxima num worker
  persistente (Octane, por exemplo). Quem resolver dar leitores a ele herda esse cuidado.

Se a escolha for remover em vez de dar propósito: a classe, o middleware, o alias em
`bootstrap/app.php` e o `'tenant'` dos três grupos de rota saem juntos — e nenhum deles aparece em
`route:list`, então a conferência é a suíte verde, não a contagem de rotas.
