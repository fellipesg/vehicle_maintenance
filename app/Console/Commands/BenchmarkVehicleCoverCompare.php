<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BenchmarkVehicleCoverCompare extends Command
{
    protected $signature = 'benchmark:vehicle-covers-compare
        {--before=s3-neon : Label baseline (antes)}
        {--after=r2-cloudflare : Label pós-migração (depois)}
        {--output= : Caminho markdown extra (ex: vehicle-covers-comparison-prod.md)}';

    protected $description = 'Gera tabela comparativa de carregamento de telas entre dois storages';

    public function handle(): int
    {
        $beforeLabel = (string) $this->option('before');
        $afterLabel = (string) $this->option('after');
        $dir = storage_path('app/benchmarks');

        $beforePath = "{$dir}/vehicle-covers-{$beforeLabel}-latest.json";
        $afterPath = "{$dir}/vehicle-covers-{$afterLabel}-latest.json";

        if (! is_file($beforePath)) {
            $this->error("Baseline não encontrado: {$beforePath}");
            $this->line("Rode: php artisan benchmark:vehicle-covers --label={$beforeLabel}");

            return self::FAILURE;
        }

        $before = json_decode((string) file_get_contents($beforePath), true, 512, JSON_THROW_ON_ERROR);
        $after = is_file($afterPath)
            ? json_decode((string) file_get_contents($afterPath), true, 512, JSON_THROW_ON_ERROR)
            : null;

        $markdown = $this->toComparisonMarkdown($before, $after, $beforeLabel, $afterLabel);
        $outputPath = $dir.'/'.((string) ($this->option('output') ?: 'vehicle-covers-comparison.md'));
        file_put_contents($outputPath, $markdown);

        $this->newLine();
        $this->line($markdown);
        $this->newLine();
        $this->info("Comparação salva em: {$outputPath}");

        if ($after === null) {
            $this->warn("Baseline \"{$afterLabel}\" ainda não existe — coluna R2 ficará vazia até migrar e rodar o benchmark.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>|null  $after
     */
    public function toComparisonMarkdown(array $before, ?array $after, string $beforeLabel, string $afterLabel): string
    {
        $lines = [];
        $lines[] = '# Comparação — carregamento de telas (capas de veículos)';
        $lines[] = '';
        $lines[] = '| Storage | Label | Gerado em | Disco |';
        $lines[] = '| --- | --- | --- | --- |';
        $lines[] = sprintf(
            '| Antes | %s | %s | %s |',
            $beforeLabel,
            $before['generated_at'] ?? '—',
            $before['storage']['covers_disk'] ?? '—',
        );
        $lines[] = sprintf(
            '| Depois | %s | %s | %s |',
            $afterLabel,
            $after['generated_at'] ?? '— (pendente)',
            $after['storage']['covers_disk'] ?? '—',
        );
        $lines[] = '';
        $lines[] = '## Tempo até todas as capas visíveis (paralelo)';
        $lines[] = '';
        $lines[] = '| Tela | Capas | '.$beforeLabel.' (ms) | '.$afterLabel.' (ms) | Melhoria |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: |';

        $beforeScreens = $before['screen_load'] ?? [];
        $afterScreens = $after['screen_load'] ?? [];

        foreach ($beforeScreens as $key => $scenario) {
            $beforeMs = $scenario['todas_capas_visiveis_ms'] ?? null;
            $afterMs = $afterScreens[$key]['todas_capas_visiveis_ms'] ?? null;
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $scenario['tela'] ?? $key,
                $scenario['capas'] ?? '—',
                $this->formatMs($beforeMs),
                $this->formatMs($afterMs),
                $this->formatImprovement($beforeMs, $afterMs),
            );
        }

        $lines[] = '';
        $lines[] = '## Tempo até a primeira capa visível';
        $lines[] = '';
        $lines[] = '| Tela | '.$beforeLabel.' (ms) | '.$afterLabel.' (ms) | Melhoria |';
        $lines[] = '| --- | ---: | ---: | ---: |';

        foreach ($beforeScreens as $key => $scenario) {
            $beforeMs = $scenario['primeira_capa_visivel_ms'] ?? null;
            $afterMs = $afterScreens[$key]['primeira_capa_visivel_ms'] ?? null;
            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                $scenario['tela'] ?? $key,
                $this->formatMs($beforeMs),
                $this->formatMs($afterMs),
                $this->formatImprovement($beforeMs, $afterMs),
            );
        }

        $lines[] = '';
        $lines[] = '## Download HTTP médio por capa';
        $lines[] = '';
        $beforeAvg = $before['summary']['http_download_median_avg_ms'] ?? null;
        $afterAvg = $after['summary']['http_download_median_avg_ms'] ?? null;
        $lines[] = '| Métrica | '.$beforeLabel.' | '.$afterLabel.' | Melhoria |';
        $lines[] = '| --- | ---: | ---: | ---: |';
        $lines[] = sprintf(
            '| HTTP mediana média (ms) | %s | %s | %s |',
            $this->formatMs($beforeAvg),
            $this->formatMs($afterAvg),
            $this->formatImprovement($beforeAvg, $afterAvg),
        );

        return implode("\n", $lines);
    }

    private function formatMs(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 0, ',', '.');
    }

    private function formatImprovement(mixed $beforeMs, mixed $afterMs): string
    {
        if ($beforeMs === null || $afterMs === null || (float) $beforeMs <= 0) {
            return '—';
        }

        $before = (float) $beforeMs;
        $after = (float) $afterMs;
        $delta = $before - $after;
        $percent = round(($delta / $before) * 100, 1);

        if ($delta > 0) {
            return sprintf('−%.0f ms (−%.1f%%)', $delta, $percent);
        }

        if ($delta < 0) {
            return sprintf('+%.0f ms (+%.1f%%)', abs($delta), abs($percent));
        }

        return '0 ms';
    }
}
