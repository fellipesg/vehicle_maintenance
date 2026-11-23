<?php

namespace App\Support\Seo;

use Illuminate\Http\Request;

/**
 * URL canônica absoluta, sem query string. A home fica com barra final
 * (https://revisalog.com.br/); as demais páginas não têm barra final.
 * O sitemap usa o mesmo método, para a <loc> e o canonical coincidirem.
 */
final class CanonicalUrl
{
    public static function origin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function home(): string
    {
        return self::origin().'/';
    }

    public static function organizationId(): string
    {
        return self::home().'#organization';
    }

    public static function websiteId(): string
    {
        return self::home().'#website';
    }

    public static function named(string $name, mixed $parameters = []): string
    {
        if ($name === 'home') {
            return self::home();
        }

        return rtrim(route($name, $parameters, absolute: true), '/');
    }

    public static function forRequest(?Request $request = null): string
    {
        $request ??= request();
        $route = $request->route();
        $name = $route?->getName();

        if ($name === 'home' || $request->path() === '/') {
            return self::home();
        }

        if (is_string($name) && $name !== '') {
            return self::named($name, $route->parameters());
        }

        return rtrim($request->url(), '/');
    }
}
