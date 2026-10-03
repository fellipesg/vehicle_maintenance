{{--
    Editor do artigo (novo e edição) em duas colunas: à esquerda o texto (título, resumo e o
    conteúdo com as abas Escrever | Pré-visualizar); à direita, fixos no desktop, Publicação (com o
    Salvar), Endereço, Categoria, Capa e SEO.

    Variáveis: $post (opcional), $categories (ativas mais a atual, mesmo inativa; o select fica
    escrito à mão para manter o aviso de categoria inativa ligado por aria-describedby),
    $submitLabel e $cancelUrl.

    resources/js/admin-blog-editor.js liga os contadores (data-char-count), a prévia do endereço, o
    campo de data só em "Agendar", a pré-visualização do Markdown (POST admin.blog.preview) e o aviso
    ao sair com alterações não salvas. Sem ele o formulário funciona igual: a aba de prévia mostra a
    última versão salva e a data fica sempre visível.
--}}
@php
    use App\Models\BlogPost;

    $post = $post ?? null;
    $selectedCategoryId = (int) old('blog_category_id', $post->blog_category_id ?? 0);
    $selectedCategoryIsInactive = $categories->contains(
        fn ($category) => $category->id === $selectedCategoryId && ! $category->is_active
    );
    $postIsLive = $post?->isPublished() ?? false;
    $postIsScheduled = $post !== null && ! $postIsLive && $post->status === BlogPost::STATUS_PUBLISHED;
    $publishMode = old('publish_mode', match (true) {
        $post === null || $post->status === BlogPost::STATUS_DRAFT => 'draft',
        $postIsScheduled => 'schedule',
        default => 'now',
    });
    $publicBlogPrefix = \Illuminate\Support\Str::after(rtrim(route('blog.index'), '/'), '://').'/';
    $currentSlug = old('slug', $post->slug ?? '');
    $counterFor = fn (string $field, int $max): string => mb_strlen((string) old($field, $post?->{$field} ?? '')).'/'.$max;
    $savedContent = (string) ($post->content ?? '');
    // Situação em cartões (x-ui.radio-cards), um por modo de publicação.
    $publishModeOptions = [
        'draft' => ['label' => 'Rascunho', 'description' => 'Só o admin vê. Fica fora do blog, do feed e do sitemap.', 'icon' => 'pencil-square'],
        'now' => [
            'label' => $postIsLive ? 'Publicado' : 'Publicar agora',
            'description' => $postIsLive ? 'No ar desde '.$post->published_at->format('d/m/Y').' às '.$post->published_at->format('H:i').'.' : 'Entra no blog assim que você salvar.',
            'icon' => 'globe-alt',
        ],
        'schedule' => ['label' => 'Agendar', 'description' => 'Entra no blog, no feed e no sitemap na data escolhida.', 'icon' => 'calendar-days'],
    ];
@endphp

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="min-w-0 space-y-4">
        <x-ui.form-errors />

        <x-ui.card as="section" title="Texto do artigo" heading-level="h2">
            <div class="grid gap-5">
                <x-ui.field name="title" label="Título" required>
                    <x-slot:aside><span class="text-xs text-subtle-foreground tabular-nums" data-char-counter-for="title" aria-hidden="true">{{ $counterFor('title', 160) }}</span></x-slot:aside>
                    <x-ui.input :value="$post->title ?? ''" required maxlength="160" placeholder="Ex.: Quando trocar o óleo do motor?" data-char-count />
                </x-ui.field>

                <x-ui.field name="excerpt" label="Resumo" hint="Uma frase que aparece no card do blog e nos resultados de busca." optional>
                    <x-slot:aside><span class="text-xs text-subtle-foreground tabular-nums" data-char-counter-for="excerpt" aria-hidden="true">{{ $counterFor('excerpt', 300) }}</span></x-slot:aside>
                    <x-ui.textarea :value="$post->excerpt ?? ''" rows="2" maxlength="300" data-char-count />
                </x-ui.field>

                <x-ui.field name="content" label="Conteúdo" hint="Em Markdown: ## para subtítulos, **negrito** e listas com hífen." required>
                    <x-ui.tabs label="Conteúdo do artigo" variant="line">
                        <x-slot:tabs>
                            <x-ui.tab target="conteudo-escrever" :active="true" icon="pencil-square">Escrever</x-ui.tab>
                            <x-ui.tab target="conteudo-previa" icon="eye">Pré-visualizar</x-ui.tab>
                        </x-slot:tabs>

                        <x-ui.tab-panel id="conteudo-escrever" :active="true">
                            <x-ui.textarea
                                :value="$post->content ?? ''"
                                rows="20"
                                autosize
                                required
                                class="font-mono text-sm leading-relaxed"
                                :placeholder="'## Subtítulo'.PHP_EOL.PHP_EOL.'Texto do artigo em Markdown.'"
                            />
                        </x-ui.tab-panel>

                        <x-ui.tab-panel id="conteudo-previa" class="min-h-40 rounded-card border border-border bg-background p-4 sm:p-6">
                            <div aria-busy="false" data-admin-blog-preview>
                                @if($savedContent !== '')
                                    <p class="mb-4 text-xs text-subtle-foreground" data-admin-blog-preview-note>Versão salva. Com o JavaScript ativo, a prévia mostra o texto que está no editor.</p>
                                @endif
                                @include('admin.blog._preview', ['content' => $savedContent])
                            </div>
                        </x-ui.tab-panel>
                    </x-ui.tabs>
                </x-ui.field>
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:sticky lg:top-20">
        <x-ui.card as="section" title="Publicação" heading-level="h2" data-admin-blog-publication>
            <x-ui.fieldset name="publish_mode" legend="Situação" legend-sr-only :selected="$publishMode">
                <x-ui.radio-cards :columns="1" :options="$publishModeOptions" />
            </x-ui.fieldset>

            <div class="mt-3" data-admin-blog-schedule>
                <x-ui.field name="published_at" label="Data de publicação" hint="Usada só em Agendar.">
                    <x-ui.input type="datetime-local" :value="$postIsScheduled ? $post->published_at->format('Y-m-d\TH:i') : null" />
                </x-ui.field>
            </div>

            <x-slot:footer class="flex-wrap justify-end border-t border-border pt-4">
                <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">{{ $submitLabel }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card as="section" title="Endereço" heading-level="h2">
            <x-ui.field name="slug" label="Slug" hint="Em branco, é gerado a partir do título." optional>
                <x-ui.input :value="$post->slug ?? ''" maxlength="180" autocomplete="off" placeholder="quando-trocar-o-oleo" />
            </x-ui.field>
            <p class="mt-2 text-xs break-all text-muted-foreground" data-admin-blog-slug-preview data-prefix="{{ $publicBlogPrefix }}">
                {{ $publicBlogPrefix }}<span class="font-mono text-foreground" data-admin-blog-slug-value>{{ $currentSlug !== '' ? $currentSlug : '…' }}</span>
            </p>
        </x-ui.card>

        <x-ui.card as="section" title="Categoria" heading-level="h2">
            <x-ui.field name="blog_category_id" label="Categoria" label-sr-only>
                <div class="relative min-w-0" data-slot="select">
                    <select name="blog_category_id" id="blog_category_id" class="form-select h-10 appearance-none bg-none text-base sm:text-sm aria-invalid:border-danger"
                            @if($selectedCategoryIsInactive) aria-describedby="blog_category_id_inactive_hint" @endif>
                        <option value="">Sem categoria</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategoryId === $category->id)>{{ $category->name }}@unless($category->is_active) (inativa)@endunless</option>
                        @endforeach
                    </select>
                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                </div>
                @if($selectedCategoryIsInactive)
                    <p id="blog_category_id_inactive_hint" class="flex items-start gap-1.5 text-sm text-warning">
                        <x-ui.icon name="exclamation-triangle" variant="solid" class="mt-0.5 size-4" />
                        <span>Esta categoria está inativa e não aparece na listagem pública.</span>
                    </p>
                @endif
            </x-ui.field>
        </x-ui.card>

        <x-ui.card as="section" title="Capa" heading-level="h2">
            <div class="grid gap-4">
                @if($post?->cover_photo_url)
                    <img src="{{ $post->cover_photo_url }}" alt="Capa atual do artigo" class="aspect-[1200/630] w-full rounded-control border border-border bg-surface-muted object-contain">
                    <x-ui.checkbox name="remove_cover" label="Remover a capa atual" />
                @elseif($post)
                    <div class="overflow-hidden rounded-control border border-border">
                        <x-blog.cover :post="$post" class="aspect-[1200/630] w-full" />
                    </div>
                @endif
                <x-ui.field name="cover" label="Foto de capa" hint="Proporção de 1200 × 630, a mesma do card do blog." optional>
                    <x-ui.file-input accept="image/jpeg,image/png,image/webp" :max-mb="4" />
                </x-ui.field>
                <x-ui.field name="cover_art" label="Ilustração de capa" hint="Usada quando o artigo não tem foto de capa.">
                    <x-ui.select :options="\App\Support\BlogCoverArt::SCENES" :value="$post->cover_art ?? ''" placeholder="Automática pela categoria" />
                </x-ui.field>
                <x-ui.field name="cover_photo_alt" label="Texto alternativo da capa" hint="Descreva a foto para quem usa leitor de tela." optional>
                    <x-ui.input :value="$post->cover_photo_alt ?? ''" maxlength="160" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <details class="group rounded-card border border-border bg-surface p-4 sm:p-5" @if($errors->hasAny(['meta_title', 'meta_description'])) open @endif>
            <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-2 text-base font-semibold text-foreground [&::-webkit-details-marker]:hidden">
                SEO
                <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
            </summary>
            <div class="mt-4 grid gap-5">
                <x-ui.field name="meta_title" label="Meta título" hint="Em branco, usa o título do artigo." optional>
                    <x-slot:aside><span class="text-xs text-subtle-foreground tabular-nums" data-char-counter-for="meta_title" aria-hidden="true">{{ $counterFor('meta_title', 160) }}</span></x-slot:aside>
                    <x-ui.input :value="$post->meta_title ?? ''" maxlength="160" data-char-count />
                </x-ui.field>
                <x-ui.field name="meta_description" label="Meta descrição" hint="Em branco, usa o resumo." optional>
                    <x-slot:aside><span class="text-xs text-subtle-foreground tabular-nums" data-char-counter-for="meta_description" aria-hidden="true">{{ $counterFor('meta_description', 255) }}</span></x-slot:aside>
                    <x-ui.textarea :value="$post->meta_description ?? ''" rows="3" maxlength="255" data-char-count />
                </x-ui.field>
            </div>
        </details>
    </div>
</div>
