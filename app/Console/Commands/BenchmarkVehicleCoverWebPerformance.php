<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class BenchmarkVehicleCoverWebPerformance extends Command
{
    protected $signature = 'benchmark:vehicle-covers-web
        {--label=prod-web : Etiqueta do benchmark (ex: prod-web-s3, prod-web-r2)}
        {--base-url= : URL base (default: produção Laravel Cloud)}
        {--path=/usuario/veiculos : Caminho autenticado a medir}
        {--email= : E-mail de login web}
        {--from-api : Medir capas da prod via API (sem login web; HTML estimado)}
        {--rounds=3 : Repetições por cenário}';

    protected $description = 'Mede carregamento real da tela web (login + HTML + capas no navegador)';

    public function handle(): int
    {
        $label = (string) $this->option('label');
        $rounds = max(1, (int) $this->option('rounds'));
        $baseUrl = rtrim((string) ($this->option('base-url') ?: 'https://vehicle-maintenance-production-l6pnoo.laravel.cloud'), '/');
        $path = (string) $this->option('path');
        $email = (string) ($this->option('email') ?: 'fgoncalves2008@gmail.com');
        $password = (string) env('BENCHMARK_WEB_PASSWORD', '');
        $fromApi = (bool) $this->option('from-api');

        if ($fromApi || $password === '') {
            if ($password === '') {
                $this->warn('BENCHMARK_WEB_PASSWORD não definido — medindo capas via API de produção (sem HTML autenticado).');
            }

            return $this->handleFromApi($label, $rounds, $baseUrl, $email, $password !== '' ? $this->measureHtmlMedian($baseUrl, $path, $email, $password, $rounds) : null);
        }

        $pageTimings = [];
        $imageBatches = [];

        for ($i = 0; $i < $rounds; $i++) {
            $result = $this->measureWebPage($baseUrl, $path, $email, $password);
            if ($result === null) {
                $this->error('Falha ao medir a página web (login ou HTTP).');

                return self::FAILURE;
            }

            $pageTimings[] = $result;
            $imageBatches[] = $result['images'];
        }

        $screenLoad = $this->buildScreenLoad($pageTimings);
        $vehicles = $this->aggregateImageRows($imageBatches);
        $storageKind = $this->detectStorageKind($vehicles);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'label' => $label,
            'mode' => 'web-browser-simulation',
            'base_url' => $baseUrl,
            'path' => $path,
            'storage' => [
                'covers_disk' => $storageKind,
                'driver' => 'web',
                'endpoint' => $baseUrl,
                'public_url' => $vehicles[0]['cover_photo_url'] ?? null,
            ],
            'screen_load' => $screenLoad,
            'vehicles' => $vehicles,
            'rounds' => $pageTimings,
            'summary' => [
                'covers_count' => count($vehicles),
                'html_median_ms' => $this->median(array_column($pageTimings, 'html_ms')),
                'first_cover_median_ms' => $this->median(array_column($pageTimings, 'first_cover_ms')),
                'all_covers_median_ms' => $this->median(array_column($pageTimings, 'all_covers_ms')),
                'screen_web_lista_todas_capas_ms' => $screenLoad['web_lista_veiculos']['todas_capas_visiveis_ms'] ?? null,
            ],
        ];

        $this->writeReport($label, $report);

        return self::SUCCESS;
    }

    private function handleFromApi(string $label, int $rounds, string $baseUrl, string $email, ?float $htmlMedianMs): int
    {
        $user = \App\Models\User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("Usuário não encontrado: {$email}");

            return self::FAILURE;
        }

        $token = $user->createToken('benchmark-web-api-'.now()->timestamp)->plainTextToken;

        try {
            $pageTimings = [];
            $imageBatches = [];

            for ($i = 0; $i < $rounds; $i++) {
                $started = microtime(true);
                $response = Http::timeout(120)->withToken($token)->acceptJson()->get("{$baseUrl}/api/v1/my-vehicles");
                $apiMs = round((microtime(true) - $started) * 1000, 2);

                if (! $response->successful()) {
                    $this->error('API remota falhou: HTTP '.$response->status());

                    return self::FAILURE;
                }

                $urls = collect($response->json('data') ?? [])
                    ->pluck('cover_photo_url')
                    ->filter(fn ($url) => is_string($url) && $url !== '')
                    ->values()
                    ->all();

                $images = $this->downloadImagesLikeBrowser($urls);
                $htmlMs = $htmlMedianMs ?? $apiMs;

                $firstCoverMs = $images !== [] ? round($htmlMs + min(array_column($images, 'download_ms')), 2) : null;
                $allCoversMs = $images !== [] ? round($htmlMs + max(array_column($images, 'parallel_batch_ms')), 2) : null;

                $pageTimings[] = [
                    'html_ms' => $htmlMs,
                    'api_ms' => $apiMs,
                    'cover_count' => count($urls),
                    'first_cover_ms' => $firstCoverMs,
                    'all_covers_ms' => $allCoversMs,
                    'images' => $images,
                    'image_urls' => $urls,
                ];
                $imageBatches[] = $images;
            }

            $screenLoad = $this->buildScreenLoad($pageTimings);
            $vehicles = $this->aggregateImageRows($imageBatches);
            $storageKind = $this->detectStorageKind($vehicles);

            $report = [
                'generated_at' => now()->toIso8601String(),
                'label' => $label,
                'mode' => $htmlMedianMs !== null ? 'web-html-plus-api-images' : 'web-api-images-only',
                'base_url' => $baseUrl,
                'path' => '/usuario/veiculos',
                'storage' => [
                    'covers_disk' => $storageKind,
                    'driver' => 'web',
                    'endpoint' => $baseUrl,
                    'public_url' => $vehicles[0]['cover_photo_url'] ?? null,
                ],
                'screen_load' => $screenLoad,
                'vehicles' => $vehicles,
                'rounds' => $pageTimings,
                'summary' => [
                    'covers_count' => count($vehicles),
                    'html_median_ms' => $this->median(array_column($pageTimings, 'html_ms')),
                    'first_cover_median_ms' => $this->median(array_filter(array_column($pageTimings, 'first_cover_ms'))),
                    'all_covers_median_ms' => $this->median(array_filter(array_column($pageTimings, 'all_covers_ms'))),
                    'screen_web_lista_todas_capas_ms' => $screenLoad['web_lista_veiculos']['todas_capas_visiveis_ms'] ?? null,
                ],
            ];

            $this->writeReport($label, $report);
        } finally {
            $user->tokens()->where('name', 'like', 'benchmark-web-api-%')->delete();
        }

        return self::SUCCESS;
    }

    /**
     * @return float|null Mediana do tempo de HTML autenticado, se senha disponível.
     */
    private function measureHtmlMedian(string $baseUrl, string $path, string $email, string $password, int $rounds): ?float
    {
        $timings = [];

        for ($i = 0; $i < $rounds; $i++) {
            $result = $this->measureWebPage($baseUrl, $path, $email, $password);
            if ($result !== null) {
                $timings[] = (float) $result['html_ms'];
            }
        }

        return $this->median($timings);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function measureWebPage(string $baseUrl, string $path, string $email, string $password): ?array
    {
        $jar = new \GuzzleHttp\Cookie\CookieJar;
        $client = Http::withOptions([
            'cookies' => $jar,
            'allow_redirects' => true,
            'connect_timeout' => 20,
            'timeout' => 120,
        ])->acceptHtml();

        $loginPage = $client->get("{$baseUrl}/login/usuario");
        if (! $loginPage->successful()) {
            return null;
        }

        $csrf = $this->extractCsrfToken($loginPage->body());
        if ($csrf === null) {
            return null;
        }

        $login = $client->asForm()->post("{$baseUrl}/login/usuario", [
            '_token' => $csrf,
            'email' => $email,
            'password' => $password,
        ]);

        if (! $login->successful() && ! $login->redirect()) {
            return null;
        }

        $navigationStart = microtime(true);
        $page = $client->get($baseUrl.$path);
        $htmlMs = round((microtime(true) - $navigationStart) * 1000, 2);

        if (! $page->successful()) {
            return null;
        }

        $imageUrls = $this->extractCoverImageUrls($page->body(), $baseUrl);
        $imageResults = $this->downloadImagesLikeBrowser($imageUrls);

        $firstCoverMs = null;
        $allCoversMs = null;

        if ($imageResults !== []) {
            $firstCoverMs = round($htmlMs + min(array_column($imageResults, 'download_ms')), 2);
            $allCoversMs = round($htmlMs + max(array_column($imageResults, 'parallel_batch_ms')), 2);
        }

        return [
            'html_ms' => $htmlMs,
            'cover_count' => count($imageUrls),
            'first_cover_ms' => $firstCoverMs,
            'all_covers_ms' => $allCoversMs,
            'images' => $imageResults,
            'image_urls' => $imageUrls,
        ];
    }

    private function extractCsrfToken(string $html): ?string
    {
        if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractCoverImageUrls(string $html, string $baseUrl): array
    {
        preg_match_all('/<img[^>]+alt="Capa do[^"]*"[^>]+src="([^"]+)"/i', $html, $matches);

        $urls = [];
        foreach ($matches[1] ?? [] as $src) {
            $urls[] = str_starts_with($src, 'http') ? $src : rtrim($baseUrl, '/').'/'.ltrim($src, '/');
        }

        if ($urls !== []) {
            return array_values(array_unique($urls));
        }

        preg_match_all('/<img[^>]+src="([^"]*vehicle-covers\/[^"]+)"/i', $html, $fallback);

        foreach ($fallback[1] ?? [] as $src) {
            $urls[] = str_starts_with($src, 'http') ? $src : rtrim($baseUrl, '/').'/'.ltrim($src, '/');
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, array<string, mixed>>
     */
    private function downloadImagesLikeBrowser(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        $parallelStarted = microtime(true);
        $responses = Http::pool(function (Pool $pool) use ($urls) {
            foreach ($urls as $index => $url) {
                $pool->as((string) $index)
                    ->timeout(120)
                    ->withOptions(['connect_timeout' => 20])
                    ->get($url);
            }
        });
        $parallelBatchMs = round((microtime(true) - $parallelStarted) * 1000, 2);

        $rows = [];
        foreach ($urls as $index => $url) {
            $started = microtime(true);
            try {
                $response = Http::timeout(120)->withOptions(['connect_timeout' => 20])->get($url);
                $downloadMs = round((microtime(true) - $started) * 1000, 2);
                $status = $response->status();
                $sizeKb = strlen($response->body()) / 1024;
            } catch (\Throwable) {
                $downloadMs = round((microtime(true) - $started) * 1000, 2);
                $status = 0;
                $sizeKb = 0;
            }

            $rows[] = [
                'cover_photo_url' => $url,
                'download_ms' => $downloadMs,
                'parallel_batch_ms' => $parallelBatchMs,
                'http_status' => $status,
                'size_kb' => round($sizeKb, 1),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageTimings
     * @return array<string, mixed>
     */
    private function buildScreenLoad(array $pageTimings): array
    {
        $htmlMs = $this->median(array_column($pageTimings, 'html_ms')) ?? 0;
        $firstMs = $this->median(array_filter(array_column($pageTimings, 'first_cover_ms'), fn ($v) => $v !== null)) ?? 0;
        $allMs = $this->median(array_filter(array_column($pageTimings, 'all_covers_ms'), fn ($v) => $v !== null)) ?? 0;
        $coverCount = (int) ($pageTimings[0]['cover_count'] ?? 0);
        $sequential = 0.0;
        foreach ($pageTimings[0]['images'] ?? [] as $image) {
            $sequential += (float) ($image['download_ms'] ?? 0);
        }

        return [
            'web_lista_veiculos' => [
                'tela' => 'Web — lista de veículos (navegador)',
                'descricao' => 'Login + HTML da página + capas carregando em paralelo como <img> no browser',
                'capas' => $coverCount,
                'api_ms' => $htmlMs,
                'primeira_capa_visivel_ms' => $firstMs,
                'todas_capas_visiveis_ms' => $allMs,
                'todas_capas_sequencial_ms' => round($htmlMs + $sequential, 2),
            ],
        ];
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $batches
     * @return array<int, array<string, mixed>>
     */
    private function aggregateImageRows(array $batches): array
    {
        $firstBatch = $batches[0] ?? [];
        $rows = [];

        foreach ($firstBatch as $index => $image) {
            $downloads = [];
            foreach ($batches as $batch) {
                if (isset($batch[$index]['download_ms'])) {
                    $downloads[] = (float) $batch[$index]['download_ms'];
                }
            }

            $rows[] = [
                'vehicle_id' => $index + 1,
                'license_plate' => '—',
                'label' => 'Capa '.($index + 1),
                'cover_photo_url' => $image['cover_photo_url'],
                'size_kb' => $image['size_kb'] ?? null,
                'http_download_median_ms' => $this->median($downloads),
                'http_status' => $image['http_status'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $vehicles
     */
    private function detectStorageKind(array $vehicles): string
    {
        foreach ($vehicles as $vehicle) {
            $url = (string) ($vehicle['cover_photo_url'] ?? '');
            if (str_contains($url, '.r2.dev')) {
                return 'r2';
            }
            if (str_contains($url, 'X-Amz-Signature') || str_contains($url, 'neon.tech')) {
                return 's3';
            }
            if (str_contains($url, '/storage/')) {
                return 'local';
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(string $label, array $report): void
    {
        $dir = storage_path('app/benchmarks');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $jsonPath = "{$dir}/vehicle-covers-web-{$label}-{$stamp}.json";
        $mdPath = "{$dir}/vehicle-covers-web-{$label}-{$stamp}.md";
        $latestJson = "{$dir}/vehicle-covers-web-{$label}-latest.json";
        $latestMd = "{$dir}/vehicle-covers-web-{$label}-latest.md";

        $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonPath, $encoded);
        file_put_contents($latestJson, $encoded);

        $markdown = $this->toMarkdown($report);
        file_put_contents($mdPath, $markdown);
        file_put_contents($latestMd, $markdown);

        $this->newLine();
        $this->line($markdown);
        $this->newLine();
        $this->info("JSON: {$jsonPath}");
        $this->info("Markdown: {$mdPath}");
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function toMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Benchmark WEB — carregamento no navegador ('.$report['label'].')';
        $lines[] = '';
        $lines[] = '- **Gerado em:** '.$report['generated_at'];
        $lines[] = '- **URL:** '.($report['base_url'] ?? '').($report['path'] ?? '');
        $lines[] = '- **Storage detectado:** '.($report['storage']['covers_disk'] ?? '—');
        if (! empty($report['storage']['public_url'])) {
            $lines[] = '- **Exemplo de capa:** '.$report['storage']['public_url'];
        }
        $lines[] = '';
        $lines[] = '## Tempo de carregamento da tela (web real)';
        $lines[] = '';
        $lines[] = '| Tela | Capas | HTML (ms) | 1ª capa visível (ms) | Todas capas (paralelo, ms) | Sequencial (ms) |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: |';

        foreach ($report['screen_load'] as $scenario) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | **%s** | %s |',
                $scenario['tela'] ?? '—',
                $scenario['capas'] ?? '—',
                $scenario['api_ms'] ?? '—',
                $scenario['primeira_capa_visivel_ms'] ?? '—',
                $scenario['todas_capas_visiveis_ms'] ?? '—',
                $scenario['todas_capas_sequencial_ms'] ?? '—',
            );
        }

        $lines[] = '';
        $lines[] = '## Capas encontradas na página';
        $lines[] = '';
        $lines[] = '| # | KB | HTTP mediana (ms) | Status | URL |';
        $lines[] = '| ---: | ---: | ---: | ---: | --- |';

        foreach ($report['vehicles'] as $row) {
            $url = (string) ($row['cover_photo_url'] ?? '');
            if (strlen($url) > 70) {
                $url = substr($url, 0, 67).'...';
            }

            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s |',
                $row['vehicle_id'],
                $row['size_kb'] ?? '—',
                $row['http_download_median_ms'] ?? '—',
                $row['http_status'] ?? '—',
                $url,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, float|null>  $values
     */
    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== null));
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = (int) floor(count($values) / 2);

        if (count($values) % 2 === 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
        }

        return round($values[$middle], 2);
    }
}
