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
Foto e cena ocupam `aspect-[1200/630]` no card, para a grade não ficar com alturas diferentes quando só alguns posts têm foto. Não voltar para altura fixa (`h-52`) em um dos dois ramos.

## SEO acompanha o post
Alterações em blog.show devem manter canonical, og:*, JSON-LD BlogPosting e o link do feed. O sitemap (/sitemap.xml) e o feed (/blog/feed) listam só posts publicados.
