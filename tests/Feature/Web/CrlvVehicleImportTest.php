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
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
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
            ->assertSee('Conferir dados do veículo');
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
            ->assertSee('Conferir dados do veículo');
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
        Exceptions::fake();

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

        // Exceção reportada é o que o handler entrega ao Sentry.
        Exceptions::assertReported(CrlvParseException::class);

        Mail::assertQueued(
            CrlvImportFailureMail::class,
            fn (CrlvImportFailureMail $mail) => $mail->hasTo((string) config('legal.support_email'))
                && $mail->reason === 'Estrutura do CRLV-e não reconhecida.'
                && $mail->context['extensao'] === 'pdf'
                && $mail->context['usuario_id'] === $this->user->id
        );

        // Nada de esperar o provedor de e-mail dentro da requisição de quem cadastra.
        Mail::assertNotSent(CrlvImportFailureMail::class);
    }

    /**
     * Mesma falha, mesma conta: o suporte recebe um aviso e o Sentry um evento. Reenviar o
     * documento recusado em sequência não multiplica nem um nem outro.
     */
    public function test_repeated_identical_failure_alerts_support_only_once(): void
    {
        Mail::fake();
        Exceptions::fake();
        $this->failingCrlvParser();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
                ->assertRedirect(route('user.vehicles.create'))
                ->assertSessionHasErrors('crlv');
        }

        Mail::assertQueuedCount(1);
        Exceptions::assertReportedCount(1);
    }

    /**
     * O log local fica fora da janela: ele é o que diz ao suporte se a pessoa tentou uma vez ou
     * vinte, e não custa cota nem caixa de entrada.
     */
    public function test_every_attempt_is_logged_even_when_the_alert_is_deduplicated(): void
    {
        Mail::fake();
        Exceptions::fake();
        Log::spy();
        $this->failingCrlvParser();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
                ->assertSessionHasErrors('crlv');
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'CRLV-e não lido.'
                && $context['usuario_id'] === $this->user->id)
            ->times(3);

        Mail::assertQueuedCount(1);
        Exceptions::assertReportedCount(1);
    }

    /**
     * A janela de silêncio é por conta: outro usuário com a mesma falha ainda avisa, senão o
     * primeiro a tropeçar no layout novo esconderia todos os outros.
     */
    public function test_window_is_per_account(): void
    {
        Mail::fake();
        Exceptions::fake();
        $this->failingCrlvParser();

        $other = User::factory()->asUser()->create();

        foreach ([$this->user, $other] as $actor) {
            $this->actingAs($actor)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
                ->assertSessionHasErrors('crlv');
        }

        Mail::assertQueuedCount(2);
        Exceptions::assertReportedCount(2);
    }

    /**
     * Renomear o PDF não é falha nova: o balde ignora o nome do arquivo.
     */
    public function test_renaming_the_file_does_not_reopen_the_window(): void
    {
        Mail::fake();
        Exceptions::fake();
        $this->failingCrlvParser();

        foreach (['CRLV-e.pdf', 'crlv-2.pdf'] as $name) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv($name)])
                ->assertSessionHasErrors('crlv');
        }

        Mail::assertQueuedCount(1);
        Exceptions::assertReportedCount(1);
    }

    /**
     * O aviso vai para o log da aplicação e para fora dela: o e-mail de quem enviou o documento
     * não entra no contexto, só o id da conta.
     */
    public function test_failure_log_does_not_carry_the_user_email(): void
    {
        Mail::fake();
        Exceptions::fake();
        Log::spy();
        $this->failingCrlvParser();

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
            ->assertSessionHasErrors('crlv');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'CRLV-e não lido.'
                && $context['usuario_id'] === $this->user->id
                && ! array_key_exists('usuario_email', $context)
                && ! in_array($this->user->email, $context, true))
            ->once();

        Mail::assertQueued(
            CrlvImportFailureMail::class,
            fn (CrlvImportFailureMail $mail) => ! array_key_exists('usuario_email', $mail->context)
                && ! in_array($this->user->email, $mail->context, true)
        );
    }

    /**
     * O nome que quem envia dá ao PDF pode trazer nome ou CPF da pessoa (LGPD, .ai/rules/models.md):
     * ele não entra no log nem no aviso ao suporte. O que vai no lugar descreve o documento, não a
     * pessoa — extensão, tamanho e um hash curto do conteúdo.
     */
    public function test_alert_does_not_carry_the_original_file_name(): void
    {
        Mail::fake();
        Exceptions::fake();
        Log::spy();
        $this->failingCrlvParser();

        $name = 'CRLV-e Joao da Silva 000.000.000-00.pdf';

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv($name)])
            ->assertSessionHasErrors('crlv');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'CRLV-e não lido.'
                && ! array_key_exists('arquivo', $context)
                && ! in_array($name, $context, true)
                && $context['extensao'] === 'pdf'
                && $context['tamanho_kb'] === 120
                && is_string($context['hash_arquivo'])
                && $context['hash_arquivo'] !== '')
            ->once();

        Mail::assertQueued(CrlvImportFailureMail::class, function (CrlvImportFailureMail $mail) use ($name): bool {
            $html = $mail->render();

            return ! array_key_exists('arquivo', $mail->context)
                && ! in_array($name, $mail->context, true)
                && ! str_contains($html, $name)
                && ! str_contains($html, 'Joao da Silva')
                && ! str_contains($html, '000.000.000-00')
                && str_contains($html, 'Hash do arquivo');
        });
    }

    /**
     * Upload de CRLV-e passa pelo balde 'uploads' (10/min por conta): PDF inválido em loop para
     * no limite da rota, antes de chegar ao leitor.
     */
    public function test_crlv_upload_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
                ->assertRedirect(route('user.vehicles.create'));
        }

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()])
            ->assertStatus(429);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function crlvUploadRouteProvider(): array
    {
        return [
            'importar (proprietário)' => ['/usuario/veiculos/importar-crlv'],
            'vincular (proprietário)' => ['/usuario/veiculos/vincular/crlv'],
        ];
    }

    #[DataProvider('crlvUploadRouteProvider')]
    public function test_every_crlv_upload_route_is_throttled(string $path): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post($path, ['crlv' => $this->unreadableCrlv()]);
        }

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post($path, ['crlv' => $this->unreadableCrlv()])
            ->assertStatus(429);
    }

    /**
     * O limite é do grupo 'uploads', compartilhado entre as rotas de upload da conta: gastar a
     * cota numa delas fecha as outras.
     */
    public function test_upload_routes_share_the_same_bucket(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($this->user)
                ->from(route('user.vehicles.create'))
                ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->unreadableCrlv()]);
        }

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/vincular/crlv', ['crlv' => $this->unreadableCrlv()])
            ->assertStatus(429);
    }

    /**
     * A tela Conferir só lê a sessão (nada de reprocessar o PDF), então fica fora do balde de
     * upload: quem recarrega a conferência não perde a cota de envio.
     */
    public function test_preview_is_not_throttled(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $this->actingAs($this->user)
                ->get('/usuario/veiculos/importar-crlv/preview')
                ->assertOk();
        }
    }

    /**
     * Leitor que recusa qualquer documento, para exercitar o caminho de falha.
     */
    private function failingCrlvParser(): void
    {
        $this->mock(CrlvPdfParser::class, function ($parser): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')
                ->andThrow(new CrlvParseException('Estrutura do CRLV-e não reconhecida.'));
        });
    }

    private function unreadableCrlv(string $name = 'CRLV-e.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    /**
     * Exercício vencido é erro de quem envia, não falha do leitor: avisar o
     * suporte a cada documento velho só geraria ruído.
     */
    public function test_expired_exercise_does_not_alert_support(): void
    {
        Mail::fake();
        Exceptions::fake();

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

        Exceptions::assertNothingReported();
        Mail::assertNothingQueued();
    }

    public function test_support_alert_email_renders(): void
    {
        $html = (new CrlvImportFailureMail(
            reason: 'Estrutura do CRLV-e não reconhecida.',
            context: [
                'origem' => 'user.vehicles.create',
                'extensao' => 'pdf',
                'tamanho_kb' => 120,
                'hash_arquivo' => 'e3b0c44298fc',
                'usuario_id' => 7,
            ],
        ))->render();

        $this->assertStringContainsString('Estrutura do CRLV-e não reconhecida.', $html);
        $this->assertStringContainsString('Hash do arquivo', $html);
        $this->assertStringContainsString('e3b0c44298fc', $html);
        $this->assertStringContainsString('ID do usuário', $html);
        $this->assertStringNotContainsString('Usuario id', $html);
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
