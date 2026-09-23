@php
    $post = $post ?? null;
@endphp

<div class="grid gap-4">
    <div>
        <label for="title" class="form-label">Título *</label>
        <input type="text" name="title" id="title" required maxlength="160"
               value="{{ old('title', $post->title ?? '') }}"
               class="form-input" placeholder="Ex: Quando trocar o óleo do motor?">
        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="slug" class="form-label">Slug</label>
            <input type="text" name="slug" id="slug" maxlength="180"
                   value="{{ old('slug', $post->slug ?? '') }}"
                   class="form-input" placeholder="gerado a partir do título">
            @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="blog_category_id" class="form-label">Categoria</label>
            <select name="blog_category_id" id="blog_category_id" class="form-select">
                <option value="">Sem categoria</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        @selected((int) old('blog_category_id', $post->blog_category_id ?? 0) === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('blog_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="excerpt" class="form-label">Resumo</label>
        <textarea name="excerpt" id="excerpt" rows="2" maxlength="300" class="form-input"
                  placeholder="Uma frase que aparece no card e na busca">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
        @error('excerpt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="content" class="form-label">Conteúdo * <span class="font-normal text-automotive-500">(Markdown)</span></label>
        <textarea name="content" id="content" rows="20" required class="form-input font-mono text-xs leading-relaxed"
                  placeholder="## Subtítulo&#10;&#10;Texto do artigo em Markdown.">{{ old('content', $post->content ?? '') }}</textarea>
        @error('content')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="cover" class="form-label">Imagem de capa</label>
            @if($post?->cover_photo_url)
                <img src="{{ $post->cover_photo_url }}" alt="Capa atual" class="mb-2 h-32 w-full rounded-lg border border-automotive-200 object-cover">
                <label class="mb-2 flex items-center gap-2 text-sm text-automotive-700">
                    <input type="checkbox" name="remove_cover" value="1"
                           class="rounded border-automotive-300 text-wrench-600 focus:ring-wrench-500">
                    Remover capa atual
                </label>
            @endif
            <input type="file" name="cover" id="cover" accept="image/*" class="form-input">
            @error('cover')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="cover_art" class="form-label">Ilustração de capa</label>
            <select name="cover_art" id="cover_art" class="form-select">
                <option value="">Automática pela categoria</option>
                @foreach(\App\Support\BlogCoverArt::SCENES as $scene => $sceneLabel)
                    <option value="{{ $scene }}" @selected(old('cover_art', $post->cover_art ?? '') === $scene)>
                        {{ $sceneLabel }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-automotive-500">Usada quando o post não tem foto de capa.</p>
            @error('cover_art')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

            @if($post && ! $post->cover_photo_url)
                <div class="mt-3 overflow-hidden rounded-lg border border-automotive-200">
                    <x-blog.cover :post="$post" class="aspect-[1200/630] w-full" />
                </div>
            @endif
        </div>

        <div>
            <label for="cover_photo_alt" class="form-label">Texto alternativo da capa</label>
            <input type="text" name="cover_photo_alt" id="cover_photo_alt" maxlength="160"
                   value="{{ old('cover_photo_alt', $post->cover_photo_alt ?? '') }}"
                   class="form-input" placeholder="Descrição da imagem para acessibilidade">
            @error('cover_photo_alt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="status" class="form-label">Status *</label>
            <select name="status" id="status" class="form-select">
                <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Rascunho</option>
                <option value="published" @selected(old('status', $post->status ?? 'draft') === 'published')>Publicado</option>
            </select>
            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="published_at" class="form-label">Data de publicação</label>
            <input type="datetime-local" name="published_at" id="published_at"
                   value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"
                   class="form-input">
            <p class="mt-1 text-xs text-automotive-500">Em branco publica agora. Data futura agenda o post.</p>
            @error('published_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <details class="rounded-lg border border-automotive-200 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-automotive-800">SEO</summary>
        <div class="mt-4 grid gap-4">
            <div>
                <label for="meta_title" class="form-label">Meta título</label>
                <input type="text" name="meta_title" id="meta_title" maxlength="160"
                       value="{{ old('meta_title', $post->meta_title ?? '') }}" class="form-input">
                @error('meta_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="meta_description" class="form-label">Meta descrição</label>
                <textarea name="meta_description" id="meta_description" rows="2" maxlength="255"
                          class="form-input">{{ old('meta_description', $post->meta_description ?? '') }}</textarea>
                @error('meta_description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </details>
</div>
