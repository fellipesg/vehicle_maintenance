<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    /**
     * Error bag do diálogo "Nova categoria".
     */
    public const CREATE_BAG = 'createCategory';

    /**
     * Error bag do diálogo de edição de uma categoria: cada linha tem o seu, então o erro e o texto
     * digitado voltam só no diálogo que foi enviado.
     */
    public static function updateBag(BlogCategory|int $category): string
    {
        return 'updateCategory'.($category instanceof BlogCategory ? $category->id : $category);
    }

    public function index(): View
    {
        $categories = BlogCategory::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return view('admin.blog.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag(self::CREATE_BAG, [
            'name' => ['required', 'string', 'max:80', 'unique:blog_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        BlogCategory::create([
            'name' => trim($data['name']),
            'slug' => BlogCategory::generateSlug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.blog.categories.index')
            ->with('success', 'Categoria criada.');
    }

    /**
     * Renomear não muda o endereço público quando keep_slug vem marcado (o padrão do formulário):
     * /blog/categoria/{slug} já pode estar compartilhado e indexado. Sem keep_slug, o endereço é
     * gerado de novo a partir do nome e o aviso diz o novo.
     */
    public function update(Request $request, BlogCategory $category): RedirectResponse
    {
        $data = $request->validateWithBag(self::updateBag($category), [
            'name' => ['required', 'string', 'max:80', 'unique:blog_categories,name,'.$category->id],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'keep_slug' => ['sometimes', 'boolean'],
        ]);

        $previousSlug = $category->slug;
        $slug = $request->boolean('keep_slug')
            ? $previousSlug
            : BlogCategory::generateSlug($data['name'], $category->id);

        $category->update([
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $message = $slug === $previousSlug
            ? 'Categoria atualizada.'
            : 'Categoria atualizada. O endereço público mudou para /blog/categoria/'.$slug.'.';

        return redirect()->route('admin.blog.categories.index')
            ->with('success', $message);
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.blog.categories.index')
            ->with('success', 'Categoria excluída.');
    }
}
