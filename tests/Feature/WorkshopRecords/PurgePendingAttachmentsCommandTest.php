<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Invoice;
use App\Models\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

class PurgePendingAttachmentsCommandTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    public function test_it_deletes_only_pending_attachments_older_than_the_retention_period(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();

        $old = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $recent = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $accepted = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $accepted->forceFill(['attachments_status' => Maintenance::ATTACHMENTS_ACCEPTED])->save();
        $ordinary = Maintenance::factory()->create();
        Invoice::factory()->create(['maintenance_id' => $ordinary->id]);

        $this->travel(-91)->days();
        foreach ([$old, $accepted, $ordinary] as $maintenance) {
            $maintenance->invoices()->update(['created_at' => now()]);
            $maintenance->photos()->update(['created_at' => now()]);
        }
        $this->travelBack();

        $this->artisan('maintenance:purge-pending-attachments')->assertSuccessful();

        $this->assertSame(0, $old->invoices()->count() + $old->photos()->count());
        $this->assertSame('declined', $old->fresh()->attachments_status);
        $this->assertNotNull(Maintenance::find($old->id), 'a OS fica, só os anexos saem');

        $this->assertSame(2, $recent->invoices()->count() + $recent->photos()->count());
        $this->assertSame('pending', $recent->fresh()->attachments_status);
        $this->assertSame(2, $accepted->invoices()->count() + $accepted->photos()->count());
        $this->assertSame(1, $ordinary->invoices()->count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'maintenance:purge-pending-attachments'));

        $this->assertCount(1, $events);
        $this->assertSame('30 3 * * *', $events->first()->expression);
        $this->assertNotNull(Schedule::class);
    }
}
