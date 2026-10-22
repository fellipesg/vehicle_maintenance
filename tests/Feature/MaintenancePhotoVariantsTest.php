<?php

namespace Tests\Feature;

use App\Jobs\ProcessMaintenancePhoto;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Services\Maintenance\MaintenanceAttachmentPurger;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Services\Maintenance\MaintenancePhotoVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsPhotoFixtures;
use Tests\TestCase;

class MaintenancePhotoVariantsTest extends TestCase
{
    use BuildsPhotoFixtures, RefreshDatabase;

    private User $workshopUser;

    private Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->workshopUser = User::factory()->asWorkshop()->create();
        $this->maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $this->workshopUser->workshop->id,
        ]);
    }

    private function upload(string $bytes, string $name = 'foto.jpg', string $subject = 'vehicle', string $stage = 'before')
    {
        $this->actingAsApiUser($this->workshopUser);

        return $this->postJson("/api/v1/maintenances/{$this->maintenance->id}/photos", [
            'photo' => UploadedFile::fake()->createWithContent($name, $bytes),
            'subject' => $subject,
            'stage' => $stage,
        ]);
    }

    public function test_upload_stores_a_master_without_gps_in_the_new_layout_and_dispatches_the_job(): void
    {
        Queue::fake();

        $this->upload($this->jpegWithExif(3000, 2000, 6))->assertCreated();

        $photo = MaintenancePhoto::firstOrFail();
        $this->assertMatchesRegularExpression(
            '#^maintenance-photos/'.$this->maintenance->id.'/[0-9a-f-]{36}/master\.jpg$#',
            $photo->path,
        );

        $master = Storage::disk('public')->get($photo->path);
        $this->assertSame('image/jpeg', getimagesizefromstring($master)['mime']);
        $this->assertStringNotContainsString('Exif', $master);
        $this->assertSame([1365, 2048], [$photo->width, $photo->height]);
        $this->assertSame(strlen($master), $photo->bytes);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $photo->dominant_color);
        $this->assertNull($photo->processed_at);
        $this->assertNull($photo->thumb_path);

        Queue::assertPushed(ProcessMaintenancePhoto::class, fn (ProcessMaintenancePhoto $job) => $job->photo->is($photo) && $job->afterCommit === true);
    }

    public function test_upload_response_falls_back_to_the_master_until_the_job_runs(): void
    {
        Queue::fake();

        $response = $this->upload($this->plainJpeg())->assertCreated();

        $response->assertJsonPath('data.processed', false);
        $variants = $response->json('data.variants');
        $this->assertSame($variants['master'], $variants['thumb']);
        $this->assertSame($variants['master'], $variants['display']);
        $response->assertJsonMissingPath('data.path');
        $response->assertJsonMissingPath('data.thumb_path');
    }

    public function test_job_creates_thumb_and_display_next_to_the_master(): void
    {
        $this->upload($this->plainJpeg(2400, 1800))->assertCreated();

        $photo = MaintenancePhoto::firstOrFail();
        $directory = dirname($photo->path);
        $extension = app(\App\Services\Maintenance\MaintenancePhotoImageProcessor::class)->supportsWebp() ? 'webp' : 'jpg';

        $this->assertTrue($photo->isProcessed());
        $this->assertSame("{$directory}/thumb.{$extension}", $photo->thumb_path);
        $this->assertSame("{$directory}/display.{$extension}", $photo->display_path);
        $this->assertSame([320, 240], array_slice(getimagesizefromstring(Storage::disk('public')->get($photo->thumb_path)), 0, 2));
        $this->assertSame([1280, 960], array_slice(getimagesizefromstring(Storage::disk('public')->get($photo->display_path)), 0, 2));
        $this->assertSame([2048, 1536], [$photo->width, $photo->height]);
        $this->assertTrue(Storage::disk('public')->exists($photo->path));
    }

    public function test_job_is_idempotent(): void
    {
        $this->upload($this->plainJpeg())->assertCreated();
        $photo = MaintenancePhoto::firstOrFail();
        $processedAt = $photo->processed_at;

        $this->travel(5)->minutes();
        app(MaintenancePhotoVariantGenerator::class)->generate($photo);

        $this->assertEquals($processedAt, $photo->fresh()->processed_at);
    }

    public function test_job_with_a_non_image_file_fails_without_retrying(): void
    {
        Storage::disk('public')->put('maintenance-photos/lixo.jpg', 'isto não é imagem');
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenance->id,
            'path' => 'maintenance-photos/lixo.jpg',
        ]);

        $job = new ProcessMaintenancePhoto($photo);
        $job->handle(app(MaintenancePhotoVariantGenerator::class));

        $this->assertFalse($photo->fresh()->isProcessed());
    }

    public function test_upload_of_a_corrupt_file_is_rejected(): void
    {
        Queue::fake();

        $this->upload('isto não é uma imagem')->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->assertSame(0, MaintenancePhoto::count());
        Queue::assertNothingPushed();
    }

    public function test_job_migrates_a_legacy_photo_to_the_new_layout_and_strips_exif(): void
    {
        Storage::disk('public')->put('maintenance-photos/7_abc.jpg', $this->jpegWithExif(800, 600, 1));
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenance->id,
            'path' => 'maintenance-photos/7_abc.jpg',
        ]);

        app(MaintenancePhotoVariantGenerator::class)->generate($photo);

        $photo->refresh();
        $this->assertNotNull($photo->storageDirectory());
        $this->assertStringStartsWith('maintenance-photos/'.$this->maintenance->id.'/', $photo->path);
        $this->assertStringNotContainsString('Exif', Storage::disk('public')->get($photo->path));
        $this->assertFalse(Storage::disk('public')->exists('maintenance-photos/7_abc.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($photo->thumb_path));
        $this->assertSame([800, 600], [$photo->width, $photo->height]);
    }

    public function test_service_delete_removes_every_file_of_a_new_photo(): void
    {
        $this->upload($this->plainJpeg())->assertCreated();
        $photo = MaintenancePhoto::firstOrFail();
        $directory = dirname($photo->path);
        Storage::disk('public')->assertExists($photo->thumb_path);

        app(MaintenancePhotoService::class)->delete($photo);

        $this->assertSame(0, MaintenancePhoto::count());
        $this->assertSame([], Storage::disk('public')->allFiles($directory));
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_model_delete_removes_files_of_a_legacy_photo(): void
    {
        Storage::disk('public')->put('maintenance-photos/9_old.jpg', 'x');
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenance->id,
            'path' => 'maintenance-photos/9_old.jpg',
        ]);

        $photo->delete();

        Storage::disk('public')->assertMissing('maintenance-photos/9_old.jpg');
    }

    public function test_deleting_the_maintenance_removes_the_photo_files(): void
    {
        $this->upload($this->plainJpeg())->assertCreated();
        $photo = MaintenancePhoto::firstOrFail();

        $this->maintenance->delete();

        $this->assertSame(0, MaintenancePhoto::count());
        $this->assertSame([], Storage::disk('public')->allFiles(dirname($photo->path)));
    }

    public function test_api_destroy_of_the_maintenance_removes_the_photo_files(): void
    {
        $this->upload($this->plainJpeg())->assertCreated();
        $photo = MaintenancePhoto::firstOrFail();

        $this->deleteJson("/api/v1/maintenances/{$this->maintenance->id}")->assertOk();

        $this->assertSame([], Storage::disk('public')->allFiles(dirname($photo->path)));
    }

    public function test_attachment_purger_removes_the_variants_too(): void
    {
        $this->upload($this->plainJpeg())->assertCreated();
        $photo = MaintenancePhoto::firstOrFail();

        app(MaintenanceAttachmentPurger::class)->purge($this->maintenance);

        $this->assertSame([], Storage::disk('public')->allFiles(dirname($photo->path)));
    }

    public function test_factory_processed_state_fills_the_variant_columns(): void
    {
        $photo = MaintenancePhoto::factory()->processed()->create(['maintenance_id' => $this->maintenance->id]);

        $this->assertTrue($photo->isProcessed());
        $this->assertNotNull($photo->storageDirectory());
        $this->assertSame(dirname($photo->path).'/thumb.webp', $photo->thumb_path);
    }

    public function test_backfill_command_queues_only_unprocessed_photos(): void
    {
        Queue::fake();
        $pending = MaintenancePhoto::factory()->count(3)->create(['maintenance_id' => $this->maintenance->id]);
        MaintenancePhoto::factory()->processed()->create(['maintenance_id' => $this->maintenance->id]);

        $this->artisan('photos:generate-variants', ['--chunk' => 2])
            ->expectsOutputToContain('Fotos enfileiradas: 3.')
            ->assertSuccessful();

        Queue::assertPushed(ProcessMaintenancePhoto::class, 3);
        foreach ($pending as $photo) {
            Queue::assertPushed(ProcessMaintenancePhoto::class, fn (ProcessMaintenancePhoto $job) => $job->photo->is($photo));
        }
    }

    public function test_backfill_command_sync_processes_photos_and_reports_failures(): void
    {
        Storage::disk('public')->put('maintenance-photos/1_ok.jpg', $this->plainJpeg());
        $ok = MaintenancePhoto::factory()->create(['maintenance_id' => $this->maintenance->id, 'path' => 'maintenance-photos/1_ok.jpg']);
        $broken = MaintenancePhoto::factory()->create(['maintenance_id' => $this->maintenance->id, 'path' => 'maintenance-photos/2_missing.jpg']);

        $this->artisan('photos:generate-variants', ['--sync' => true])
            ->expectsOutputToContain('Fotos processadas: 1. Falhas: 1.')
            ->assertFailed();

        $this->assertTrue($ok->fresh()->isProcessed());
        $this->assertFalse($broken->fresh()->isProcessed());
    }
}
