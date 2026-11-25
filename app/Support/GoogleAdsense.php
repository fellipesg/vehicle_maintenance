<?php

namespace App\Support;

/**
 * Google AdSense, só na página do artigo do blog e só com ADSENSE_CLIENT_ID definido. Listagem do
 * blog, categorias, portais, landing, busca e selo (/v/) ficam sem anúncio: o AdSense recusou o site
 * por pouco conteúdo, e anúncio em página que é só lista de links, ou em página que prova
 * autenticidade, custa mais do que rende.
 */
final class GoogleAdsense
{
    /**
     * Rotas que carregam o script do AdSense e o aviso de consentimento.
     */
    public const ROUTES = ['blog.show'];

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
     * ID de um bloco de anúncio configurado (blog_article), ou null.
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
