---
paths:
  - 'app/Models/Identity.php'
  - 'app/Models/User.php'
  - 'app/Http/Controllers/Web/AuthController.php'
  - 'app/Http/Controllers/Web/Auth/PasswordResetController.php'
  - 'app/Http/Controllers/Api/PasswordController.php'
  - 'app/Http/Controllers/Api/TwoFactorController.php'
  - 'app/Services/TwoFactorService.php'
  - 'app/Services/User/DeleteUserAccount.php'
  - 'app/Rules/AvailableForUserType.php'
  - 'app/Rules/MatchesIdentityPassword.php'
  - 'config/auth.php'
  - 'database/factories/UserFactory.php'
  - 'database/factories/IdentityFactory.php'
---

# Identity

## Um e-mail, uma senha — e uma conta por papel
`identities` guarda **e-mail, senha e 2FA**. Cada papel (proprietário, lojista, oficina) continua
sendo sua própria linha de `users`, com o `tenant_id` dela, a oficina dela, a loja dela. As linhas
que compartilham `identity_id` são **contas irmãs** da mesma pessoa, uma por papel
(`unique(identity_id, user_type)`).

A arquitetura foi escolhida depois de medir a alternativa. Transformar `user_type` numa tabela de
papéis dentro de **uma** conta exigiria que `users.tenant_id` passasse a significar "o tenant do
papel ativo", e dali sai toda a autorização: 168 referências em 74 arquivos, as 5 policies comparando
`$model->tenant_id === $user->tenant_id`, `User::currentVehicles()`, `stockVehicles()` e a chave de
cache do `EtagForVehicleList`. N papéis = N tenants, e cada uma dessas referências mudaria de
sentido. **Nada disso mudou**: nenhuma policy, nenhum uso de `tenant_id`, nenhuma das chamadas de
`isUser()/isGarage()/isWorkshop()`, nenhum grupo de rota `user.type:` e nenhuma decisão de
`app/Enums/Portal.php` ou `app/Support/PortalAccess.php` (o único toque no `Portal` foi trocar duas
leituras de relação por `loadMissing`, sem mudar o que ele responde). Só mudou a porta de entrada. Se
uma mudança sua nesta área começar a pedir alteração em policy, pare e reveja o desenho: a
propriedade se quebrou.

O guard `web` continua no provider `users` e devolve um `App\Models\User` — é ele que as policies
comparam. A identidade entra só no login e no broker de senha.

## `users.email` é cópia desnormalizada; a fonte é `identities.email`
O e-mail ficou nas duas tabelas de propósito: o `EloquentUserProvider` do guard consulta `users` por
e-mail, há 10 buscas por endereço em `app/` e toda tela e e-mail lê `users.email`. O unique global de
`users.email` saiu (quem faz esse papel é o unique de `identities.email`), e sobrou um índice comum.

Quem troca o e-mail usa **`Identity::changeEmailTo()`**, que grava na identidade e nas contas irmãs
na mesma transação. Gravar numa ponta só é como uma conta irmã deixa de entrar.

## A senha é escrita pela conta e gravada na identidade
`$user->password` lê da identidade e `$user->password = ...` grava **nela** — não existe mais coluna
`password` em `users`. O mesmo vale para `two_factor_secret`, `two_factor_confirmed_at` e
`two_factor_recovery_codes`. Por isso `Hash::check($x, $user->password)`,
`current_password:web`, o `AuthenticateWebSession` (que compara `getAuthPassword()`) e os cadastros
seguem valendo sem mudança.

A identidade de uma conta nova é criada em **`User::save()`**, sobrescrito, e não num evento
`saving`: o `WithoutModelEvents` do `DatabaseSeeder` silencia eventos, e a conta seria gravada sem
`identity_id`. Sobrescrever o método cobre `create`, `update`, `updateOrCreate`, `forceFill()->save()`,
`saveQuietly` e as fábricas. Ligar uma conta a uma identidade **que já existe** é sempre explícito,
pelo `identity_id` — nunca por adivinhação pelo e-mail.

`remember_token` ficou em `users`, não na identidade: o cookie recaller guarda o id do usuário e o
`EloquentUserProvider::retrieveByToken` consulta `users.remember_token`. Movê-lo exigiria um provider
customizado sem ganho nenhum. Trocar a senha roda o token de **todas** as irmãs
(`Identity::changePasswordTo()`).

## Ler a identidade é sempre `loadMissing`
`Model::preventLazyLoading` está ligado fora de produção e a identidade é lida na autenticação de
**toda** requisição: uma violação ali derruba a sessão em vez de responder. Por isso
`User::identityRecord()` usa `loadMissing('identity')`, e não `$this->identity`. Mesma razão do
`loadMissing` em `Portal::dealerAwaitingApproval()`, `Portal::workshopAwaitingApproval()` e
`UserResource` (`workshop_id`), que liam relação não carregada no caminho do login.

## O login web escolhe a irmã pelo portal da URL
As 4 telas já recebem o portal (`routes/web.php`, `login.submit/{portal}`), então
`AuthController::login` confere a senha na **identidade** e loga a irmã daquele portal com
`Auth::login()`. Não usa `Auth::attempt`: ele acha a conta pelo e-mail e, com contas irmãs, não teria
como escolher entre elas. O `throttle:auth-web` continua na rota e o rehash do hash antigo (o que o
`EloquentUserProvider` fazia) está em `rehashIdentityPasswordIfNeeded`.

Senha certa na porta errada não invalida sessão nenhuma (ninguém entrou) e devolve
`suggested_portal`. Com um papel só, a frase é a de sempre ("Esta conta é de oficina…"); com irmãs,
ela **lista as áreas** que o e-mail abre. O Painel admin não é um papel: é a área extra de quem tem
`is_admin`, então `Identity::accountFor(Portal::Admin)` procura a irmã com a flag ligada — e uma
identidade não tem como ter uma conta de proprietário *e* uma de admin separadas, porque as duas são
`user_type = 'user'`.

O 2FA continua **só na API** (`routes/api-two-factor.php`); o login web nunca teve desafio e esta
mudança não criou um.

## O broker de senha busca a identidade
`config/auth.php` ganhou o provider `identities`, e o broker `users` aponta para ele: com contas
irmãs, procurar o e-mail em `users` casaria com mais de uma linha. Quem recebe
`ResetPasswordNotification` é a **identidade** (`Identity::$name` lê o nome da primeira conta, que é
a mesma pessoa), e `Password::createToken()`/`tokenExists()` recebem a identidade nos testes.

Redefinir ou trocar a senha derruba as sessões web e os tokens Sanctum de **todas** as irmãs — a
senha é uma só. A volta depois de redefinir é o login do papel único da identidade, ou o hub quando
ela tem mais de um (qualquer escolha nossa seria chute).

## Excluir um papel não leva os outros; o último leva a identidade
`DeleteUserAccount` move a conta excluída para uma identidade **só dela** (e-mail inutilizável e
senha aleatória) antes de anonimizá-la, e depois apaga a identidade compartilhada se não sobrou
nenhuma conta nela. Sem isso, anonimizar um papel trocaria a senha e o e-mail das irmãs.

`/excluir-conta` é público e não tem sessão para perguntar "qual conta?", então o e-mail leva **um
link por perfil**, nomeado (`AccountDeletionRequestMail::$confirmations`). Um link só, para a
primeira linha que o e-mail achasse, apagaria o papel errado em silêncio.

## Busca por e-mail que vira busca por papel
`VehicleConsignmentService::resolveOwnerUser` procura o e-mail **com `user_type` proprietário**:
anexar a consignação à conta de oficina da mesma pessoa mandaria o aviso para a área errada e
deixaria a liberação do histórico com um papel que não é o dono. `EmailSuppression` e
`WorkshopObserver` continuam por endereço, não por conta — estão certos como estão.

## O cadastro em etapas é a porta da frente (/cadastro)
Três etapas: **perfil e e-mail**, **senha**, **dados do perfil**. A busca do e-mail acontece na
primeira, que é o ponto: num formulário de uma página só, a pessoa preenchia CNPJ, CEP e endereço
inteiros para então ler "informe a senha da sua conta".

O estado mora em `App\Support\Account\SignupFlow` (sessão, 30 minutos), nunca em campo escondido —
ali a senha viajaria no HTML e voltaria em qualquer reenvio. **Senha em claro não entra na sessão**:
no caminho da identidade nova fica o *hash* (que o cast `hashed` aceita pronto) e no da conta irmã
não fica senha nenhuma, só a marca de que a pessoa a soube.

As etapas são guardadas pelo middleware `signup.step`, e não por `authorize()` do FormRequest, por
dois motivos: 403 é resposta errada para quem só perdeu o fluxo, e a validação do FormRequest roda
antes do controller — sem a guarda, a etapa da senha validaria pelo ramo errado (senha nova em vez
da senha da identidade) com o fluxo expirado.

Balde próprio `throttle:signup`, nunca `auth-web`: a chave do `auth-web` cai em
`login:<e-mail>|<ip>` fora dos caminhos que ele conhece, e um cadastro passaria a gastar a cota de
**login** daquele e-mail. São dois limites — por aparelho (enumeração na etapa 1) e por e-mail mais
aparelho (adivinhação de senha na etapa 2, com o e-mail lido da sessão porque o formulário não o
reenvia).

O lojista aparece na escolha mas leva ao login social: a loja não se cadastra por e-mail e senha
(ver "Lojista signs up through a social provider", `.ai/rules/web-api.md`).

As duas entradas antigas continuam: `/register` (proprietário, caminho curto de quem veio da busca
de veículo) e `/para-oficinas/cadastro`, que é a landing de aquisição e carrega o `ref` da
prospecção (`.ai/rules/outreach.md`). Para não existirem duas implementações da mesma coisa, a
criação mora em `App\Services\User\OwnerSignupService` e `App\Services\Workshop\WorkshopSignupService`,
a validação dos campos da oficina em `App\Http\Requests\Concerns\ValidatesWorkshopProfile` e o
formulário deles em `resources/views/auth/partials/workshop-profile-fields.blade.php`. Campo novo da
oficina entra nesses dois lugares, senão a mesma oficina passa numa tela e não na outra.

## Logado não se troca de perfil — é requisito, não acaso
Não existe caminho de conta logada para outro papel, e isso é deliberado: as telas de cadastro e de
login estão **todas** no grupo `guest` (quem está logado cai no próprio Início por
`redirectUsersTo`), e `Portal::accessibleBy()` devolve só a área da própria conta, então o menu de
conta não oferece a área da irmã. Trocar de perfil é sair e entrar pela porta do outro portal.
`Tests\Feature\Web\SignupFlowTest` trava as duas pontas. Qualquer tela de "adicionar perfil" dentro
da conta logada quebraria esse requisito.

## Cadastro do segundo papel pede a senha da identidade
O que bloqueia um cadastro deixou de ser "o e-mail existe" e passou a ser "o papel existe":
`App\Rules\AvailableForUserType` no lugar do `unique:users,email`, com a mesma mensagem de antes
(`custom.email.unique`), porque a saída oferecida é a mesma (entrar ou redefinir). Quando o e-mail já
tem identidade e o papel não, o cadastro pede **a senha dessa identidade**
(`App\Rules\MatchesIdentityPassword`) em vez de uma nova, e a conta nasce irmã. Vale para `/register`
(proprietário) e `/para-oficinas/cadastro` (oficina).

## A API não mudou neste PR — e ainda precisa mudar
`POST /api/v1/login` aceita `portal` **opcional**. Com ele, a conta é a irmã daquele portal; sem ele,
a ordem é fixa e documentada (proprietário, lojista, oficina) em
`Api\AuthController::resolveAccountForPortal`, usada também pelo vínculo por e-mail do Google e da
Apple. Com uma conta só — 100% da base hoje — a resposta é idêntica à de antes.

O login social também **não** cria papel novo: quando o e-mail já tem conta e nenhuma dela atende o
`portal` pedido, a resposta continua 403 (`resolveAccountForPortal(...) ?? accountsForEmail(...)->first()`,
que é o que faz a checagem de portal responder como antes). Criar conta de lojista por Google/Apple
num e-mail que já tem outro papel é decisão de produto, não efeito colateral.

`RegisterRequest` da API segue com `unique:users`: ela **não** cria conta irmã. Enquanto o app não
mandar `portal` em todo login, uma pessoa com dois papéis não escolhe por qual entra no app. Mexer
nisso é mudança de contrato e pede o app primeiro, como em "Endereço é da empresa, não da conta"
(`.ai/rules/api.md`).

## Fábricas
`User::factory()` cria a identidade sozinha (é o `User::save()` que faz isso), então a suíte inteira
continua valendo sem edição. Conta irmã é `User::factory()->asWorkshop()->forIdentity($identity)`,
que reaproveita o e-mail e **não** mexe na senha da identidade. `IdentityFactory` existe para montar
a identidade primeiro quando o teste precisa dela.
