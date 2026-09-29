<?php

namespace Tests\Unit;

use App\Notifications\AccountEmailChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class AccountEmailChangedNotificationTest extends TestCase
{
    public function test_warns_the_old_address_in_portuguese_with_the_new_one_masked(): void
    {
        $notification = new AccountEmailChangedNotification('Ana Lima', 'ana.nova@example.com');
        $notifiable = (new AnonymousNotifiable)->route('mail', 'ana@example.com');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via($notifiable));

        $mail = $notification->toMail($notifiable);

        $this->assertSame('O e-mail da sua conta na RevisaLog foi alterado', $mail->subject);
        $this->assertSame('Olá, Ana!', $mail->greeting);
        $this->assertSame('RevisaLog', $mail->salutation);
        $this->assertSame([[config('mail.reply_to.address'), config('mail.reply_to.name')]], $mail->replyTo);
        $this->assertNull($mail->actionUrl);

        $html = (string) $mail->render();

        $this->assertStringContainsString('an***@example.com', $html);
        $this->assertStringNotContainsString('ana.nova@example.com', $html);
        $this->assertStringContainsString('Se não foi você', $html);
        $this->assertStringNotContainsString('Hello', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }

    public function test_short_addresses_and_blank_names(): void
    {
        $this->assertSame('a***@example.com', AccountEmailChangedNotification::maskEmail('a@example.com'));
        $this->assertSame('a***@example.com', AccountEmailChangedNotification::maskEmail('ab@example.com'));
        $this->assertSame('ab***@example.com', AccountEmailChangedNotification::maskEmail('abc@example.com'));

        $mail = (new AccountEmailChangedNotification('  ', 'novo@example.com'))->toMail(new AnonymousNotifiable);

        $this->assertSame('Olá!', $mail->greeting);
    }
}
