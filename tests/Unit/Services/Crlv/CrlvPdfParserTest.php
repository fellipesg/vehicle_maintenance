<?php

namespace Tests\Unit\Services\Crlv;

use App\Services\Crlv\CrlvPdfParser;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrlvPdfParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
    }

    /**
     * @return array<string, array{array{
     *     fixture: string,
     *     license_plate: string,
     *     renavam: string,
     *     crv_number: string,
     *     exercise_year: int,
     *     brand: string,
     *     model: string,
     *     year: int,
     *     color: string,
     *     chassis: string,
     *     engine: string,
     *     detran_state: string,
     *     motorization: ?string
     * }}>
     */
    public static function crlvFixtureProvider(): array
    {
        return [
            'parana_mercedes_c180' => [[
                'fixture' => 'divesa_c180_pr.pdf',
                'license_plate' => 'QOS6H54',
                'renavam' => '01159110473',
                'crv_number' => '244043259050',
                'exercise_year' => 2025,
                'brand' => 'Mercedes-Benz',
                'model' => 'C 180',
                'year' => 2018,
                'color' => 'PRETA',
                'chassis' => '9BMWF4AW9JM008903',
                'engine' => '27491031429700',
                'detran_state' => 'PR',
                'motorization' => '156CV',
            ]],
            'mato_grosso_do_sul_honda_civic' => [[
                'fixture' => 'honda_civic_ms.pdf',
                'license_plate' => 'PHF9J95',
                'renavam' => '01050047521',
                'crv_number' => '264600365712',
                'exercise_year' => 2026,
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2016,
                'color' => 'PRETA',
                'chassis' => '93HFB9640GZ202125',
                'engine' => 'R20Z5-6401964',
                'detran_state' => 'MS',
                'motorization' => '155CV 2L',
            ]],
        ];
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_parses_crlv_pdf(array $expected): void
    {
        $parsed = app(CrlvPdfParser::class)->parseFile(
            base_path('tests/fixtures/crlv/'.$expected['fixture'])
        );

        $this->assertSame($expected['license_plate'], $parsed->licensePlate);
        $this->assertSame($expected['renavam'], $parsed->renavam);
        $this->assertSame($expected['crv_number'], $parsed->crvNumber);
        $this->assertSame($expected['exercise_year'], $parsed->exerciseYear);
        $this->assertSame($expected['brand'], $parsed->brand);
        $this->assertSame($expected['model'], $parsed->model);
        $this->assertSame($expected['year'], $parsed->year);
        $this->assertSame($expected['color'], $parsed->color);
        $this->assertSame($expected['chassis'], $parsed->chassis);
        $this->assertSame($expected['engine'], $parsed->engine);
        $this->assertSame($expected['motorization'], $parsed->motorization);
        $this->assertTrue($parsed->brandMatched);
        $this->assertTrue($parsed->modelMatched);
        $this->assertSame($expected['detran_state'], $parsed->detranState);
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_recognizes_crlv_document(array $expected): void
    {
        $this->assertTrue(
            app(CrlvPdfParser::class)->isCrlvDocument(
                base_path('tests/fixtures/crlv/'.$expected['fixture'])
            )
        );
    }
}
