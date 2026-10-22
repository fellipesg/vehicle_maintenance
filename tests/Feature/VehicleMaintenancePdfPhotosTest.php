<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Services\Vehicle\VehiclePdfMaintenancePhotos;
use App\Support\DemoWarrantyPdfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fotos das OS no PDF do histórico: só quando o proprietário atual marcou export_include_photos,
 * antes/depois para ele e só as públicas para quem mais exporta (.ai/rules/pdfs.md).
 */
class VehicleMaintenancePdfPhotosTest extends TestCase
{
    use RefreshDatabase;

    private int $colorSeed = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_owner_with_the_preference_gets_the_before_and_after_photos(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 1);

        $this->assertSame(2, $this->embeddedPhotos($vehicle, $owner));
    }

    public function test_owner_falls_back_to_the_first_two_after_photos_without_a_pair(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        foreach (range(0, 2) as $sort) {
            $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: $sort);
        }

        $selection = $this->selectFor($vehicle, $owner, requesterIsOwner: true);
        $this->assertSame(2, $this->embeddedPhotos($vehicle, $owner));
        $this->assertSame([0, 1], array_map(fn (MaintenancePhoto $photo): int => $photo->sort, $selection[$maintenance->id]['photos']));
        $this->assertSame(3, $selection[$maintenance->id]['eligible']);
    }

    public function test_owner_sees_a_lone_before_photo_when_there_is_no_after(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $before = $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 1);

        $selection = $this->selectFor($vehicle, $owner, requesterIsOwner: true);

        $this->assertSame([$before->id], array_map(fn (MaintenancePhoto $photo): int => $photo->id, $selection[$maintenance->id]['photos']));
        $this->assertSame(2, $selection[$maintenance->id]['eligible']);
        $this->assertSame(1, $this->embeddedPhotos($vehicle, $owner));
    }

    public function test_pair_uses_the_first_before_and_the_first_after_by_sort(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $lateBefore = $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 3);
        $firstBefore = $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 1);
        $firstAfter = $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 2);

        $selection = $this->selectFor($vehicle, $owner, requesterIsOwner: true);

        $this->assertSame(
            [$firstBefore->id, $firstAfter->id],
            array_map(fn (MaintenancePhoto $photo): int => $photo->id, $selection[$maintenance->id]['photos']),
        );
        $this->assertNotContains($lateBefore->id, array_map(fn (MaintenancePhoto $photo): int => $photo->id, $selection[$maintenance->id]['photos']));
    }

    public function test_non_owner_requester_gets_only_public_photos(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_DURING, sort: 1);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 2, subject: MaintenancePhoto::SUBJECT_PART);
        $after = $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 3);
        $admin = User::factory()->asAdmin()->create();

        $selection = $this->selectFor($vehicle, $admin, requesterIsOwner: false);
        $this->assertSame(1, $this->embeddedPhotos($vehicle, $admin, masked: true));
        $this->assertSame([$after->id], array_map(fn (MaintenancePhoto $photo): int => $photo->id, $selection[$maintenance->id]['photos']));
        $this->assertSame(1, $selection[$maintenance->id]['eligible']);
    }

    public function test_requester_without_a_user_gets_only_public_photos(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 1);

        $this->assertSame(1, $this->embeddedPhotos($vehicle, null, masked: true));
    }

    public function test_no_maintenance_photos_when_the_preference_is_false_or_null(): void
    {
        foreach ([false, null] as $preference) {
            [$owner, $vehicle] = $this->ownerWithVehicle($preference);
            $maintenance = $this->maintenanceFor($vehicle, $owner);
            $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
            $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 1);

            $this->assertSame(0, $this->embeddedPhotos($vehicle, $owner));
            $this->assertSame(0, $this->embeddedPhotos($vehicle, User::factory()->asAdmin()->create(), masked: true));
        }
    }

    public function test_only_the_current_owner_preference_counts(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(false);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 0);

        $formerOwner = User::factory()->asUser()->create(['export_include_photos' => true])->refresh();
        $formerOwner->vehicles()->attach($vehicle->id, ['purchase_date' => now()->subYear(), 'is_current_owner' => false, 'tenant_id' => $formerOwner->tenant_id]);

        $this->assertSame(0, $this->embeddedPhotos($vehicle, $owner));
    }

    public function test_redacted_ownerless_records_keep_showing_no_photos(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $maintenance->forceFill(['owner_status' => Maintenance::OWNER_PENDING, 'attachments_status' => Maintenance::ATTACHMENTS_PENDING])->save();
        $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
        $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 1);

        $this->assertSame(0, $this->embeddedPhotos($vehicle, $owner));
    }

    public function test_caps_photos_per_maintenance_and_per_pdf(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenances = collect(range(1, 10))->map(function (int $day) use ($vehicle, $owner): Maintenance {
            $maintenance = $this->maintenanceFor($vehicle, $owner, now()->subDays($day));
            $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 0);
            $this->photo($maintenance, MaintenancePhoto::STAGE_BEFORE, sort: 1);
            $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 2);
            $this->photo($maintenance, MaintenancePhoto::STAGE_AFTER, sort: 3);

            return $maintenance;
        });

        $selection = $this->selectFor($vehicle, $owner, requesterIsOwner: true);

        foreach ($selection as $entry) {
            $this->assertLessThanOrEqual(VehiclePdfMaintenancePhotos::MAX_PER_MAINTENANCE, count($entry['photos']));
        }
        $this->assertSame(
            VehiclePdfMaintenancePhotos::MAX_PER_PDF,
            array_sum(array_map(fn (array $entry): int => count($entry['photos']), $selection)),
        );
        // As OS mais recentes ganham as fotos primeiro (a mais antiga, a última, fica sem).
        $this->assertArrayHasKey($maintenances->first()->id, $selection);
        $this->assertSame([], $selection[$maintenances->last()->id]['photos']);
        $this->assertSame(VehiclePdfMaintenancePhotos::MAX_PER_PDF, $this->embeddedPhotos($vehicle, $owner));
    }

    public function test_prefers_display_path_and_falls_back_to_path(): void
    {
        $photos = app(VehiclePdfMaintenancePhotos::class);

        $withoutVariant = new MaintenancePhoto(['path' => 'maintenance-photos/original.jpg']);
        $this->assertSame('maintenance-photos/original.jpg', $photos->pathFor($withoutVariant));

        $withVariant = new MaintenancePhoto(['path' => 'maintenance-photos/original.jpg']);
        $withVariant->setRawAttributes(['path' => 'maintenance-photos/original.jpg', 'display_path' => 'maintenance-photos/display.jpg']);
        $this->assertSame('maintenance-photos/display.jpg', $photos->pathFor($withVariant));

        $emptyVariant = new MaintenancePhoto;
        $emptyVariant->setRawAttributes(['path' => 'maintenance-photos/original.jpg', 'display_path' => '']);
        $this->assertSame('maintenance-photos/original.jpg', $photos->pathFor($emptyVariant));
    }

    public function test_template_renders_captions_and_the_extra_photos_line_with_the_seal_link(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $html = $this->renderView($vehicle, [
            $maintenance->id => [
                'items' => [
                    ['src' => 'data:image/jpeg;base64,AAAA', 'width' => 300, 'height' => 200, 'label' => 'Antes'],
                    ['src' => 'data:image/jpeg;base64,BBBB', 'width' => 300, 'height' => 200, 'label' => 'Depois'],
                ],
                'extra' => 3,
            ],
        ]);

        $this->assertStringContainsString('Fotos do serviço', $html);
        $this->assertStringContainsString('<div class="photo-caption">Antes</div>', $html);
        $this->assertStringContainsString('<div class="photo-caption">Depois</div>', $html);
        $this->assertStringContainsString('width="300" height="200"', $html);
        $this->assertStringContainsString('+3 fotos no RevisaLog', $html);
        $this->assertStringContainsString('revisalog.com.br/v/'.$maintenance->verification_code, $html);
    }

    public function test_template_uses_the_singular_and_omits_the_line_without_extra_photos(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle(true);
        $maintenance = $this->maintenanceFor($vehicle, $owner);
        $item = ['src' => 'data:image/jpeg;base64,AAAA', 'width' => 300, 'height' => 200, 'label' => 'Depois'];

        $single = $this->renderView($vehicle, [$maintenance->id => ['items' => [$item], 'extra' => 1]]);
        $this->assertStringContainsString('+1 foto no RevisaLog', $single);

        $none = $this->renderView($vehicle, [$maintenance->id => ['items' => [$item], 'extra' => 0]]);
        $this->assertStringNotContainsString('no RevisaLog', str_replace('Confira', '', explode('photo-more">', $none.'photo-more">')[1] ?? ''));
        $this->assertStringNotContainsString('<div class="photo-more">', $none);

        $this->assertStringNotContainsString('Fotos do serviço', $this->renderView($vehicle, []));
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(?bool $preference): array
    {
        $owner = User::factory()->asUser()->create(['export_include_photos' => $preference])->refresh();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner, $vehicle];
    }

    private function maintenanceFor(Vehicle $vehicle, User $owner, ?\DateTimeInterface $date = null): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_date' => $date ?? now()->subDay(),
        ]);
    }

    private function photo(Maintenance $maintenance, string $stage, int $sort, string $subject = MaintenancePhoto::SUBJECT_VEHICLE): MaintenancePhoto
    {
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => $subject,
            'stage' => $stage,
            'sort' => $sort,
            'created_by' => $maintenance->user_id,
        ]);
        Storage::disk('public')->put($photo->path, $this->photoJpeg());

        return $photo;
    }

    /**
     * Uma cor diferente por foto: o Dompdf grava imagens idênticas uma vez só.
     */
    private function photoJpeg(): string
    {
        $this->colorSeed++;
        $image = imagecreatetruecolor(1200, 800);
        imagefilledrectangle($image, 0, 0, 1199, 799, imagecolorallocate($image, ($this->colorSeed * 37) % 256, ($this->colorSeed * 91) % 256, ($this->colorSeed * 53) % 256));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Imagens a mais no PDF por causa das fotos das OS, comparando com o mesmo PDF sem nenhuma.
     */
    private function embeddedPhotos(Vehicle $vehicle, ?User $viewer, bool $masked = false): int
    {
        $exporter = app(VehicleMaintenancePdfExporter::class);

        $withPhotos = $exporter->generate($vehicle->fresh(), $masked, $viewer);
        $withCount = DemoWarrantyPdfValidator::embeddedImageCount($withPhotos['content']);
        $exporter->cleanupTemps($withPhotos['temps']);

        MaintenancePhoto::query()->whereIn('maintenance_id', $vehicle->maintenances()->select('id'))->delete();
        $baseline = $exporter->generate($vehicle->fresh(), $masked, $viewer);
        $baseCount = DemoWarrantyPdfValidator::embeddedImageCount($baseline['content']);
        $exporter->cleanupTemps($baseline['temps']);

        return $withCount - $baseCount;
    }

    /**
     * @return array<int, array{photos: list<MaintenancePhoto>, eligible: int}>
     */
    private function selectFor(Vehicle $vehicle, ?User $viewer, bool $requesterIsOwner): array
    {
        $vehicle = $vehicle->fresh();
        $vehicle->load(['maintenances.photos' => fn ($query) => $query
            ->where('subject', MaintenancePhoto::SUBJECT_VEHICLE)
            ->whereIn('stage', [MaintenancePhoto::STAGE_BEFORE, MaintenancePhoto::STAGE_AFTER])]);

        return app(VehiclePdfMaintenancePhotos::class)
            ->select($vehicle->maintenances->sortByDesc('maintenance_date')->values(), $requesterIsOwner);
    }

    /**
     * @param  array<int, mixed>  $maintenancePhotos
     */
    private function renderView(Vehicle $vehicle, array $maintenancePhotos): string
    {
        $vehicle = $vehicle->fresh();
        $vehicle->load([
            'maintenances.items.warranty',
            'maintenances.generalWarranty',
            'maintenances.invoices',
            'maintenances.workshop',
            'maintenances.verifiedWorkshop',
        ]);

        return view('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'maintenancePhotos' => $maintenancePhotos,
            'coverImageSrc' => null,
            'workshopLogos' => [],
            'workshopLogoWidth' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH,
            'workshopLogoHeight' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT,
            'revisalogCoverLogoSrc' => null,
            'revisalogLogoSrc' => null,
        ])->render();
    }
}
