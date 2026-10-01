<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class PortugueseLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth');
        RateLimiter::clear('auth-web');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_application_runs_in_brazilian_portuguese(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('pt_BR', config('app.fallback_locale'));
        $this->assertSame('pt_BR', config('app.faker_locale'));
        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertMatchesRegularExpression('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', fake()->cpf());
    }

    public function test_portuguese_is_the_default_when_the_environment_does_not_set_a_locale(): void
    {
        $config = (string) file_get_contents(config_path('app.php'));
        $envExample = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("env('APP_LOCALE', 'pt_BR')", $config);
        $this->assertStringContainsString("env('APP_FALLBACK_LOCALE', 'pt_BR')", $config);
        $this->assertStringContainsString("env('APP_FAKER_LOCALE', 'pt_BR')", $config);
        $this->assertMatchesRegularExpression('/^APP_LOCALE=pt_BR$/m', $envExample);
        $this->assertMatchesRegularExpression('/^APP_FALLBACK_LOCALE=pt_BR$/m', $envExample);
        $this->assertMatchesRegularExpression('/^APP_FAKER_LOCALE=pt_BR$/m', $envExample);
    }

    /**
     * As duas configurações do PHPUnit (SQLite e Postgres) forçam pt_BR, para o APP_LOCALE=en de um
     * .env antigo não mudar o idioma da suíte.
     */
    public function test_both_phpunit_configurations_force_portuguese(): void
    {
        foreach (['phpunit.xml', 'phpunit.pgsql.xml'] as $configuration) {
            $xml = simplexml_load_file(base_path($configuration));
            $forced = [];

            foreach ($xml->php->env as $env) {
                if ((string) $env['force'] === 'true') {
                    $forced[(string) $env['name']] = (string) $env['value'];
                }
            }

            foreach (['APP_LOCALE', 'APP_FALLBACK_LOCALE', 'APP_FAKER_LOCALE'] as $name) {
                $this->assertSame('pt_BR', $forced[$name] ?? null, "{$configuration}: {$name}");
            }
        }
    }

    public function test_web_register_errors_are_in_portuguese_with_friendly_field_names(): void
    {
        User::factory()->asUser()->create(['email' => 'ja-cadastrado@example.com']);

        $this->from(route('register'))
            ->post('/register', [
                'email' => 'ja-cadastrado@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '1199',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'name' => 'O campo nome é obrigatório.',
                'email' => 'Este e-mail já está cadastrado.',
                'phone' => 'O campo telefone precisa ter pelo menos 10 caracteres.',
            ]);
    }

    public function test_web_login_rate_limit_message_is_in_portuguese_and_says_how_long_to_wait(): void
    {
        User::factory()->asUser()->create([
            'email' => 'limite@example.com',
            'password' => bcrypt('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login/usuario')->post('/login/usuario', [
                'email' => 'limite@example.com',
                'password' => 'senha-errada',
            ]);
        }

        $this->from('/login/usuario')
            ->post('/login/usuario', [
                'email' => 'limite@example.com',
                'password' => 'senha-errada',
            ])
            ->assertRedirect('/login/usuario')
            ->assertSessionHasErrors('email');

        $message = session('errors')->first('email');

        $this->assertMatchesRegularExpression('/^Muitas tentativas\. Aguarde (\d+) segundos? e tente de novo\.$/u', $message);
        $this->assertStringNotContainsString('Too many', $message);
    }

    public function test_owner_maintenance_with_workshop_and_no_invoice_explains_the_rule_in_portuguese(): void
    {
        Storage::fake('public');
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);
        $workshop = Workshop::factory()->create();

        $this->actingAs($owner)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshop->id,
                'maintenance_date' => '2026-01-01',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
            ])
            ->assertSessionHasErrors([
                'maintenance_type' => 'O campo tipo de manutenção é obrigatório.',
                'invoices' => 'Informe ao menos uma nota fiscal (PDF ou XML) ao vincular uma oficina cadastrada.',
            ]);
    }

    public function test_vehicle_edit_reports_taken_plate_renavam_and_oversized_cover_in_portuguese(): void
    {
        Storage::fake('public');
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $otherVehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), [
                'license_plate' => $otherVehicle->license_plate,
                'renavam' => $otherVehicle->renavam,
                'crv_number' => '123456789',
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis' => $vehicle->chassis,
                'cover' => UploadedFile::fake()->image('capa.jpg')->size(6000),
            ])
            ->assertSessionHasErrors([
                'license_plate' => 'Esta placa já está cadastrada.',
                'renavam' => 'Este RENAVAM já está cadastrado.',
                'cover' => 'O arquivo do campo capa paisagem não pode ter mais de 5120 KB.',
            ]);
    }

    public function test_workshop_profile_errors_use_portuguese_field_names(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshopUser->workshop->delete();
        $workshopUser->unsetRelation('workshop');

        $this->actingAs($workshopUser)
            ->post(route('workshop.profile.store'), [
                'name' => 'Mecânica do Bairro',
                'phone' => '11999999999',
                'cep' => '0131',
                'street' => 'Av Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SPA',
                'facebook' => 'nao-e-um-link',
            ])
            ->assertSessionHasErrors([
                'cep' => 'O campo CEP precisa ter 8 caracteres.',
                'state' => 'O campo estado precisa ter 2 caracteres.',
                'facebook' => 'O campo Facebook precisa ser uma URL válida.',
            ]);
    }

    public function test_api_validation_errors_are_in_portuguese(): void
    {
        $this->postJson('/api/v1/register', [
            'email' => 'nao-e-email',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'O campo nome é obrigatório.')
            ->assertJsonPath('errors.email.0', 'O campo e-mail precisa ser um endereço de e-mail válido.')
            ->assertJsonPath('errors.password.0', 'O campo senha é obrigatório.');
    }

    public function test_wildcard_and_related_field_names_are_translated(): void
    {
        $validator = Validator::make(
            ['items' => [['name' => '']], 'invoices' => ['nao-e-arquivo'], 'workshop_id' => 7],
            [
                'items.*.name' => 'required',
                'invoices.*' => 'file',
                'code' => 'required_without:recovery_code',
                'kilometers' => 'required_with:workshop_id',
            ],
        );

        $errors = $validator->errors();

        $this->assertSame('O campo nome do item é obrigatório.', $errors->first('items.0.name'));
        $this->assertSame('O campo nota fiscal precisa ser um arquivo.', $errors->first('invoices.0'));
        $this->assertSame(
            'O campo código é obrigatório quando código de recuperação não for informado.',
            $errors->first('code'),
        );
        $this->assertSame('O campo quilometragem é obrigatório quando oficina for informado.', $errors->first('kilometers'));
    }

    public function test_validation_summary_and_framework_strings_are_in_portuguese(): void
    {
        $exception = ValidationException::withMessages([
            'license_plate' => 'Esta placa já está cadastrada.',
            'renavam' => 'Este RENAVAM já está cadastrado.',
            'chassis' => 'Este chassi já está cadastrado.',
        ]);

        $this->assertSame('Esta placa já está cadastrada. (e mais 2 erros)', $exception->getMessage());
        $this->assertSame('Os dados enviados são inválidos.', __('The given data was invalid.'));
        $this->assertSame('E-mail ou senha incorretos.', __('auth.failed'));
        $this->assertSame('Este link de redefinição de senha é inválido ou expirou.', __('passwords.token'));
        $this->assertSame('Enviamos um link para redefinir sua senha. Confira seu e-mail.', __('passwords.sent'));
        $this->assertSame('&laquo; Anterior', __('pagination.previous'));
        $this->assertSame('Próxima &raquo;', __('pagination.next'));
        $this->assertSame('Mostrando', __('Showing'));
        $this->assertSame('Redefinir senha', __('Reset Password'));
        $this->assertSame('Página não encontrada', __('Not Found'));
    }

    public function test_notification_emails_use_portuguese_chrome(): void
    {
        $html = (string) (new MailMessage)
            ->line('Sua próxima revisão está chegando.')
            ->action('Ver veículo', 'https://revisalog.com.br/usuario/veiculos/1')
            ->render();

        $this->assertStringContainsString('lang="pt-BR"', $html);
        $this->assertStringContainsString('Olá!', $html);
        $this->assertStringContainsString('Atenciosamente,', $html);
        $this->assertStringContainsString('não funcionar, copie o endereço abaixo', $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Regards,', $html);
        $this->assertStringNotContainsString('having trouble', $html);
    }

    public function test_relative_dates_and_month_names_are_in_portuguese(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00'));

        $this->assertSame('pt_BR', Carbon::getLocale());
        $this->assertSame('há 2 horas', now()->subHours(2)->diffForHumans());
        $this->assertSame('em 3 dias', now()->addDays(3)->diffForHumans());
        $this->assertSame('29 de setembro de 2026', now()->translatedFormat('j \d\e F \d\e Y'));
        $this->assertSame('terça-feira', now()->translatedFormat('l'));
    }

    public function test_notification_bell_shows_relative_time_in_portuguese(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00'));
        $owner = User::factory()->asUser()->create();
        $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MaintenanceKmReminderNotification',
            'data' => ['title' => 'Revisão dos 40.000 km'],
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($owner);

        $this->blade('<x-notification-bell />')
            ->assertSee('Revisão dos 40.000 km')
            ->assertSee('há 2 horas')
            ->assertDontSee('hours ago');
    }

    #[RequiresPhpExtension('intl')]
    public function test_numbers_are_formatted_in_brazilian_portuguese(): void
    {
        $this->assertSame('pt_BR', Number::defaultLocale());
        $this->assertSame('40.000', Number::format(40000));
        $this->assertSame('1.234,5', Number::format(1234.5, precision: 1));
    }
}
