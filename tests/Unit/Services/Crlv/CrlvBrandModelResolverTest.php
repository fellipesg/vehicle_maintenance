<?php

namespace Tests\Unit\Services\Crlv;

use App\Services\Crlv\CrlvBrandModelResolver;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrlvBrandModelResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function brandModelProvider(): array
    {
        return [
            // Em importados o "I/" é marca de importação, não a montadora.
            'importado audi' => ['I/AUDI Q3 150CV', 'Audi', 'Q3'],
            'importado marca com duas palavras' => ['I/MERCEDES BENZ C 180', 'Mercedes-Benz', 'C 180'],
            'importado com barra dupla' => ['I/VW/AMAROK HIGHLINE', 'Volkswagen', 'Amarok'],
            'nacional' => ['VW/GOL 1.0', 'Volkswagen', 'Gol'],
            'abreviacao com ponto' => ['M.BENZ/C 180', 'Mercedes-Benz', 'C 180'],
        ];
    }

    #[DataProvider('brandModelProvider')]
    public function test_resolves_brand_and_model(string $line, string $brand, string $model): void
    {
        $resolved = app(CrlvBrandModelResolver::class)->resolve($line);

        $this->assertSame($brand, $resolved['brand']);
        $this->assertSame($model, $resolved['model']);
    }
}
