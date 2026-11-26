<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // O nonce nasce antes da view renderizar: é dele que saem os atributos `nonce` dos scripts
        // inline (@cspNonce) e das tags do @vite.
        if ($this->cspIsEnabled()) {
            ContentSecurityPolicy::useNonce();
        }

        /** @var Response $response */
        $response = $next($request);

        foreach (config('security.headers', []) as $header => $value) {
            $response->headers->set($header, $value);
        }

        $this->applyContentSecurityPolicy($request, $response);

        return $response;
    }

    /**
     * O CSP das páginas vale só para HTML, e nunca sobrescreve um que a resposta já traga.
     *
     * As respostas de arquivo do cliente (relatório de inspeção, nota fiscal, foto de manutenção)
     * mandam o próprio CSP, bem mais apertado — `default-src 'none'; sandbox`. Um `set()` cego aqui
     * trocaria aquela política pela desta, que libera script com nonce: o arquivo enviado por
     * terceiro voltaria a ter alcance dentro da origem do site.
     */
    private function applyContentSecurityPolicy(Request $request, Response $response): void
    {
        if (! $this->cspIsEnabled()) {
            return;
        }

        if ($response->headers->has('Content-Security-Policy')
            || $response->headers->has('Content-Security-Policy-Report-Only')) {
            return;
        }

        if (! $this->respondsWithHtml($response)) {
            return;
        }

        $policy = ContentSecurityPolicy::header($request);

        if ($policy === null) {
            return;
        }

        $response->headers->set(ContentSecurityPolicy::headerName(), $policy);

        // `report-to` só vale com o destino declarado à parte. O `report-uri`, que vai no próprio
        // CSP, atende Firefox e Safari; este atende o Chrome, que já deu o outro por obsoleto.
        if (($reportUri = ContentSecurityPolicy::reportUri()) !== null) {
            $response->headers->set(
                'Reporting-Endpoints',
                ContentSecurityPolicy::REPORTING_ENDPOINT.'="'.$reportUri.'"',
            );
        }
    }

    private function cspIsEnabled(): bool
    {
        return (bool) config('security.csp.enabled', true);
    }

    private function respondsWithHtml(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type');

        return str_contains(strtolower($contentType), 'text/html');
    }
}
