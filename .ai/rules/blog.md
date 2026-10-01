---
paths:
  - 'app/Http/Controllers/Web/BlogController.php'
  - 'app/Support/BlogCoverArt.php'
  - 'resources/views/components/blog/**'
  - 'app/Http/Controllers/Web/Admin/BlogPostController.php'
  - 'app/Models/BlogPost.php'
  - 'resources/views/blog/**'
  - 'resources/views/admin/blog/**'
---

# Blog

## Publicação é status + published_at
Post público exige `status = published` E `published_at` no passado (scope `published`). Rascunho sempre zera `published_at`; data futura mantém o post agendado (fora da listagem, feed e sitemap). Só admin vê rascunho em /blog/{slug}, com aviso de pré-visualização.

## Conteúdo é Markdown renderizado na view
`content` é gravado como Markdown e renderizado com `Str::markdown` dentro de `.blog-content` (estilos em resources/css/app.css, o projeto não usa @tailwindcss/typography). Não gravar HTML pronto no banco nem adicionar plugin de tipografia.

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
Alterações em blog.show devem manter canonical, og:*, JSON-LD BlogPosting e o link do feed. O sitemap (/sitemap.xml) e o feed (/blog/feed) listam só posts publicados.
