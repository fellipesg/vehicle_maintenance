---
paths:
  - 'app/Support/Seo/**'
  - 'app/Http/Middleware/NoIndex.php'
  - 'app/Http/Controllers/Web/LlmsTxtController.php'
  - 'app/Http/Controllers/Web/SitemapController.php'
  - 'config/seo.php'
  - 'resources/views/layouts/partials/seo-head.blade.php'
  - 'resources/views/components/seo/**'
  - 'public/robots.txt'
---

# SEO público

## Canonical e Open Graph saem do layout, uma vez
`layouts/partials/seo-head.blade.php` imprime canonical, description, og:url, og:type e o grafo Organization + WebSite. A view só passa `@section('meta_description')`, `@section('canonical')` (quando a URL não é a da rota) e `@section('og_type')`. Não repetir essas tags em `@push('head')`. A home canônica é `rtrim(config('app.url'), '/') . '/'`; as outras páginas não têm barra final nem query string (`CanonicalUrl`). og:image continua só no `x-brand-head-icons`.

## noindex não entra no robots.txt
Login, cadastro de conta, senha, comentário do blog e a área autenticada levam o middleware `noindex` (meta `noindex, follow` e header `X-Robots-Tag`). `/para-oficinas/cadastro` fica de fora: é landing indexável. O `robots.txt` não lista `Disallow` dessas rotas, para o crawler ler a meta. Convites, descadastro e resultado de verificação já têm a própria meta e não usam esse middleware.

## FAQ visível e FAQPage são o mesmo texto
A resposta é uma lista de trechos em `App\Support\Seo\Faq`. O componente `x-seo.faq-list` concatena os trechos sem espaço extra, e o JSON-LD usa `Faq::plain`. Link de FAQ não usa `external` no `x-ui.link`: o aviso "(abre em nova aba)" entraria no texto visível e sairia do schema.

## /sobre é a página de quem faz o RevisaLog
`/sobre` (rota `about`, `AboutController`, `resources/views/about/show.blade.php`) só afirma o que o repositório sustenta: o que é o produto, selo × declarada, o que não garante, grátis no lançamento, app iOS, como o blog usa fontes oficiais e correção pelo `/contato`, e o nome do responsável. Não inventar credenciais, histórico, números, equipe, oficinas parceiras nem processo de revisão que ninguém executa. A bio de "Quem escreve" (`#autor`) vem de uma única linha, `lang/pt_BR/about.php` (`author_bio`), e não imprime nada enquanto estiver vazia; o dono preenche. Está no sitemap (`config/seo.php`), no rodapé e no llms.txt.

## Sitemap e llms.txt
Páginas fixas têm `lastmod` em `config/seo.php` (`static_lastmod`, chave = nome da rota). A chave tem ponto (`blog.index`): leia o array inteiro, porque `config('seo.static_lastmod.blog.index')` procura um nível `blog` que não existe. A data é a da última mudança do HTML público, não a versão do texto legal. Posts e categorias usam `updated_at`. Rota com noindex e `/llms.txt` não entram no sitemap. `llms.txt` é a rota `llms` (`text/plain; charset=UTF-8`); cada link local precisa responder 200. Página ou post novo que deva ser citado entra no arquivo no mesmo commit em que a URL passa a existir.
