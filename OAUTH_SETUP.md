# 🔐 Guia de Configuração OAuth - Google

Este guia explica passo a passo como obter as credenciais OAuth do Google para habilitar login social no aplicativo.

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

### 3. Ativar a API do Google+

1. No menu lateral esquerdo, vá em **"APIs e serviços"** → **"Biblioteca"**
2. Na barra de pesquisa, digite: `Google+ API`
3. Clique no resultado **"Google+ API"**
4. Clique no botão **"ATIVAR"**
5. Aguarde a ativação (pode levar alguns segundos)

### 4. Criar Credenciais OAuth 2.0

1. No menu lateral, vá em **"APIs e serviços"** → **"Credenciais"**
2. Clique no botão **"+ CRIAR CREDENCIAIS"** no topo
3. Selecione **"ID do cliente OAuth 2.0"**

### 5. Configurar Tela de Consentimento OAuth

**Se for a primeira vez configurando OAuth neste projeto:**

1. Você será redirecionado para a **"Tela de consentimento OAuth"**
2. Selecione **"Externo"** (para desenvolvimento/teste)
3. Clique em **"CRIAR"**
4. Preencha os campos obrigatórios:
   - **Nome do aplicativo**: `Vehicle Maintenance`
   - **Email de suporte do usuário**: Seu email
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
   - **Nome**: `Vehicle Maintenance Web Client` (ou outro nome)
   - **Origens JavaScript autorizadas**: 
     - `http://localhost:8080` (para desenvolvimento)
     - `http://127.0.0.1:8080` (para desenvolvimento)
   - **URIs de redirecionamento autorizados**: 
     - `http://localhost:8080/api/v1/auth/google/callback`
     - `http://127.0.0.1:8080/api/v1/auth/google/callback`
     - `http://192.168.3.11:8080/api/v1/auth/google/callback` (se usar IP local)
4. Clique em **"CRIAR"**

### 7. Copiar as Credenciais

1. Uma janela será exibida com suas credenciais:
   - **ID do cliente**: `xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com`
   - **Segredo do cliente**: `GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxxxxx`
2. **IMPORTANTE**: Copie essas informações agora! O segredo do cliente só é mostrado uma vez.
3. Clique em **"OK"**

### 8. Configurar no Backend

1. Abra o arquivo `.env` do backend:
   ```bash
   cd backend
   # Se estiver usando Docker:
   docker compose exec app nano .env
   # Ou edite diretamente: backend/.env
   ```

2. Adicione as seguintes linhas (substitua pelos valores reais):
   ```env
   GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-seu_client_secret_aqui
   GOOGLE_REDIRECT_URI=http://localhost:8080/api/v1/auth/google/callback
   ```

3. Salve o arquivo

4. Reinicie o container do backend:
   ```bash
   docker compose restart app
   ```

### 9. Testar a Configuração

1. No Flutter app, tente fazer login com Google
2. O navegador deve abrir e mostrar a tela de login do Google
3. Após autenticar, você será redirecionado de volta

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
- Certifique-se de que reiniciou o container após alterar o `.env`

### "Access blocked: Authorization Error"
- Verifique se a API "Google+ API" está ativada
- Verifique se as credenciais estão configuradas corretamente no `.env`

## 📚 Recursos Adicionais

- [Documentação do Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2)
- [Google Cloud Console](https://console.cloud.google.com/)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)

