<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\AppStorage;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicVerificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_code_renders_workshop_seal_with_provenance_design(): void
    {
        $workshop = Workshop::factory()->create(['name' => 'Silva Auto Center']);
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Civic EX',
            'year' => 2022,
            'chassis' => '9BWZZZ377VT004251',
        ]);
        $this->createSealedMaintenance($vehicle, $workshop, [
            'maintenance_type' => 'Revisão 40 mil',
            'maintenance_date' => '2026-03-18',
            'kilometers' => 40012,
            // Gravado em UTC; a página mostra no horário de Brasília (17:05 UTC = 14:05), como o PDF.
            'verified_at' => Carbon::parse('2026-03-18 17:05:00', 'UTC'),
            'verification_code' => 'RVL-TEST-12',
        ]);

        $this->get('/v/RVL-TEST-12')
            ->assertOk()
            ->assertSee('Verificação pública RevisaLog')
            ->assertSee('Selo da oficina confirmado')
            ->assertDontSee('Registro verificado')
            ->assertSee('prov-seal prov-verified', false)
            ->assertSee('prov-marker--verified', false)
            ->assertSeeInOrder(['Silva Auto Center', 'registrou este serviço', 'em 18/03/2026 às 14:05'])
            ->assertSee('Revisão 40 mil')
            ->assertSee('18/03/2026')
            ->assertSee('40.012 km')
            ->assertSee('Honda · Civic EX · 2022')
            ->assertSee('9BW••••••••••4251', false)
            ->assertDontSee('9BWZZZ377VT004251', false)
            ->assertSee('Código de verificação')
            ->assertSee('data-copy-value="RVL-TEST-12"', false)
            ->assertSee('Copiar código')
            ->assertSee('Não pode ser alterado pelo proprietário')
            ->assertSee('QR code desta verificação')
            ->assertSee('aria-label="QR code para conferir este selo"', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('href="'.route('home').'#procedencia"', false);
    }

    public function test_valid_code_offers_copy_link_and_native_share_of_the_verification_url(): void
    {
        $this->createSealedMaintenance(Vehicle::factory()->create(), Workshop::factory()->create(), [
            'verification_code' => 'RVL-LINK-34',
        ]);

        $html = $this->get('/v/RVL-LINK-34')
            ->assertOk()
            ->assertSee('Copiar link')
            ->assertSee('Compartilhar')
            ->getContent();

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $url = url('/v/RVL-LINK-34');

        $copyCode = $document->querySelector('button[data-copy-button][data-copy-value="RVL-LINK-34"]');
        $copyLink = $document->querySelector('button[data-copy-button][data-copy-value="'.$url.'"]');
        $share = $document->querySelector('button[data-share-verification]');

        $this->assertSame('Código copiado', $copyCode?->getAttribute('data-copied-label'));
        $this->assertSame('Link copiado', $copyLink?->getAttribute('data-copied-label'));
        $this->assertSame($url, $share?->getAttribute('data-url'));
        $this->assertTrue($share->hasAttribute('hidden'), 'Compartilhar nasce oculto e só aparece onde há navigator.share.');
        $this->assertSame('button', $share->getAttribute('type'));
    }

    public function test_valid_code_shows_workshop_logo_inside_the_seal_marker(): void
    {
        Storage::fake('r2');

        $workshop = Workshop::factory()->create();
        $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_verification.jpg';
        Storage::disk('r2')->put($logoPath, 'fake-logo');
        $workshop->update(['logo_path' => $logoPath]);

        $this->createSealedMaintenance(Vehicle::factory()->create(), $workshop, [
            'verification_code' => 'RVL-LOGO-12',
        ]);

        $this->get('/v/RVL-LOGO-12')
            ->assertOk()
            ->assertSee($workshop->logoUrl(), false);
    }

    public function test_guest_sees_link_to_know_revisalog_and_authenticated_user_does_not(): void
    {
        $this->createSealedMaintenance(Vehicle::factory()->create(), Workshop::factory()->create(), [
            'verification_code' => 'RVL-GUES-12',
        ]);

        $this->get('/v/RVL-GUES-12')
            ->assertOk()
            ->assertSee('Conheça o RevisaLog');

        $this->actingAs(User::factory()->create())
            ->get('/v/RVL-GUES-12')
            ->assertOk()
            ->assertDontSee('Conheça o RevisaLog');
    }

    public function test_invalid_code_explains_that_no_workshop_seal_matches(): void
    {
        $this->get('/v/RVL-NADA-00')
            ->assertNotFound()
            ->assertSee('Código não encontrado')
            ->assertSee('Nenhum Selo da oficina corresponde a este código.')
            ->assertDontSee('registro verificado')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('href="'.route('home').'#procedencia"', false);
    }

    public function test_declared_maintenance_code_is_not_confirmed_as_seal(): void
    {
        Maintenance::factory()->declaredByOwner()->create()->forceFill([
            'verification_code' => 'RVL-DECL-12',
        ])->saveQuietly();

        $this->get('/v/RVL-DECL-12')
            ->assertNotFound()
            ->assertSee('Código não encontrado')
            ->assertDontSee('Selo da oficina confirmado');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    /**
     * WRK-X04: a OS mudou depois do selo (mais de um minuto depois da emissão): a página pública diz
     * quando, para quem confere não achar que o selo cobre a versão alterada sem saber.
     */
    public function test_seal_shows_when_the_service_order_changed_after_it_was_issued(): void
    {
        $maintenance = $this->createSealedMaintenance(Vehicle::factory()->create(), Workshop::factory()->create(), [
            'verified_at' => Carbon::parse('2026-03-18 14:05:00'),
            'verification_code' => 'RVL-UPDT-12',
        ]);
        $maintenance->forceFill(['updated_at' => Carbon::parse('2026-04-02 09:30:00')])->saveQuietly();

        $document = HTMLDocument::createFromString($this->get('/v/RVL-UPDT-12')->assertOk()->getContent(), LIBXML_NOERROR);
        $updated = $document->querySelector('[data-updated-after-seal]');

        $this->assertNotNull($updated);
        $this->assertSame('Atualizada em', trim($updated->querySelector('dt')->textContent));
        $this->assertSame('02/04/2026', trim($updated->querySelector('time')->textContent));
        $this->assertStringContainsString('A oficina alterou a OS depois de emitir o selo.', $updated->textContent);
    }

    public function test_seal_without_later_changes_has_no_update_line(): void
    {
        $sealedAt = Carbon::parse('2026-03-18 14:05:00');
        $maintenance = $this->createSealedMaintenance(Vehicle::factory()->create(), Workshop::factory()->create(), [
            'verified_at' => $sealedAt,
            'verification_code' => 'RVL-SAME-12',
        ]);
        $maintenance->forceFill(['updated_at' => $sealedAt->copy()->addSeconds(30)])->saveQuietly();

        $this->get('/v/RVL-SAME-12')
            ->assertOk()
            ->assertDontSee('data-updated-after-seal', false)
            ->assertDontSee('Atualizada em');
    }

    /**
     * Selo emitido às 22:30 de Brasília (01:30 UTC do dia seguinte): a data e a hora da página são
     * as de Brasília, as mesmas do PDF que o comprador tem em mãos, não as do UTC gravado no banco.
     */
    public function test_seal_date_and_time_use_the_brasilia_timezone_like_the_pdf(): void
    {
        $maintenance = $this->createSealedMaintenance(Vehicle::factory()->create(), Workshop::factory()->create(), [
            'verified_at' => Carbon::parse('2026-03-19 01:30:00', 'UTC'),
            'verification_code' => 'RVL-NITE-12',
        ]);
        $maintenance->forceFill(['updated_at' => Carbon::parse('2026-04-03 02:10:00', 'UTC')])->saveQuietly();

        $response = $this->get('/v/RVL-NITE-12')->assertOk();

        $response->assertSee('em 18/03/2026 às 22:30')
            ->assertDontSee('em 19/03/2026 às 01:30');
        $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
        $this->assertSame('02/04/2026', trim($document->querySelector('[data-updated-after-seal] time')->textContent));
    }

    private function createSealedMaintenance(Vehicle $vehicle, Workshop $workshop, array $attributes = []): Maintenance
    {
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'maintenance_type' => $attributes['maintenance_type'] ?? 'Troca de óleo',
            'maintenance_date' => $attributes['maintenance_date'] ?? now()->toDateString(),
            'kilometers' => $attributes['kilometers'] ?? 10000,
        ]);

        $maintenance->forceFill([
            'registered_by_type' => 'workshop',
            'verified_at' => $attributes['verified_at'] ?? now(),
            'verified_workshop_id' => $workshop->id,
            'verification_code' => $attributes['verification_code'],
        ])->saveQuietly();

        return $maintenance;
    }
}
