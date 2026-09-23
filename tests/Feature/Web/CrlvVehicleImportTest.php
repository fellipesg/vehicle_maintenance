<?php

namespace Tests\Feature\Web;

use App\Mail\CrlvImportFailureMail;
use App\Models\User;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvParseException;
use App\Services\Crlv\CrlvPdfParser;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CrlvVehicleImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->user = User::factory()->asUser()->create();
    }

    /**
     * @return array<string, array{array{
     *     fixture: string,
     *     license_plate: string,
     *     renavam: string,
     *     crv_number: string,
     *     brand: string,
     *     model: string,
     *     year: int,
     *     color: string,
     *     chassis: string,
     *     engine: string,
     *     motorization: ?string
     * }}>
     */
    public static function crlvFixtureProvider(): array
    {
        return [
            'parana_mercedes_c180' => [[
                'fixture' => 'divesa_c180_pr.pdf',
                'license_plate' => 'QOS6H54',
                'renavam' => '01159110473',
                'crv_number' => '244043259050',
                'brand' => 'Mercedes-Benz',
                'model' => 'C 180',
                'year' => 2018,
                'color' => 'PRETA',
                'chassis' => '9BMWF4AW9JM008903',
                'engine' => '27491031429700',
                'motorization' => '156CV',
            ]],
            'mato_grosso_do_sul_honda_civic' => [[
                'fixture' => 'honda_civic_ms.pdf',
                'license_plate' => 'PHF9J95',
                'renavam' => '01050047521',
                'crv_number' => '264600365712',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2016,
                'color' => 'PRETA',
                'chassis' => '93HFB9640GZ202125',
                'engine' => 'R20Z5-6401964',
                'motorization' => '155CV 2L',
            ]],
        ];
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_user_can_import_crlv_and_see_preview(array $expected): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/'.$expected['fixture']),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.import.preview'))
            ->assertSessionHas('crlv_verification.parsed.license_plate', $expected['license_plate'])
            ->assertSessionHas('crlv_verification.parsed.renavam', $expected['renavam'])
            ->assertSessionHas('crlv_verification.parsed.brand', $expected['brand'])
            ->assertSessionHas('crlv_verification.parsed.model', $expected['model']);

        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertOk()
            ->assertSee($expected['license_plate'])
            ->assertSee($expected['brand'])
            ->assertSee('Confirmar e salvar veículo');
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_user_can_confirm_imported_vehicle(array $expected): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/'.$expected['fixture']),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $token = session('crlv_verification.token');

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', [
                'license_plate' => $expected['license_plate'],
                'renavam' => $expected['renavam'],
                'crv_number' => $expected['crv_number'],
                'brand' => $expected['brand'],
                'model' => $expected['model'],
                'year' => $expected['year'],
                'color' => $expected['color'],
                'chassis' => $expected['chassis'],
                'engine' => $expected['engine'],
                'motorization' => $expected['motorization'],
                'current_kilometers' => 50_000,
                'terms_accepted' => '1',
                'crlv_verification_token' => $token,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => $expected['license_plate'],
            'renavam' => $expected['renavam'],
            'brand' => $expected['brand'],
            'model' => $expected['model'],
        ]);
    }

    public function test_rejects_non_crlv_pdf(): void
    {
        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors('crlv');
    }

    public function test_claim_import_without_existing_vehicle_goes_to_preview(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post('/usuario/veiculos/vincular/crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.import.preview'))
            ->assertSessionHas('crlv_verification.parsed.license_plate', 'PHF9J95')
            ->assertSessionMissing('claim_vehicle_id');

        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertOk()
            ->assertSee('PHF9J95')
            ->assertSee('01050047521')
            ->assertSee('Confirmar e salvar veículo');
    }

    public function test_garage_claim_import_without_existing_vehicle_goes_to_preview(): void
    {
        $garage = User::factory()->asGarage()->create();

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($garage)
            ->post('/garagem/estoque/vincular/crlv', ['crlv' => $file])
            ->assertRedirect(route('garage.vehicles.import.preview'))
            ->assertSessionHas('crlv_verification.parsed.license_plate', 'PHF9J95')
            ->assertSessionMissing('claim_vehicle_id');

        $this->actingAs($garage)
            ->get('/garagem/estoque/importar-crlv/preview')
            ->assertOk()
            ->assertSee('PHF9J95');
    }

    /**
     * O formulário de confirmação precisa carregar tudo que o cadastro exige:
     * sem o aceite dos termos ou sem o token, o botão "Confirmar" não salva nada.
     */
    #[DataProvider('previewPageProvider')]
    public function test_preview_form_carries_terms_and_verification_token(string $role, string $importPath, string $previewPath): void
    {
        $actor = $role === 'garage'
            ? User::factory()->asGarage()->create()
            : $this->user;

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($actor)->post($importPath, ['crlv' => $file]);

        $this->actingAs($actor)
            ->get($previewPath)
            ->assertOk()
            ->assertSee('name="terms_accepted"', false)
            ->assertSee('name="crlv_verification_token"', false)
            ->assertSee(session('crlv_verification.token'), false);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function previewPageProvider(): array
    {
        return [
            'usuario' => ['user', '/usuario/veiculos/importar-crlv', '/usuario/veiculos/importar-crlv/preview'],
            'garagem' => ['garage', '/garagem/estoque/importar-crlv', '/garagem/estoque/importar-crlv/preview'],
        ];
    }

    /**
     * Regressão: submeter exatamente os campos renderizados na tela de
     * confirmação precisa gravar o veículo, sem erro de validação.
     */
    public function test_confirming_the_rendered_preview_form_saves_the_vehicle(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $html = $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->getContent();

        $payload = $this->formPayloadFrom($html);
        $payload['current_kilometers'] = 50_000;

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
        ]);
    }

    /**
     * Extrai do HTML os campos que o navegador enviaria ao submeter o formulário.
     *
     * @return array<string, string>
     */
    private function formPayloadFrom(string $html): array
    {
        $payload = [];

        preg_match_all('/<(?:input|select)\b[^>]*name="([a-z_]+)"[^>]*>/i', $html, $fields, PREG_SET_ORDER);

        foreach ($fields as [$tag, $name]) {
            if ($name === '_token' || isset($payload[$name])) {
                continue;
            }

            if (preg_match('/type="checkbox"/i', $tag)) {
                $payload[$name] = '1';

                continue;
            }

            $payload[$name] = preg_match('/value="([^"]*)"/i', $tag, $value) ? $value[1] : '';
        }

        // Marca vem de <select> com <option selected>.
        if (preg_match('/<select\b[^>]*name="brand".*?<option[^>]*selected[^>]*value="([^"]*)"/is', $html, $option)) {
            $payload['brand'] = $option[1];
        } elseif (preg_match('/<select\b[^>]*name="brand".*?<option[^>]*value="([^"]*)"[^>]*selected/is', $html, $option)) {
            $payload['brand'] = $option[1];
        }

        // O modelo é montado no navegador a partir do catálogo da marca.
        if (preg_match('/const selectedModel = ("(?:[^"\\\\]|\\\\.)*")/', $html, $model)) {
            $payload['model'] = json_decode($model[1]);
        }

        return $payload;
    }

    /**
     * Em produção a sessão viaja dentro de um cookie (SESSION_DRIVER=cookie) e
     * o navegador descarta em silêncio qualquer cookie acima de 4096 bytes —
     * era isso que apagava o CRLV-e lido antes da tela de confirmação.
     */
    public function test_imported_crlv_session_fits_in_a_cookie(): void
    {
        config(['session.driver' => 'cookie']);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $largest = 0;

        foreach ($response->headers->getCookies() as $cookie) {
            $largest = max($largest, strlen($cookie->getName()) + strlen((string) $cookie->getValue()));
        }

        $this->assertGreaterThan(0, $largest);
        $this->assertLessThan(
            4096,
            $largest,
            "A sessão com o CRLV-e ocupa {$largest} bytes e não cabe em um cookie."
        );
    }

    /**
     * CRLV-e que parece válido mas o leitor não entende é layout novo de
     * DETRAN: o suporte precisa saber para cobrir o modelo.
     */
    public function test_unreadable_crlv_alerts_support(): void
    {
        Mail::fake();

        $this->mock(CrlvPdfParser::class, function ($parser): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')
                ->andThrow(new CrlvParseException('Estrutura do CRLV-e não reconhecida.'));
        });

        $file = UploadedFile::fake()->create('CRLV-e.pdf', 120, 'application/pdf');

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors('crlv');

        Mail::assertQueued(
            CrlvImportFailureMail::class,
            fn (CrlvImportFailureMail $mail) => $mail->hasTo((string) config('legal.support_email'))
                && $mail->reason === 'Estrutura do CRLV-e não reconhecida.'
                && $mail->context['arquivo'] === 'CRLV-e.pdf'
                && $mail->context['usuario_id'] === $this->user->id
        );
    }

    /**
     * Exercício vencido é erro de quem envia, não falha do leitor: avisar o
     * suporte a cada documento velho só geraria ruído.
     */
    public function test_expired_exercise_does_not_alert_support(): void
    {
        Mail::fake();

        $this->mock(CrlvExerciseValidator::class, function ($validator): void {
            $validator->shouldReceive('assertAcceptable')
                ->andThrow(new RuntimeException('O CRLV-e precisa ser do exercício 2025 ou 2026.'));
        });

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors('crlv');

        Mail::assertNothingQueued();
    }

    public function test_support_alert_email_renders(): void
    {
        $html = (new CrlvImportFailureMail(
            reason: 'Estrutura do CRLV-e não reconhecida.',
            context: [
                'origem' => 'user.vehicles.create',
                'arquivo' => 'CRLV-e.pdf',
                'tamanho_kb' => 120,
                'usuario_id' => 7,
                'usuario_email' => null,
            ],
        ))->render();

        $this->assertStringContainsString('Estrutura do CRLV-e não reconhecida.', $html);
        $this->assertStringContainsString('CRLV-e.pdf', $html);
        $this->assertStringContainsString('Usuario id', $html);
    }

    public function test_preview_redirects_without_session(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertRedirect(route('user.vehicles.create'));
    }

    public function test_user_can_import_crlv_on_edit_to_fill_form(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create([
            'license_plate' => 'QOS6H54',
            'renavam' => '01159110473',
            'crv_number' => '244043259050',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
        ]);
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post(route('user.vehicles.import-crlv.edit', $vehicle), ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'crv_number' => '244043259050',
            'brand' => 'Mercedes-Benz',
            'model' => 'C 180',
            'motorization' => '156CV',
        ]);
    }

    public function test_edit_import_rejects_crlv_with_different_renavam(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create([
            'license_plate' => 'AAA1A11',
            'renavam' => '99999999999',
        ]);
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->from(route('user.vehicles.edit', $vehicle))
            ->post(route('user.vehicles.import-crlv.edit', $vehicle), ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.edit', $vehicle))
            ->assertSessionHasErrors('crlv');
    }
}
