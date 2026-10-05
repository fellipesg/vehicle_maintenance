<?php

namespace Tests\Feature;

use App\Mail\IosAppLaunchedMail;
use App\Models\User;
use App\Models\UserAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IosAppLaunchTest extends TestCase
{
    use RefreshDatabase;

    private const APP_STORE_URL = 'https://apps.apple.com/br/app/revisalog/id6814863841';

    public function test_home_shows_the_official_app_store_badge_and_android_coming_soon(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.self::APP_STORE_URL.'"', false)
            ->assertSee('aria-label="Baixar o RevisaLog na App Store"', false)
            ->assertSee('Android em breve');
    }

    public function test_it_announces_once_to_owners_and_shops_but_not_workshops_or_test_accounts(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'dono@gmail.com', 'user_type' => 'user', 'name' => 'Ana Souza']);
        $shop = User::factory()->create(['email' => 'loja@outlook.com', 'user_type' => 'garage']);
        User::factory()->create(['email' => 'oficina@gmail.com', 'user_type' => 'workshop']);
        User::factory()->create(['email' => 'demo@vehicle-maintenance.test', 'user_type' => 'user']);
        User::factory()->create(['email' => 'qa@EXAMPLE.com', 'user_type' => 'user']);
        User::factory()->create(['email' => 'apagado-7@deleted.revisalog.invalid', 'user_type' => 'user']);

        $this->artisan('users:announce-ios-app', ['--pause-ms' => 0])
            ->expectsOutputToContain('Avisos enviados: 2. Falhas: 0.')
            ->assertSuccessful();

        Mail::assertSent(IosAppLaunchedMail::class, 2);
        Mail::assertSent(IosAppLaunchedMail::class, fn (IosAppLaunchedMail $mail): bool => $mail->hasTo('dono@gmail.com') && $mail->firstName() === 'Ana');
        Mail::assertSent(IosAppLaunchedMail::class, fn (IosAppLaunchedMail $mail): bool => $mail->hasTo('loja@outlook.com'));
        $this->assertSame(2, UserAnnouncement::query()->where('announcement', UserAnnouncement::IOS_APP_LAUNCH)->count());
        $this->assertTrue($owner->announcements()->exists() && $shop->announcements()->exists());

        Mail::fake();
        $this->artisan('users:announce-ios-app', ['--pause-ms' => 0])
            ->expectsOutputToContain('Avisos enviados: 0. Falhas: 0.')
            ->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_dry_run_counts_without_sending_or_recording(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'dono@gmail.com', 'user_type' => 'user']);

        $this->artisan('users:announce-ios-app', ['--dry-run' => true])
            ->expectsOutputToContain('Receberiam o aviso: 1 usuário(s)')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, UserAnnouncement::count());
    }

    public function test_a_failed_send_is_not_recorded_so_a_rerun_retries_it(): void
    {
        config(['mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1], 'mail.default' => 'broken']);
        User::factory()->create(['email' => 'dono@gmail.com', 'user_type' => 'user']);

        $this->artisan('users:announce-ios-app', ['--pause-ms' => 0])
            ->expectsOutputToContain('Avisos enviados: 0. Falhas: 1.')
            ->assertFailed();

        $this->assertSame(0, UserAnnouncement::count());
    }

    public function test_the_email_links_to_the_app_store_and_mentions_android(): void
    {
        $mail = new IosAppLaunchedMail(User::factory()->make(['name' => 'Bruno Lima']));

        $this->assertSame('O RevisaLog chegou ao iPhone', $mail->envelope()->subject);
        $mail->assertSeeInHtml(self::APP_STORE_URL);
        $mail->assertSeeInHtml('Olá, Bruno!');
        $mail->assertSeeInHtml('Android está a caminho');
        $mail->assertSeeInHtml('mesma conta que você já usa no site');
    }
}
