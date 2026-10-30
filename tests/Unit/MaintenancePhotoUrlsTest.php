<?php

namespace Tests\Unit;

use App\Models\MaintenancePhoto;
use App\Support\Maintenance\MaintenancePhotoPreview;
use App\Support\Maintenance\MaintenancePhotoUrls;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MaintenancePhotoUrlsTest extends TestCase
{
    public function test_expiry_is_the_end_of_the_next_ten_minute_window(): void
    {
        $this->assertSame(
            '2026-10-22 12:20:00',
            MaintenancePhotoUrls::expiresAt(CarbonImmutable::parse('2026-10-22 12:03:20', 'UTC'))->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-22 12:20:00',
            MaintenancePhotoUrls::expiresAt(CarbonImmutable::parse('2026-10-22 12:09:59', 'UTC'))->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-22 12:30:00',
            MaintenancePhotoUrls::expiresAt(CarbonImmutable::parse('2026-10-22 12:10:00', 'UTC'))->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_expiry_is_identical_inside_a_window_and_always_at_least_ten_minutes_ahead(): void
    {
        $start = CarbonImmutable::parse('2026-10-22 12:10:00', 'UTC');

        for ($second = 0; $second < 600; $second += 7) {
            $now = $start->addSeconds($second);
            $expiresAt = MaintenancePhotoUrls::expiresAt($now);

            $this->assertTrue($expiresAt->equalTo(MaintenancePhotoUrls::expiresAt($start)));
            $this->assertGreaterThanOrEqual(MaintenancePhotoUrls::TTL_SECONDS, $expiresAt->getTimestamp() - $now->getTimestamp());
            $this->assertLessThanOrEqual(2 * MaintenancePhotoUrls::TTL_SECONDS, $expiresAt->getTimestamp() - $now->getTimestamp());
        }
    }

    public function test_preview_prefers_the_first_before_and_first_after_of_the_vehicle(): void
    {
        $photos = collect([
            $this->photo(1, 'vehicle', 'before', 2),
            $this->photo(2, 'vehicle', 'before', 1),
            $this->photo(3, 'part', 'before', 0),
            $this->photo(4, 'vehicle', 'after', 5),
            $this->photo(5, 'vehicle', 'after', 3),
            $this->photo(6, 'vehicle', 'during', 0),
        ]);

        $this->assertSame([2, 5], MaintenancePhotoPreview::pick($photos)->pluck('id')->all());
    }

    public function test_preview_falls_back_to_the_first_two_after_photos(): void
    {
        $photos = collect([
            $this->photo(1, 'vehicle', 'before', 0),
            $this->photo(2, 'vehicle', 'after', 2),
            $this->photo(3, 'vehicle', 'after', 1),
            $this->photo(4, 'vehicle', 'after', 3),
        ]);

        $this->assertSame([3, 2], MaintenancePhotoPreview::pick($photos->reject(fn ($p) => $p->id === 1))->pluck('id')->all());
        $this->assertSame([1, 3], MaintenancePhotoPreview::pick($photos)->pluck('id')->all());
        $this->assertSame([], MaintenancePhotoPreview::pick(collect([$this->photo(9, 'part', 'after', 0)]))->all());
    }

    private function photo(int $id, string $subject, string $stage, int $sort): MaintenancePhoto
    {
        return (new MaintenancePhoto(['subject' => $subject, 'stage' => $stage, 'sort' => $sort]))->forceFill(['id' => $id]);
    }
}
