<?php

namespace App\Support;

/**
 * Google AdSense, só nas páginas públicas do blog e só com ADSENSE_CLIENT_ID definido. Portais,
 * landing, consulta pública e selo (/v/) ficam sem anúncio: anúncio de oficina concorrente na tela
 * do cliente, ou numa página que prova autenticidade, custa mais do que rende.
 */
final class GoogleAdsense
{
    /**
     * Rotas que carregam o script do AdSense e o aviso de consentimento.
     */
    public const ROUTES = ['blog.index', 'blog.category', 'blog.show'];

    /**
     * ID do cliente ("ca-pub-…"), ou null quando o AdSense está desligado.
     */
    public static function client(): ?string
    {
        $client = trim((string) config('services.google_adsense.client'));

        return preg_match('/^ca-pub-\d{10,20}$/', $client) === 1 ? $client : null;
    }

    /**
     * ID do editor para o ads.txt ("pub-…"), derivado do cliente.
     */
    public static function publisherId(): ?string
    {
        $client = self::client();

        return $client === null ? null : substr($client, strlen('ca-'));
    }

    /**
     * ID de um bloco de anúncio configurado (blog_article, blog_index), ou null.
     */
    public static function slot(string $name): ?string
    {
        $slot = trim((string) config("services.google_adsense.slots.{$name}"));

        return self::client() !== null && preg_match('/^\d{6,20}$/', $slot) === 1 ? $slot : null;
    }

    public static function isActiveOnCurrentPage(): bool
    {
        return self::client() !== null && request()->routeIs(...self::ROUTES);
    }
}
