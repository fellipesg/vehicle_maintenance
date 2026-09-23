<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
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

        $staticUrls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('blog.index'), 'priority' => '0.8'],
            ['loc' => route('contact.show'), 'priority' => '0.5'],
            ['loc' => route('legal.terms'), 'priority' => '0.3'],
            ['loc' => route('legal.privacy'), 'priority' => '0.3'],
        ];

        return response()
            ->view('sitemap', compact('staticUrls', 'posts', 'categories'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
