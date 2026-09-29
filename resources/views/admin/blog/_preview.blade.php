{{--
    Pré-visualização do editor de artigo (POST admin.blog.preview e a aba "Pré-visualizar"): o
    Markdown renderizado como no artigo publicado, com Str::markdown dentro de .blog-content
    (.ai/rules/blog.md). Nada é gravado.
--}}
@if(trim($content) === '')
    <p class="text-sm text-muted-foreground" data-admin-blog-preview-empty>Escreva o conteúdo para ver a pré-visualização.</p>
@else
    <div class="blog-content">{!! Str::markdown($content) !!}</div>
@endif
