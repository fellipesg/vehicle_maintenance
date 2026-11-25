<?php

namespace App\Support\Security;

use App\Support\GoogleAdsense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Monta o Content-Security-Policy das páginas HTML (App\Http\Middleware\SetSecurityHeaders).
 *
 * O eixo da política é `'nonce-…'` + `'strict-dynamic'`: script inline só roda com o nonce do
 * pedido, e script que um script confiável injeta herda a confiança. É isso que deixa o GA4 e o
 * AdSense carregarem os próprios arquivos sem `'unsafe-inline'` e sem listar domínio por domínio.
 * Os hosts listados em script-src são o degrau para navegador sem `'strict-dynamic'` (Safari < 15.4,
 * que entende nonce mas ignora a palavra): lá a lista vale, nos atuais ela é ignorada.
 *
 * Dois perfis, porque anúncio e portal não cabem na mesma política:
 * páginas do blog com AdSense (GoogleAdsense::ROUTES) liberam img/frame/connect para `https:`,
 * porque criativo e iframe de anúncio vêm de domínio que não se enumera; o resto do site — e todo
 * portal autenticado, onde estão chassi, RENAVAM, documento do proprietário e nota fiscal — fica na
 * lista fechada. Em navegador com `'strict-dynamic'` nem o perfil do anúncio aceita script injetado
 * sem nonce, que é o vetor que interessa.
 */
final class ContentSecurityPolicy
{
    /**
     * Tamanho do nonce em caracteres (Str::random devolve alfanuméricos).
     */
    private const NONCE_LENGTH = 32;

    /**
     * Nome do destino no cabeçalho Reporting-Endpoints, citado por `report-to`.
     */
    public const REPORTING_ENDPOINT = 'csp';

    /**
     * GA4 (x-analytics). Carrega em página pública e na tela seguinte ao cadastro.
     */
    private const ANALYTICS_SCRIPT_HOSTS = [
        'https://www.googletagmanager.com',
        'https://*.googletagmanager.com',
        'https://www.google-analytics.com',
    ];

    private const ANALYTICS_CONNECT_HOSTS = [
        'https://*.google-analytics.com',
        'https://*.analytics.google.com',
        'https://*.googletagmanager.com',
    ];

    /**
     * Turnstile protege os formulários públicos: o api.js desenha o desafio dentro de um iframe
     * do próprio challenges.cloudflare.com, então o host precisa estar em script-src e frame-src.
     */
    private const TURNSTILE_HOST = 'https://challenges.cloudflare.com';

    /**
     * Leaflet dos mapas do admin (CSS, JS e os ícones de pino que o CSS referencia).
     */
    private const LEAFLET_HOST = 'https://cdn.jsdelivr.net';

    /**
     * Tiles do OpenStreetMap, pedidos pelo resources/js/admin-map.js.
     */
    private const MAP_TILE_HOSTS = ['https://*.tile.openstreetmap.org'];

    private const FONT_HOST = 'https://fonts.bunny.net';

    /**
     * Gera o nonce do pedido e o guarda onde o Blade e o @vite leem.
     */
    public static function useNonce(): string
    {
        return Vite::useCspNonce(Str::random(self::NONCE_LENGTH));
    }

    /**
     * Nonce do pedido, ou null quando nenhum foi gerado (console, teste de view isolada).
     */
    public static function nonce(): ?string
    {
        return Vite::cspNonce();
    }

    /**
     * Nome do cabeçalho: Report-Only enquanto security.csp.enforce for false.
     */
    public static function headerName(): string
    {
        return config('security.csp.enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';
    }

    /**
     * Endereço que recebe as violações, ou null quando não há relatório configurado.
     */
    public static function reportUri(): ?string
    {
        $configured = trim((string) config('security.csp.report_uri'));

        if ($configured !== '') {
            return $configured;
        }

        return route('csp-report');
    }

    /**
     * Política completa do pedido, ou null quando não há nonce para sustentá-la.
     *
     * Sem nonce o `'strict-dynamic'` derrubaria todo script da página, então é melhor não mandar
     * cabeçalho nenhum do que mandar um que quebra a tela.
     */
    public static function header(Request $request): ?string
    {
        if (self::nonce() === null) {
            return null;
        }

        $directives = self::directives($request);

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $sources === []
                ? $directive
                : $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function directives(Request $request): array
    {
        $nonce = self::nonce();
        $onAdPage = GoogleAdsense::isActiveOnCurrentPage();
        $viteOrigins = self::viteDevOrigins();

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'script-src' => [
                "'self'",
                "'nonce-{$nonce}'",
                "'strict-dynamic'",
                ...self::ANALYTICS_SCRIPT_HOSTS,
                self::TURNSTILE_HOST,
                self::LEAFLET_HOST,
                ...($onAdPage ? ['https:'] : []),
                ...$viteOrigins['http'],
            ],
            // O projeto é Tailwind com atributo style nos componentes, e o Turnstile injeta estilo
            // próprio. Estilo inline não executa script: o risco não se compara ao de script-src.
            'style-src' => ["'self'", "'unsafe-inline'", self::FONT_HOST, self::LEAFLET_HOST, ...$viteOrigins['http']],
            'font-src' => ["'self'", 'data:', self::FONT_HOST],
            'img-src' => $onAdPage
                ? ["'self'", 'data:', 'blob:', 'https:']
                : [
                    "'self'",
                    'data:',
                    'blob:',
                    self::LEAFLET_HOST,
                    ...self::MAP_TILE_HOSTS,
                    'https://www.google-analytics.com',
                    ...self::configuredStorageOrigins(),
                ],
            'connect-src' => $onAdPage
                ? ["'self'", 'https:', ...$viteOrigins['http'], ...$viteOrigins['ws']]
                : [
                    "'self'",
                    ...self::ANALYTICS_CONNECT_HOSTS,
                    ...self::configuredStorageOrigins(),
                    ...$viteOrigins['http'],
                    ...$viteOrigins['ws'],
                ],
            'frame-src' => $onAdPage
                ? ["'self'", self::TURNSTILE_HOST, 'https:']
                : ["'self'", self::TURNSTILE_HOST],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
        ];

        // Página servida em https sobe os subrecursos junto; em http local isso transformaria o
        // servidor do Vite (http://localhost:5173) em https e derrubaria o `npm run dev`.
        if ($request->isSecure()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        $directives = self::withExtraHosts($directives);

        if (($reportUri = self::reportUri()) !== null) {
            $directives['report-uri'] = [$reportUri];
            $directives['report-to'] = [self::REPORTING_ENDPOINT];
        }

        return array_map(
            static fn (array $sources): array => array_values(array_unique($sources)),
            $directives,
        );
    }

    /**
     * Hosts extras de config/security.php, para liberar uma origem nova sem alterar código.
     *
     * @param  array<string, list<string>>  $directives
     * @return array<string, list<string>>
     */
    private static function withExtraHosts(array $directives): array
    {
        $extra = config('security.csp.extra_hosts', []);

        if (! is_array($extra)) {
            return $directives;
        }

        foreach ($extra as $directive => $sources) {
            if (! is_string($directive) || ! is_array($sources) || ! array_key_exists($directive, $directives)) {
                continue;
            }

            $directives[$directive] = [...$directives[$directive], ...array_map('strval', $sources)];
        }

        return $directives;
    }

    /**
     * Origens das URLs públicas dos discos (R2/S3 e o /storage local), de onde saem capa de
     * veículo, imagens da marca, logo de oficina e capa de artigo.
     *
     * @return list<string>
     */
    private static function configuredStorageOrigins(): array
    {
        $urls = [];

        foreach ((array) config('filesystems.disks', []) as $disk) {
            if (is_array($disk) && isset($disk['url'])) {
                $urls[] = $disk['url'];
            }
        }

        $urls[] = config('app.asset_url');

        return self::originsFrom($urls);
    }

    /**
     * Servidor de desenvolvimento do Vite, quando `npm run dev` está no ar (public/hot).
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private static function viteDevOrigins(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $hot = @file_get_contents(public_path('hot'));

        if ($hot === false) {
            return ['http' => [], 'ws' => []];
        }

        $origins = self::originsFrom([trim($hot)]);

        return [
            'http' => $origins,
            'ws' => array_map(
                static fn (string $origin): string => Str::startsWith($origin, 'https://')
                    ? Str::replaceFirst('https://', 'wss://', $origin)
                    : Str::replaceFirst('http://', 'ws://', $origin),
                $origins,
            ),
        ];
    }

    /**
     * Reduz URLs a origens (esquema, host e porta), descartando o que não é absoluto.
     *
     * @param  array<int, mixed>  $urls
     * @return list<string>
     */
    private static function originsFrom(array $urls): array
    {
        $origins = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);

            if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
                continue;
            }

            $origin = $parts['scheme'].'://'.$parts['host'];

            if (isset($parts['port'])) {
                $origin .= ':'.$parts['port'];
            }

            $origins[] = $origin;
        }

        return array_values(array_unique($origins));
    }
}
