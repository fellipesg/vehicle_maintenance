<?php

namespace Tests\Feature\Web;

use App\Enums\RegistrationSource;
use App\Enums\WorkshopProspectStatus;
use App\Events\UserRegistered;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopProspect;
use App\Notifications\NewUserSignupAlertNotification;
use App\Rules\ValidCnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkshopSelfSignupTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '11222333000181';

    private const MEASUREMENT_ID = 'G-TEST123456';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Carlos Pereira',
            'email' => 'carlos@autocenter.example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'trade_name' => 'Auto Center Pereira',
            'cnpj' => '11.222.333/0001-81',
            'phone' => '(43) 99999-1234',
            'cep' => '86010-000',
            'street' => 'Rua das Flores',
            'number' => '120',
            'complement' => 'Sala 2',
            'neighborhood' => 'Centro',
            'city' => 'Londrina',
            'state' => 'pr',
        ], $overrides);
    }

    public function test_landing_renders_for_guests_with_signup_and_contact_ctas(): void
    {
        $this->get(route('workshops.landing'))
            ->assertOk()
            ->assertSee('O nome da sua oficina')
            ->assertSee('Selo da oficina')
            ->assertSee('Oficinas da rede')
            ->assertSee('Revisão programada')
            ->assertSee('Retorno após serviço')
            ->assertSee('Se no futuro houver planos pagos, avisamos com antecedência, e nada é cobrado sem a oficina contratar.')
            ->assertSee('Cadastrar minha oficina')
            ->assertSee('href="'.route('workshops.signup').'"', false)
            ->assertSee('Falar com a equipe')
            ->assertSee('href="'.route('contact.show', ['assunto' => 'partnership']).'"', false)
            ->assertSee('href="'.route('verification.lookup').'"', false);
    }

    public function test_landing_hides_signup_for_logged_in_users_and_offers_the_inicio(): void
    {
        $workshopUser = User::factory()->create(['user_type' => 'workshop']);

        $this->actingAs($workshopUser)
            ->get(route('workshops.landing'))
            ->assertOk()
            ->assertSee('Ir para o Início')
            ->assertSee('href="'.route('workshop.dashboard').'"', false)
            ->assertDontSee('Cadastrar minha oficina')
            ->assertDontSee(route('workshops.signup'), false);
    }

    public function test_landing_passes_a_valid_ref_to_the_signup_link_and_ignores_an_invalid_one(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create();

        $this->get(route('workshops.landing', ['ref' => $prospect->token]))
            ->assertOk()
            ->assertSee(e(route('workshops.signup', ['ref' => $prospect->token])), false);

        $this->get(route('workshops.landing', ['ref' => 'nao-existe']))
            ->assertOk()
            ->assertDontSee('nao-existe', false);
    }

    public function test_signup_page_renders_the_form_and_the_cep_autofill_hook(): void
    {
        $this->get(route('workshops.signup'))
            ->assertOk()
            ->assertSee('name="trade_name"', false)
            ->assertSee('name="cnpj"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('data-cep-autofill', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Ao cadastrar a oficina, você aceita os')
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertDontSee('name="ref"', false);
    }

    public function test_signup_page_prefills_from_a_valid_ref_as_editable_defaults(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create([
            'trade_name' => 'Mecânica do Zé',
            'cnpj' => self::CNPJ,
            'email' => 'ze@mecanica.example.com',
        ]);

        $this->get(route('workshops.signup', ['ref' => $prospect->token]))
            ->assertOk()
            ->assertSee('value="Mecânica do Zé"', false)
            ->assertSee('value="11.222.333/0001-81"', false)
            ->assertSee('value="ze@mecanica.example.com"', false)
            ->assertSee('name="ref" value="'.$prospect->token.'"', false);

        $this->get(route('workshops.signup', ['ref' => 'invalido']))
            ->assertOk()
            ->assertDontSee('Mecânica do Zé')
            ->assertDontSee('name="ref"', false);
    }

    public function test_signup_creates_user_tenant_and_workshop_and_logs_in(): void
    {
        Event::fake([UserRegistered::class]);

        $this->post(route('workshops.signup.store'), $this->payload())
            ->assertRedirect(route('workshop.dashboard'))
            ->assertSessionHas('success')
            ->assertSessionHas('analytics_event', 'sign_up')
            ->assertSessionHas('analytics_method', 'workshop');

        $user = User::where('email', 'carlos@autocenter.example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('workshop', $user->user_type);
        $this->assertNotNull($user->tenant_id);
        $this->assertSame('workshop', $user->tenant->type);

        $workshop = Workshop::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($user->tenant_id, $workshop->tenant_id);
        $this->assertSame('Auto Center Pereira', $workshop->name);
        $this->assertSame(self::CNPJ, $workshop->cnpj);
        $this->assertSame('43999991234', $workshop->phone);
        $this->assertSame('43999991234', $workshop->whatsapp);
        $this->assertSame('86010000', $workshop->cep);
        $this->assertSame('Rua das Flores', $workshop->street);
        $this->assertSame('PR', $workshop->state);
        $this->assertSame('carlos@autocenter.example.com', $workshop->email);
        $this->assertSame(1, Workshop::count());

        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->user->is($user) && $event->source === RegistrationSource::Web);

        $this->get(route('workshop.dashboard'))->assertOk();
    }

    public function test_signup_sends_the_team_alert_marked_as_workshop_and_the_workshop_welcome(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post(route('workshops.signup.store'), $this->payload())->assertRedirect(route('workshop.dashboard'));

        $user = User::where('email', 'carlos@autocenter.example.com')->firstOrFail();

        Notification::assertSentOnDemand(NewUserSignupAlertNotification::class, function (NewUserSignupAlertNotification $notification, array $channels, object $notifiable) use ($user) {
            $mail = $notification->toMail($notifiable);
            $text = implode(' ', array_map(fn ($line) => (string) $line, $mail->introLines));

            return $notification->user->is($user)
                && str_contains($mail->subject, 'Novo cadastro de oficina')
                && str_contains($mail->subject, 'Auto Center Pereira')
                && str_contains($text, 'Oficina')
                && str_contains($text, '11.222.333/0001-81')
                && str_contains($text, 'Londrina/PR');
        });

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) {
            return $mail->actionUrl() === route('workshop.dashboard')
                && $mail->actionLabel() === 'Ir para o Início'
                && str_contains($mail->render(), 'conta da sua oficina');
        });
    }

    public function test_signup_rejects_an_invalid_cnpj(): void
    {
        $this->from(route('workshops.signup'))
            ->post(route('workshops.signup.store'), $this->payload(['cnpj' => '11.222.333/0001-82']))
            ->assertRedirect(route('workshops.signup'))
            ->assertSessionHasErrors(['cnpj' => 'Informe um CNPJ válido, com 14 dígitos.']);

        $this->post(route('workshops.signup.store'), $this->payload(['cnpj' => '00000000000000']))
            ->assertSessionHasErrors('cnpj');
        $this->post(route('workshops.signup.store'), $this->payload(['cnpj' => '123']))
            ->assertSessionHasErrors('cnpj');

        $this->assertGuest();
        $this->assertDatabaseCount('workshops', 0);
        $this->assertSame(0, User::count());
    }

    public function test_signup_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'carlos@autocenter.example.com']);

        $this->post(route('workshops.signup.store'), $this->payload())
            ->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);

        $this->assertGuest();
        $this->assertDatabaseCount('workshops', 0);
    }

    public function test_signup_rejects_a_duplicate_cnpj(): void
    {
        Workshop::factory()->create(['cnpj' => self::CNPJ]);
        $before = User::count();

        $this->post(route('workshops.signup.store'), $this->payload())
            ->assertSessionHasErrors(['cnpj' => 'Já existe uma oficina cadastrada com este CNPJ. Se ela é sua, entre na sua conta ou fale com a equipe.']);

        $this->assertGuest();
        $this->assertSame($before, User::count());
    }

    public function test_signup_requires_the_main_fields_and_a_confirmed_password(): void
    {
        $this->post(route('workshops.signup.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password', 'trade_name', 'cnpj', 'phone', 'cep', 'street', 'number', 'neighborhood', 'city', 'state']);

        $this->post(route('workshops.signup.store'), $this->payload(['password_confirmation' => 'outra-senha']))
            ->assertSessionHasErrors('password');
        $this->post(route('workshops.signup.store'), $this->payload(['state' => 'XX']))
            ->assertSessionHasErrors('state');
        $this->post(route('workshops.signup.store'), $this->payload(['phone' => '123']))
            ->assertSessionHasErrors('phone');
    }

    public function test_signup_converts_the_prospect_of_the_ref_even_with_another_email_and_cnpj(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create(['cnpj' => '99999999000191', 'email' => 'outro@mecanica.example.com']);

        $this->post(route('workshops.signup.store'), $this->payload(['ref' => $prospect->token]))
            ->assertRedirect(route('workshop.dashboard'));

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->status);
        $this->assertNotNull($prospect->converted_at);
        $this->assertSame(Workshop::firstOrFail()->id, $prospect->workshop_id);
    }

    public function test_signup_converts_the_prospect_with_the_same_cnpj_without_a_ref(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create(['cnpj' => self::CNPJ, 'email' => 'outro@mecanica.example.com']);

        $this->post(route('workshops.signup.store'), $this->payload())->assertRedirect(route('workshop.dashboard'));

        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->fresh()->status);
    }

    public function test_signup_converts_the_prospect_with_the_same_email_through_the_observer(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create(['cnpj' => '99999999000191', 'email' => 'carlos@autocenter.example.com']);

        $this->post(route('workshops.signup.store'), $this->payload())->assertRedirect(route('workshop.dashboard'));

        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->fresh()->status);
    }

    public function test_signup_ignores_an_unknown_ref(): void
    {
        $other = WorkshopProspect::factory()->sent()->create(['cnpj' => '99999999000191']);

        $this->post(route('workshops.signup.store'), $this->payload(['ref' => 'desconhecido']))
            ->assertRedirect(route('workshop.dashboard'));

        $this->assertSame(WorkshopProspectStatus::Sent, $other->fresh()->status);
    }

    public function test_signup_is_throttled_like_the_owner_register(): void
    {
        Mail::fake();
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('workshops.signup.store'), ['email' => "a{$attempt}@example.com"]);
        }

        $this->post(route('workshops.signup.store'), $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('workshops', 0);
    }

    public function test_logged_in_users_are_redirected_away_from_the_signup(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'workshop']))
            ->get(route('workshops.signup'))
            ->assertRedirect();
    }

    public function test_owner_register_is_unchanged(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post('/register', [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('user.dashboard'))->assertSessionHas('analytics_event', 'sign_up')
            ->assertSessionMissing('analytics_method');

        $this->assertSame('user', User::where('email', 'maria@example.com')->firstOrFail()->user_type);

        $this->post('/logout');
        $this->post('/register', [
            'name' => 'Oficina Fake',
            'email' => 'fake@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'workshop',
        ])->assertSessionHasErrors('user_type');
    }

    public function test_cnpj_rule_checks_the_check_digits(): void
    {
        $this->assertTrue(ValidCnpj::isValid('11.222.333/0001-81'));
        $this->assertTrue(ValidCnpj::isValid('11444777000161'));
        $this->assertFalse(ValidCnpj::isValid('11222333000180'));
        $this->assertFalse(ValidCnpj::isValid('11111111111111'));
        $this->assertFalse(ValidCnpj::isValid('1122233300018'));
        $this->assertFalse(ValidCnpj::isValid(''));
    }

    public function test_new_pages_are_public_for_google_analytics_and_listed_in_the_sitemap(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->get(route('workshops.landing'))->assertOk()->assertSee(self::MEASUREMENT_ID, false);
        $this->get(route('workshops.signup'))->assertOk()->assertSee(self::MEASUREMENT_ID, false);

        $this->get(route('sitemap'))->assertOk()->assertSee(route('workshops.landing'), false);
    }

    public function test_landing_with_ref_fires_the_outreach_click_event(): void
    {
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->get(route('workshops.landing', ['ref' => 'abc']))->assertSee("gtag('event', 'outreach_click'", false);
        $this->get(route('workshops.landing'))->assertDontSee('outreach_click', false);
    }

    public function test_workshop_signup_fires_sign_up_with_the_workshop_method(): void
    {
        Mail::fake();
        Notification::fake();
        config(['services.google_analytics.measurement_id' => self::MEASUREMENT_ID]);

        $this->post(route('workshops.signup.store'), $this->payload())->assertRedirect(route('workshop.dashboard'));

        $this->get(route('workshop.dashboard'))
            ->assertOk()
            ->assertSee("gtag('event', 'sign_up', { method: 'workshop' })", false);

        $this->get(route('workshop.dashboard'))->assertDontSee('googletagmanager.com', false);
    }
}
