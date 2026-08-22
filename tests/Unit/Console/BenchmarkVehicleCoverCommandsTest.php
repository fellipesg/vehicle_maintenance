<?php

namespace Tests\Unit\Console;

use App\Console\Commands\BenchmarkVehicleCoverCompare;
use App\Console\Commands\BenchmarkVehicleCoverPerformance;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BenchmarkVehicleCoverCommandsTest extends TestCase
{
    #[Test]
    public function compare_markdown_shows_pending_after_column_when_r2_missing(): void
    {
        $before = [
            'generated_at' => '2026-08-22T16:00:00+00:00',
            'storage' => ['covers_disk' => 's3'],
            'screen_load' => [
                'app_lista_veiculos' => [
                    'tela' => 'App — lista de veículos',
                    'capas' => 6,
                    'primeira_capa_visivel_ms' => 1500.0,
                    'todas_capas_visiveis_ms' => 8200.0,
                ],
            ],
            'summary' => ['http_download_median_avg_ms' => 4200.0],
        ];

        $markdown = (new BenchmarkVehicleCoverCompare)->toComparisonMarkdown(
            $before,
            null,
            's3-neon',
            'r2-cloudflare',
        );

        $this->assertStringContainsString('App — lista de veículos', $markdown);
        $this->assertStringContainsString('8.200', $markdown);
        $this->assertStringContainsString('—', $markdown);
    }

    #[Test]
    public function compare_markdown_calculates_improvement_when_after_exists(): void
    {
        $before = [
            'generated_at' => '2026-08-22T16:00:00+00:00',
            'storage' => ['covers_disk' => 's3'],
            'screen_load' => [
                'web_dashboard' => [
                    'tela' => 'Web — dashboard',
                    'capas' => 6,
                    'primeira_capa_visivel_ms' => 2000.0,
                    'todas_capas_visiveis_ms' => 10000.0,
                ],
            ],
            'summary' => ['http_download_median_avg_ms' => 5000.0],
        ];

        $after = [
            'generated_at' => '2026-08-22T17:00:00+00:00',
            'storage' => ['covers_disk' => 'r2'],
            'screen_load' => [
                'web_dashboard' => [
                    'tela' => 'Web — dashboard',
                    'capas' => 6,
                    'primeira_capa_visivel_ms' => 800.0,
                    'todas_capas_visiveis_ms' => 3000.0,
                ],
            ],
            'summary' => ['http_download_median_avg_ms' => 1500.0],
        ];

        $markdown = (new BenchmarkVehicleCoverCompare)->toComparisonMarkdown(
            $before,
            $after,
            's3-neon',
            'r2-cloudflare',
        );

        $this->assertStringContainsString('−7000 ms (−70.0%)', $markdown);
    }

    #[Test]
    public function performance_markdown_highlights_screen_load_table(): void
    {
        $report = [
            'label' => 's3-neon',
            'generated_at' => '2026-08-22T16:00:00+00:00',
            'storage' => [
                'covers_disk' => 's3',
                'driver' => 's3',
                'bucket' => 'vehicle-maintenance',
                'endpoint' => 'https://example.test',
            ],
            'screen_load' => [
                'app_lista_veiculos' => [
                    'tela' => 'App — lista de veículos (HomePage)',
                    'capas' => 6,
                    'api_ms' => 180.0,
                    'primeira_capa_visivel_ms' => 1400.0,
                    'todas_capas_visiveis_ms' => 8200.0,
                    'todas_capas_sequencial_ms' => 25000.0,
                ],
            ],
            'summary' => ['covers_count' => 10],
            'vehicles' => [],
        ];

        $markdown = (new BenchmarkVehicleCoverPerformance)->toMarkdown($report);

        $this->assertStringContainsString('Tempo de carregamento das telas', $markdown);
        $this->assertStringContainsString('**8200**', $markdown);
    }
}
