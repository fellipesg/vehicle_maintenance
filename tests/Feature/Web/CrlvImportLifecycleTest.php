<?php

namespace Tests\Feature\Web;

use App\Models\CrlvImport;
use App\Models\User;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrlvImportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->user = User::factory()->asUser()->create();
    }

    private function crlvFile(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );
    }

    private function import(?User $user = null): CrlvImport
    {
        $this->actingAs($user ?? $this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->crlvFile()]);

        // UUID v7 é crescente: o último id é o import recém-criado.
        return CrlvImport::query()->orderByDesc('id')->firstOrFail();
    }

    /**
     * A sessão viaja dentro de um cookie em produção: só o id pode ficar lá.
     */
    public function test_session_keeps_only_the_import_id(): void
    {
        config(['session.driver' => 'cookie']);

        $response = $this->actingAs($this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $this->crlvFile()]);

        $largest = 0;

        foreach ($response->headers->getCookies() as $cookie) {
            $largest = max($largest, strlen($cookie->getName()) + strlen((string) $cookie->getValue()));
        }

        $this->assertLessThan(2048, $largest, "A sessão ocupa {$largest} bytes.");
        $this->assertSame(1, CrlvImport::count());
    }

    public function test_import_belongs_to_whoever_sent_it(): void
    {
        $import = $this->import();
        $intruder = User::factory()->asUser()->create();

        // Sessão do intruso apontando para o import de outra pessoa.
        $this->actingAs($intruder)
            ->withSession(['crlv_import_id' => $import->id])
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertRedirect(route('user.vehicles.create'));
    }

    public function test_expired_import_is_not_usable(): void
    {
        $import = $this->import();
        $import->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertRedirect(route('user.vehicles.create'));
    }

    public function test_confirming_the_vehicle_consumes_the_import(): void
    {
        $import = $this->import();

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', [
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
                'current_kilometers' => 50_000,
                'terms_accepted' => '1',
                'crlv_verification_token' => $import->token,
            ])
            ->assertRedirect()
            ->assertSessionMissing('crlv_import_id');

        $this->assertNotNull($import->fresh()->consumed_at);
    }

    /** Documento pessoal não fica guardado além do cadastro em andamento. */
    public function test_prune_removes_expired_imports(): void
    {
        $expired = $this->import();
        $expired->forceFill(['expires_at' => now()->subHour()])->save();

        $current = $this->import();

        $this->artisan('model:prune', ['--model' => CrlvImport::class])->assertExitCode(0);

        $this->assertDatabaseMissing('crlv_imports', ['id' => $expired->id]);
        $this->assertDatabaseHas('crlv_imports', ['id' => $current->id]);
    }

    public function test_parsed_document_is_encrypted_at_rest(): void
    {
        $import = $this->import();

        $raw = DB::table('crlv_imports')->where('id', $import->id)->value('parsed');

        $this->assertStringNotContainsString('PHF9J95', (string) $raw);
        $this->assertSame('PHF9J95', $import->parsed['license_plate']);
    }
}
