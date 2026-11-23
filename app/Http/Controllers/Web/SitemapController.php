<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\Seo\CanonicalUrl;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at']);

        $categories = BlogCategory::query()
            ->active()
            ->whereHas('posts', fn ($query) => $query->published())
            ->get(['slug', 'updated_at']);

        $staticPages = [
            'home' => '1.0',
            'blog.index' => '0.8',
            'workshops.landing' => '0.7',
            'workshops.signup' => '0.6',
            'verification.lookup' => '0.5',
            'guides.comparison' => '0.7',
            'contact.show' => '0.5',
            'legal.terms' => '0.3',
            'legal.privacy' => '0.3',
        ];

        $staticUrls = [];
        $lastmod = config('seo.static_lastmod');

        foreach ($staticPages as $name => $priority) {
            $staticUrls[] = [
                'loc' => CanonicalUrl::named($name),
                'priority' => $priority,
                'lastmod' => is_array($lastmod) ? ($lastmod[$name] ?? null) : null,
            ];
        }

        return response()
            ->view('sitemap', compact('staticUrls', 'posts', 'categories'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
