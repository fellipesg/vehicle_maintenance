<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageTemplate;
use App\Notifications\WorkshopFollowUpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopFollowUpNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_reply_to_uses_workshop_email_when_valid(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create([
            'name' => 'Oficina Central',
            'email' => 'oficina@example.com',
        ]);
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();
        $vehicle = Vehicle::factory()->create();

        $mail = (new WorkshopFollowUpNotification(
            $template,
            $vehicle,
            'Revisão próxima',
            'Seu veículo está perto da revisão.',
            ['workshop_name' => $workshop->name],
        ))->toMail($owner);

        $this->assertSame([['oficina@example.com', 'Oficina Central']], $mail->replyTo);
        $this->assertSame('Revisalog', $mail->salutation);
    }

    public function test_mail_reply_to_falls_back_to_support_when_workshop_email_is_invalid(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create([
            'name' => 'Oficina Sem E-mail',
            'email' => '',
        ]);
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();
        $vehicle = Vehicle::factory()->create();

        $mail = (new WorkshopFollowUpNotification(
            $template,
            $vehicle,
            'Revisão próxima',
            'Seu veículo está perto da revisão.',
            ['workshop_name' => $workshop->name],
        ))->toMail($owner);

        $this->assertSame([[
            (string) config('mail.reply_to.address'),
            (string) config('mail.reply_to.name'),
        ]], $mail->replyTo);
    }
}
