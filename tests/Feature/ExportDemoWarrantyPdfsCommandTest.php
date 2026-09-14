<?php

namespace Tests\Feature;

use App\Models\Workshop;
use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Database\Seeders\DemoWorkshopAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportDemoWarrantyPdfsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_command_exports_valid_pdf_with_seed_option(): void
    {
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        $this->artisan('demo:export-warranty-pdfs', ['--seed' => true])
            ->assertExitCode(0);

        $absolutePath = storage_path('app/demo-exports/historico_'.DemoMaintenanceWarrantiesSeeder::PLATE.'_garantias.pdf');

        $this->assertFileExists($absolutePath);

        $content = file_get_contents($absolutePath);
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertTrue(str_contains($content, "\xFF\xD8\xFF"));
    }

    public function test_command_aborts_outside_local_and_testing(): void
    {
        $originalEnv = app()->environment();

        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->artisan('demo:export-warranty-pdfs')
                ->assertExitCode(1);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }
}
