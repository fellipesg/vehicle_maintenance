---
paths:
  - 'app/Http/Controllers/Web/BlogController.php'
  - 'app/Support/BlogCoverArt.php'
  - 'resources/views/components/blog/**'
  - 'app/Http/Controllers/Web/Admin/BlogPostController.php'
  - 'app/Models/BlogPost.php'
  - 'app/Models/BlogComment.php'
  - 'app/Http/Controllers/Web/BlogCommentController.php'
  - 'app/Http/Controllers/Web/Admin/BlogCommentController.php'
  - 'resources/views/blog/**'
  - 'resources/views/admin/blog/**'
---

# Blog

## Publicação é status + published_at
Post público exige `status = published` E `published_at` no passado (scope `published`). Rascunho sempre zera `published_at`; data futura mantém o post agendado (fora da listagem, feed e sitemap). Só admin vê rascunho em /blog/{slug}, com aviso de pré-visualização.

## Agendamento é no horário de Brasília e não precisa de cron
O campo "Data de publicação" (datetime-local) é lido no fuso de exibição com `DisplayTime::fromLocalInput` e gravado em UTC; o editor e a lista do admin mostram com `DisplayTime::local`. A validação de data futura compara depois da conversão (não usar `after:now`, que compara em UTC). O post agendado entra no ar sozinho quando `published_at` passa, porque o scope `published` filtra por `now()`: não há comando nem job de publicação, e não se deve criar um.

## Conteúdo é Markdown renderizado na view
`content` é gravado como Markdown e renderizado com `Str::markdown` dentro de `.blog-content` (estilos em resources/css/app.css, o projeto não usa @tailwindcss/typography). Não gravar HTML pronto no banco nem adicionar plugin de tipografia. Se o arquivo começa com `> uma linha`, essa linha é o lead (`data-slot=blog-post-lead`) e sai do corpo; os outros posts continuam usando o excerpt. `## Perguntas frequentes` seguido de `### pergunta` vira FAQPage com o texto do Markdown.

## Slug é gerado, nunca colidido
Use `BlogPost::generateSlug()` / `BlogCategory::generateSlug()` — eles sufixam -2, -3 quando o slug já existe. O slug é a route key das duas models.

## Capa usa o prefixo blog-covers/
Upload de capa vai por `BlogCoverService` com `AppStorage::BLOG_COVERS_PREFIX`, que está registrado em `isPublicCacheEligiblePath` e `usesCoversDisk` (disco de covers / R2). Não usar `Storage::put` direto nem `asset()` para capas.

## Post sem foto usa cena SVG ilustrada
Quando `cover_photo_path` é nulo, card e hero renderizam `<x-blog.cover>`: SVG inline com gradiente, padrão de pontos e uma **cena** desenhada (relatório + veículo, calendário de revisão, motor com funil, documento, selo, venda, veículo genérico). A cena vem de `blog_posts.cover_art`; vazio cai na cena padrão da categoria em `App\Support\BlogCoverArt`, e categoria desconhecida usa paleta determinística pelo slug (crc32) com a cena `carro`. No card passe `:label="false"`, porque o chip da categoria já aparece logo abaixo.

## Cena nova exige as três pontas
Para acrescentar uma ilustração: registre a chave em `BlogCoverArt::SCENES` (rótulo em PT-BR, é o que aparece no select do admin), crie `resources/views/components/blog/art/<chave>.blade.php` recebendo `:art` e desenhe no viewBox 1200x630 — o `<svg>`, o fundo e o rótulo já vêm do cover. A cena é recuada (`translate(36,34) scale(0.94)`) para não colidir com o rótulo da categoria no canto superior esquerdo; deixe o topo-esquerdo livre. `BlogCoverArtTest` falha se a chave não tiver componente, e o admin só aceita chaves de `SCENES`.

## Mídia do card é 1200x630
Foto e cena ocupam `aspect-[1200/630]` no card, para a grade não ficar com alturas diferentes quando só alguns posts têm foto. Não voltar para altura fixa (`h-52`) em um dos dois ramos. A foto aparece inteira (`object-contain` sobre `bg-surface-muted`) no card, no artigo e na prévia do admin: na proporção pedida (1200 × 630) ela preenche a moldura; fora dela nada é cortado. Não voltar para `object-cover` (a catraca DesignSystemGuardrailsTest está em zero).

## Card do blog tem um link só
`x-blog.card` tem um único link esticado (o título, `after:absolute after:inset-0`); a capa é decorativa (`alt=""` na foto, `decorative` no `x-blog.cover`) e a categoria fica clicável por cima com `relative z-10`. `heading-level` acompanha a página: `h3` dentro de uma seção com `h2` ("Leia também"), `h2` na listagem.

## Leitura em coluna de 56ch
O artigo e os documentos legais usam a coluna de leitura de `.blog-content`/`.doc-content` (app.css): `max-w-[56ch]` (~72 caracteres por linha na Inter; 68ch dava ~89), 17px no celular e 18px a partir de sm, entrelinha 1,8. O H1 vem sempre do `x-ui.page-header` (no artigo, o tamanho maior vai no slot title).

## Imagem de compartilhamento vai pelo layout
A capa do post vira og:image e twitter:image por `@section('og_image')` e `@section('og_image_alt')`; `layouts.app` repassa ao `<x-brand-head-icons include-og-image>`, que imprime uma vez só (sem a capa, a arte og-preview.png da marca). Nunca um segundo og:image em `@push('head')`: WhatsApp e Facebook usam o primeiro.

## SEO acompanha o post
Canonical, meta description, og:type, og:title, og:url e og:description saem do layout (`seo-head`), via `@section`. O post só acrescenta no `@push('head')` o link do feed, `article:*` e o JSON-LD (`BlogPosting` + `BreadcrumbList`). Não repetir description, canonical nem og:url. Sem capa, o BlogPosting omite `image` (não usar a arte og da marca como imagem do artigo). Autor é Person (nome do autor do post, ou Felipe Gonçalves quando não há autor gravado, ver `App\Support\Seo\BlogAuthor`); quando quem assina é o Felipe, o `author.url` e o nome da linha de autoria apontam para `/sobre#autor`, e outro autor não leva link. `dateModified`, o "Atualizado em" e o `lastmod` do sitemap usam `BlogPost::modified_at` (o maior entre `updated_at` e `published_at`): post agendado nasce com `updated_at` anterior a `published_at` e nunca pode declarar modificação antes da publicação. publisher aponta para o `@id` da Organization. Trilha visível: Início › Blog › Categoria › Post. O sitemap e o feed listam só posts publicados.

## Posts de resposta direta têm seeder próprio
`AeoBlogPostsSeeder` publica os quatro posts de `database/data/aeo_posts.php` (data 23/11/2026 00:00 em Brasília) e, se a linha já existir, atualiza o texto de `vender-o-carro-com-historico-de-manutencao`, `selo-da-oficina-manutencao-verificada`, `como-o-revisalog-guarda-o-historico-do-seu-carro` e `historico-de-carro-usado-como-consultar` sem mexer em `published_at`. Não rode `BlogSeeder` de novo para publicar isso: ele recoloca `published_at` em `now()`. Em produção o comando é `php artisan db:seed --class=AeoBlogPostsSeeder --force`.

## Série de posts vem de arquivo, e o seeder só cria
`BlogSeriesSeeder` publica a série de `database/data/blog_series.php` (metadados, `publish_at` no horário de Brasília) com o corpo em `database/data/blog_series/{slug}.md`. Todo post entra como publicado com a data do calendário: data passada já aparece, data futura fica agendada (regra acima). O seeder usa `firstOrCreate` pelo slug e não sobrescreve post existente, para não desfazer edição feita no admin; corrigir texto já publicado é pelo admin, ou por migration de dados quando a correção é do próprio arquivo: trocar o Markdown não alcança linha já semeada (publicada ou agendada), então uma migration troca só o trecho exato antigo pelo novo, por slug, com o query builder (sem tocar `updated_at`) e sem efeito se o texto já foi editado ou a linha não existe (ver `fix_blog_series_promises_and_public_search_wording`). Nenhum post promete "post futuro"/"próximo post" (o agendado pode mudar) nem chama de "consulta pública" a busca `/buscar-veiculo`, que exige conta gratuita; só `/verificar` é aberto a visitante (`BlogSeriesWordingTest`). Link interno de um post só pode apontar para post de data anterior, porque o agendado responde 404 (`BlogSeriesSeederTest` confere). Não usar lista de tarefas (`- [ ]`) no Markdown: o conversor GFM vira checkbox desabilitado ao lado do marcador da lista.

## Comentários: só conta logada, um nível, texto puro
Comenta quem está logado, de qualquer perfil (proprietário, lojista, oficina, admin); visitante lê os publicados e vê "Entre para comentar" / "Criar conta" (`blog.comments.enter`, que grava o artigo em `url.intended` e leva ao hub ou ao cadastro; por isso `blog` está em `PortalAccess::PUBLIC_INTENDED_SEGMENTS`). Sem resposta, curtida nem edição; o autor exclui o próprio (`BlogCommentPolicy::delete`: autor ou admin). Corpo de 2 a 2.000 caracteres depois do trim, impresso escapado com `whitespace-pre-line`: sem Markdown, HTML nem link automático. A seção `#comentarios` fica depois do anúncio e do convite, nunca dentro de `.blog-content`. Comentários não entram no feed nem no sitemap; o JSON-LD leva `commentCount` (só publicados).

## Moderação é por link
Texto com `http://`, `https://`, `www.` ou formato de domínio (`site.com.br`, ver `BlogComment::containsLink`) é gravado `pending`; o resto sai `published`. Situações: `published`, `pending`, `hidden`. O autor vê o próprio pendente com o selo "Aguardando moderação"; ninguém mais vê pendente nem oculto. O admin aprova, oculta ou exclui em `admin.blog.comments.index` (filtros Todos/Pendentes/Publicados/Ocultos); os pendentes entram nas Pendências da visão geral. Comentário de admin leva o selo "Equipe RevisaLog".

## Nome público é primeiro nome + inicial
`BlogComment::author_display_name` calcula na hora ("Felipe G."; nome de uma palavra sai como está). Nunca nome completo nem e-mail na página pública (o admin vê os dois). Nada é gravado: mudar o nome na conta muda o comentário.

## Abuso e interruptor por artigo
POST com `throttle:blog-comments` (5/min e 30/dia por conta, `AppServiceProvider`), honeypot `website` (finge sucesso e não grava, como o /contato) e só em post publicado (rascunho e agendado dão 404). `blog_posts.comments_enabled` (padrão true, switch "Permitir comentários" no editor; ausente no envio, mantém o valor): desligado, os publicados continuam visíveis, o formulário vira "Os comentários estão fechados neste artigo." e o POST é recusado.

## Exclusão de conta apaga os comentários
`DeleteUserAccount` apaga os `blog_comments` da pessoa na mesma transação: a conta é anonimizada, não removida, então o cascade de `user_id` não dispara. Está na política de privacidade (seções 2 e 7) e nos termos (seção "Comentários no blog"), versão 2026-11-18.
