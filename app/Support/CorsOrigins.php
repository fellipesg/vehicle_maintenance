<?php

namespace App\Support;

/**
 * Origens aceitas pelo CORS, lidas do ambiente.
 *
 * A lógica morava em duas funções globais declaradas dentro de `config/cors.php`. Sob
 * `config:cache` o arquivo de config não é carregado, então aquelas funções não existiam em
 * runtime — só nos testes, que carregam a config do arquivo. Sendo autoloaded, a classe não
 * desaparece desse jeito.
 *
 * Mesmo assim, estes métodos servem para **montar** a config, não para consultar em runtime: eles
 * leem `env()`, e com a config em cache o Laravel nem carrega o `.env`
 * (`LoadEnvironmentVariables::bootstrap` sai na primeira linha), de modo que `allowed()` cairia no
 * `['*']` fora de produção. Quem precisa das origens depois de a aplicação subir lê
 * `config('cors.allowed_origins')`, que é o valor que este arquivo produziu.
 */
class CorsOrigins
{
    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $raw = env('CORS_ALLOWED_ORIGINS');

        if (is_string($raw) && trim($raw) !== '') {
            return self::split($raw);
        }

        if (env('APP_ENV') === 'production') {
            $appUrl = rtrim((string) env('APP_URL', ''), '/');

            return $appUrl !== '' ? [$appUrl] : [];
        }

        return ['*'];
    }

    /**
     * Sem curinga padrão: o CORS com credenciais só vale para as origens listadas em
     * `CORS_ALLOWED_ORIGINS` / `CORS_ALLOWED_ORIGIN_PATTERNS`.
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        $raw = env('CORS_ALLOWED_ORIGIN_PATTERNS');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return self::split($raw);
    }

    /**
     * Credenciais ficam ligadas sempre que a lista não é `*`, porque o navegador recusa
     * `Access-Control-Allow-Origin: *` junto de `Allow-Credentials: true`.
     */
    public static function supportsCredentials(): bool
    {
        return ! in_array('*', self::allowed(), true);
    }

    /**
     * @return list<string>
     */
    private static function split(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
