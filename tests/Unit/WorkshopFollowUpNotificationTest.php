<?php

namespace Tests\Unit;

use App\Models\EmailSuppression;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageOptOut;
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
        $this->assertSame('RevisaLog', $mail->salutation);
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

    public function test_via_drops_every_channel_when_the_owner_opted_out(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);

        $this->assertSame([], $this->notificationFor($template)->via($owner));
    }

    public function test_via_keeps_the_app_notice_when_the_address_is_suppressed(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        EmailSuppression::suppress($owner->email, EmailSuppression::REASON_UNSUBSCRIBED);

        $this->assertSame(['database'], $this->notificationFor($template)->via($owner));
    }

    public function test_via_keeps_both_channels_for_an_owner_who_never_opted_out(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        $this->assertSame(['mail', 'database'], $this->notificationFor($template)->via($owner));
    }

    public function test_mail_links_the_signed_unsubscribe_route(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Central']);
        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        $notification = $this->notificationFor($template);

        // A carga é cifrada com IV aleatório, então cada chamada devolve uma URL diferente: o que
        // importa é que o e-mail leve uma assinada, e a mesma no corpo e no cabeçalho.
        $unsubscribeUrl = $notification->unsubscribeUrl($owner);
        $this->assertNotNull($unsubscribeUrl);
        $this->assertStringStartsWith(url('/oficinas/descadastrar').'?payload=', $unsubscribeUrl);
        $this->assertStringContainsString('&signature=', $unsubscribeUrl);

        $outro = implode(' ', $notification->toMail($owner)->outroLines);
        $this->assertStringContainsString('Oficina Central', $outro);
        $this->assertStringContainsString(url('/oficinas/descadastrar').'?payload=', $outro);
        $this->assertStringContainsString('&signature=', $outro);
    }

    private function notificationFor(WorkshopMessageTemplate $template): WorkshopFollowUpNotification
    {
        return new WorkshopFollowUpNotification(
            $template,
            Vehicle::factory()->create(),
            'Revisão próxima',
            'Seu veículo está perto da revisão.',
            ['workshop_name' => $template->workshop->name],
        );
    }
}
