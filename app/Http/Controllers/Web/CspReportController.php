<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recebe as violações de Content-Security-Policy e grava no log.
 *
 * É o destino padrão de `report-uri`/`report-to` (config/security.php): serve para rodar alguns
 * dias em Report-Only e ver o que a política bloquearia antes de ligar o bloqueio de verdade.
 * Quem preferir o Sentry aponta CSP_REPORT_URI para o endpoint de segurança do projeto e esta rota
 * deixa de ser usada.
 */
class CspReportController extends Controller
{
    /**
     * Esquemas de extensão de navegador. Quem instalou a extensão gera a violação; o site não tem
     * o que corrigir, e sem este filtro o log do trial vira ruído.
     */
    private const IGNORED_SCHEMES = [
        'chrome-extension',
        'moz-extension',
        'safari-extension',
        'safari-web-extension',
        'webkit-masked-url',
        'chrome',
        'about',
    ];

    public function __invoke(Request $request): Response
    {
        foreach ($this->reports($request) as $report) {
            $this->record($report);
        }

        // 204: o navegador não lê corpo de resposta de relatório.
        return response()->noContent();
    }

    /**
     * Os dois formatos chegam na mesma rota: `report-uri` manda um objeto com a chave
     * `csp-report`, e a Reporting API do `report-to` manda uma lista de relatórios.
     *
     * @return list<array<string, mixed>>
     */
    private function reports(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            return [$payload['csp-report']];
        }

        $reports = [];

        foreach ($payload as $entry) {
            if (is_array($entry) && isset($entry['body']) && is_array($entry['body'])) {
                $reports[] = $entry['body'];
            }
        }

        return $reports;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function record(array $report): void
    {
        // A Reporting API renomeou as chaves; os dois nomes são aceitos.
        $blockedUri = (string) ($report['blocked-uri'] ?? $report['blockedURL'] ?? '');
        $directive = (string) ($report['effective-directive']
            ?? $report['effectiveDirective']
            ?? $report['violated-directive']
            ?? '');
        $documentUri = (string) ($report['document-uri'] ?? $report['documentURL'] ?? '');

        if ($this->isNoise($blockedUri)) {
            return;
        }

        $channel = trim((string) config('security.csp.report_log_channel'));

        $logger = $channel === '' ? Log::channel(config('logging.default')) : Log::channel($channel);

        $logger->warning('Violação de Content-Security-Policy', [
            'directive' => Str::limit($directive, 120, ''),
            'blocked_uri' => Str::limit($blockedUri, 300, ''),
            'document_uri' => Str::limit($documentUri, 300, ''),
            'disposition' => (string) ($report['disposition'] ?? ''),
            'line_number' => $report['line-number'] ?? $report['lineNumber'] ?? null,
            'source_file' => Str::limit((string) ($report['source-file'] ?? $report['sourceFile'] ?? ''), 300, ''),
        ]);
    }

    private function isNoise(string $blockedUri): bool
    {
        if ($blockedUri === '') {
            return false;
        }

        $scheme = Str::lower(Str::before($blockedUri, ':'));

        return in_array($scheme, self::IGNORED_SCHEMES, true);
    }
}
