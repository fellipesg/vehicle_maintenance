<?php

namespace Tests\Feature;

use App\Models\Workshop;
use App\Support\AppStorage;
use App\Support\DemoWorkshopLogoGenerator;
use Database\Seeders\DemoWorkshopAccountsSeeder;
use Database\Seeders\DemoWorkshopLogosSeeder;
use Database\Seeders\DevPortalUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoWorkshopLogosSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCoversDisk('r2');
    }

    public function test_seeder_sets_logo_path_and_file_on_covers_disk(): void
    {
        Http::preventStrayRequests();

        $portraitJpeg = DemoWorkshopLogoGenerator::jpeg('Portrait', [0, 51, 153]);
        Http::fake([
            'picsum.photos/*' => Http::response($portraitJpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        (new DevPortalUsersSeeder)->run();

        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::DIVESA_NAME]);
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        (new DemoWorkshopAccountsSeeder)->run();

        $seeder = new DemoWorkshopLogosSeeder;
        $seeder->run();
        $seeder->run();

        foreach (DemoWorkshopLogosSeeder::workshops() as $config) {
            $workshop = Workshop::query()->where('name', $config['name'])->first();
            $this->assertNotNull($workshop, "Workshop {$config['name']} should exist.");
            $this->assertNotNull($workshop->logo_path);
            $this->assertStringStartsWith(AppStorage::WORKSHOP_LOGOS_PREFIX, $workshop->logo_path);
            $this->assertTrue(AppStorage::isWorkshopLogoPath($workshop->logo_path));
            $this->assertTrue(AppStorage::usesCoversDisk($workshop->logo_path));
            Storage::disk('r2')->assertExists($workshop->logo_path);

            $bytes = Storage::disk('r2')->get($workshop->logo_path);
            $this->assertIsString($bytes);
            $this->assertStringStartsWith("\xFF\xD8\xFF", $bytes);

            $size = getimagesizefromstring($bytes);
            $this->assertIsArray($size);
            $this->assertGreaterThan($size[0], $size[1], 'Demo workshop logos should be portrait (height > width).');
            $this->assertSame(DemoWorkshopLogoGenerator::WIDTH, $size[0]);
            $this->assertSame(DemoWorkshopLogoGenerator::HEIGHT, $size[1]);
        }

        Http::assertSentCount(count(DemoWorkshopLogosSeeder::workshops()) * 2);
    }

    public function test_seeder_falls_back_to_gd_when_picsum_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'picsum.photos/*' => Http::response('not-a-jpeg', 200, ['Content-Type' => 'text/plain']),
        ]);

        (new DevPortalUsersSeeder)->run();

        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::DIVESA_NAME]);
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        (new DemoWorkshopAccountsSeeder)->run();

        (new DemoWorkshopLogosSeeder)->run();

        foreach (DemoWorkshopLogosSeeder::workshops() as $config) {
            $workshop = Workshop::query()->where('name', $config['name'])->first();
            $this->assertNotNull($workshop);
            $this->assertNotNull($workshop->logo_path);
            Storage::disk('r2')->assertExists($workshop->logo_path);

            $bytes = Storage::disk('r2')->get($workshop->logo_path);
            $this->assertIsString($bytes);
            $this->assertStringStartsWith("\xFF\xD8\xFF", $bytes);

            $size = getimagesizefromstring($bytes);
            $this->assertIsArray($size);
            $this->assertGreaterThan($size[0], $size[1], 'Fallback GD logos should be portrait (height > width).');
            $this->assertSame(DemoWorkshopLogoGenerator::WIDTH, $size[0]);
            $this->assertSame(DemoWorkshopLogoGenerator::HEIGHT, $size[1]);
        }
    }

    public function test_seeder_skips_missing_workshops_without_exploding(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $seeder = new DemoWorkshopLogosSeeder;
        $seeder->run();

        $this->assertSame(0, Workshop::query()->whereNotNull('logo_path')->count());
        Http::assertNothingSent();
    }
}
