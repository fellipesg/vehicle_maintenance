<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const MEASUREMENT_ID = 'G-TEST123456';

    public function test_script_is_rendered_on_home_when_measurement_id_is_set(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id='.self::MEASUREMENT_ID, false)
            ->assertSee("analytics_storage: 'granted'", false)
            ->assertSee("ad_storage: 'denied'", false)
            ->assertSee('allow_google_signals: false', false);
    }

    public function test_privacy_policy_discloses_google_analytics_cookies(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('cookies de medição do Google Analytics', false)
            ->assertSee('Google Analytics (medição de uso das páginas públicas)', false)
            ->assertDontSee('Usamos apenas cookies essenciais', false);

        $this->assertSame('2026-10-09', config('legal.privacy_version'));
    }

    public function test_script_is_rendered_on_guest_layout_pages(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->get(route('register'))->assertOk()->assertSee(self::MEASUREMENT_ID, false);
        $this->get(route('legal.privacy'))->assertOk()->assertSee(self::MEASUREMENT_ID, false);
    }

    public function test_script_is_absent_when_measurement_id_is_empty(): void
    {
        config(['services.google_analytics.measurement_id' => '']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_script_is_absent_on_authenticated_portal_pages(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);
        $user = User::factory()->create(['user_type' => 'user']);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_outreach_ref_fires_event_on_contact_page(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->get(route('contact.show', ['assunto' => 'partnership', 'ref' => 'abc']))
            ->assertSee("gtag('event', 'outreach_click'", false);

        $this->get(route('contact.show'))
            ->assertDontSee('outreach_click', false);
    }

    public function test_sign_up_event_appears_on_the_page_after_registration(): void
    {
        Mail::fake();
        Notification::fake();
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->post('/register', [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee("gtag('event', 'sign_up'", false);

        $this->get(route('user.dashboard'))
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_sign_up_event_is_absent_when_measurement_id_is_empty(): void
    {
        Mail::fake();
        Notification::fake();
        config(['services.google_analytics.measurement_id' => '']);

        $this->post('/register', [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->get(route('user.dashboard'))->assertDontSee('sign_up', false);
    }
}
