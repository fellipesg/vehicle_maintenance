# 🔐 Guia de Configuração OAuth - Google

Este guia explica passo a passo como obter as credenciais OAuth do Google para habilitar login social no aplicativo.

O backend usa Laravel Socialite. As rotas envolvidas (ver `routes/api.php` e `config/services.php`):

| Rota | Papel |
| --- | --- |
| `GET /api/v1/auth/google/redirect` | Manda o usuário para a tela do Google |
| `GET /api/v1/auth/google/callback` | Recebe o retorno e emite o token Sanctum |

O mesmo par existe para `facebook` e `twitter` (`FACEBOOK_*` / `TWITTER_*`).

> O Google Cloud Console muda de layout com frequência. Os nomes abaixo refletem o console atual
> ("APIs e serviços" → "Credenciais" e a seção "Google Auth Platform"); se um menu aparecer com outro
> nome, procure por "Tela de permissão OAuth" e "Clientes".

## 📋 Pré-requisitos

- Conta Google (Gmail)
- Acesso ao [Google Cloud Console](https://console.cloud.google.com/)

## 🚀 Passo a Passo

### 1. Acessar o Google Cloud Console

1. Acesse: https://console.cloud.google.com/
2. Faça login com sua conta Google

### 2. Criar ou Selecionar um Projeto

1. No topo da página, clique no seletor de projetos (ao lado do logo do Google Cloud)
2. Clique em **"NOVO PROJETO"** (ou selecione um projeto existente)
3. Preencha:
   - **Nome do projeto**: `Vehicle Maintenance` (ou outro nome de sua escolha)
   - **Organização**: Deixe como está (ou selecione se tiver)
4. Clique em **"CRIAR"**
5. Aguarde alguns segundos e selecione o projeto recém-criado

### 3. Nenhuma API extra para ativar

A antiga **Google+ API** foi desativada em 2019 e não existe mais no console. Para "Entrar com o Google"
(OpenID Connect), **não é preciso ativar API nenhuma**: basta a tela de permissão OAuth e um ID de cliente.

Só ative a **People API** (em "APIs e serviços" → "Biblioteca") se o app for ler dados adicionais do perfil
além de `openid`, `email` e `profile` — o Socialite não precisa dela para o login usado aqui.

### 4. Criar Credenciais OAuth 2.0

1. No menu lateral, vá em **"APIs e serviços"** → **"Credenciais"**
2. Clique no botão **"+ CRIAR CREDENCIAIS"** no topo
3. Selecione **"ID do cliente OAuth 2.0"**

### 5. Configurar Tela de Consentimento OAuth

**Se for a primeira vez configurando OAuth neste projeto:**

1. Você será redirecionado para a **"Tela de permissão OAuth"** (em consoles novos: **"Google Auth Platform"** → **"Branding"**)
2. Selecione **"Externo"** (para desenvolvimento/teste)
3. Clique em **"CRIAR"**
4. Preencha os campos obrigatórios:
   - **Nome do aplicativo**: `Revisalog`
   - **Email de suporte do usuário**: `suporte@revisalog.com.br` (ou seu e-mail)
   - **Email de contato do desenvolvedor**: Seu email
5. Clique em **"SALVAR E CONTINUAR"**
6. Na próxima tela (Escopos), clique em **"SALVAR E CONTINUAR"**
7. Na tela de usuários de teste (opcional), clique em **"SALVAR E CONTINUAR"**
8. Na tela de resumo, clique em **"VOLTAR AO PAINEL"**

### 6. Criar o ID do Cliente OAuth 2.0

1. Volte para **"APIs e serviços"** → **"Credenciais"**
2. Clique em **"+ CRIAR CREDENCIAIS"** → **"ID do cliente OAuth 2.0"**
3. Preencha o formulário:
   - **Tipo de aplicativo**: Selecione **"Aplicativo da Web"**
   - **Nome**: `Revisalog Web Client` (ou outro nome)
   - **Origens JavaScript autorizadas**:
     - `http://localhost:8080` (stack Docker, `make up` — porta `APP_PORT`)
     - `http://localhost:8000` (sem Docker, `composer run dev`)
     - `https://revisalog.com.br` (produção)
   - **URIs de redirecionamento autorizados** — sempre `{APP_URL}/api/v1/auth/google/callback`:
     - `http://localhost:8080/api/v1/auth/google/callback`
     - `http://localhost:8000/api/v1/auth/google/callback`
     - `https://revisalog.com.br/api/v1/auth/google/callback`
4. Clique em **"CRIAR"**

> A URI de redirecionamento tem que bater **exatamente** com `GOOGLE_REDIRECT_URI` no `.env` (mesmo
> esquema, host, porta e caminho). Se usar um IP da rede local para testar no celular
> (`http://192.168.x.x:8080/...`), cadastre esse endereço também.

### 7. Copiar as Credenciais

1. Uma janela será exibida com suas credenciais:
   - **ID do cliente**: `xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com`
   - **Segredo do cliente**: `GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxxxxx`
2. **IMPORTANTE**: Copie essas informações agora! O segredo do cliente só é mostrado uma vez.
3. Clique em **"OK"**

### 8. Configurar no Backend

1. Edite o `.env` na raiz deste repositório (as chaves comentadas já estão no `.env.example`).

2. Preencha as três variáveis (substitua pelos valores reais):
   ```env
   GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-seu_client_secret_aqui
   GOOGLE_REDIRECT_URI=http://localhost:8080/api/v1/auth/google/callback
   ```

3. Salve o arquivo. Nunca commite o `.env` nem o client secret.

4. Recarregue a configuração:
   ```bash
   # Docker (Makefile):
   make shell   # depois, no container: php artisan config:clear
   # ou simplesmente:
   docker compose restart app

   # Sem Docker:
   php artisan config:clear
   ```

5. Em produção (Laravel Cloud) as mesmas variáveis entram no painel do ambiente, com
   `GOOGLE_REDIRECT_URI=https://revisalog.com.br/api/v1/auth/google/callback`.

### 9. Testar a Configuração

1. No Flutter app, tente fazer login com Google (ou abra `{APP_URL}/api/v1/auth/google/redirect` no navegador)
2. O navegador deve abrir e mostrar a tela de login do Google
3. Após autenticar, o Google volta em `/api/v1/auth/google/callback` e a API responde com o token Sanctum
4. O callback tem rate limit (`throttle:auth`); tentativas repetidas em sequência podem responder 429

## 🔍 Verificar Credenciais Existentes

Se você já criou credenciais e precisa visualizá-las novamente:

1. Acesse: https://console.cloud.google.com/
2. Vá em **"APIs e serviços"** → **"Credenciais"**
3. Clique no nome do seu **"ID do cliente OAuth 2.0"**
4. Você verá o **ID do cliente** (mas não o segredo)
5. Se precisar do segredo novamente, clique em **"RECRIAR SEGREDO DO CLIENTE"**

## ⚠️ Importante

- **Segredo do cliente**: Só é mostrado uma vez! Guarde-o com segurança.
- **URIs de redirecionamento**: Devem corresponder exatamente às URLs configuradas
- **Ambiente de produção**: Para produção, você precisará:
  - Configurar a tela de consentimento como "Público" (após revisão do Google)
  - Adicionar o domínio de produção nas URIs de redirecionamento
  - Configurar domínio verificado no Google Search Console

## 🆘 Problemas Comuns

### "redirect_uri_mismatch"
- Verifique se a URI no `.env` corresponde exatamente à configurada no Google Cloud Console
- Certifique-se de incluir `http://` ou `https://`
- Verifique se não há espaços extras ou barras no final

### "invalid_client"
- Verifique se o `GOOGLE_CLIENT_ID` está correto
- Verifique se o `GOOGLE_CLIENT_SECRET` está correto
- Certifique-se de que limpou o cache de config (`php artisan config:clear`) ou reiniciou o container após alterar o `.env`

### "Access blocked: Authorization Error"
- Com o app em modo **Teste**, só entram as contas listadas em "Usuários de teste" na tela de permissão OAuth
- Verifique se as credenciais estão configuradas corretamente no `.env`
- Não procure pela "Google+ API": ela não existe mais e não é necessária

## 📚 Recursos Adicionais

- [Documentação do Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2)
- [Google Cloud Console](https://console.cloud.google.com/)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)

