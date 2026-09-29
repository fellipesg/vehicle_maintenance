<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProvenanceSealTerminologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sealed_maintenance_uses_workshop_seal_as_primary_label(): void
    {
        $workshop = Workshop::factory()->create(['name' => 'Silva Auto Center']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshop->id,
        ]);
        $maintenance->forceFill([
            'verified_at' => Carbon::parse('2026-03-12 10:00:00'),
            'verification_code' => 'RVL-ABCD-EF',
        ])->saveQuietly();

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', [
            'maintenance' => $maintenance->fresh()->load('verifiedWorkshop'),
        ]);

        $this->assertStringContainsString('Selo da oficina', $html);
        $this->assertStringNotContainsString('Registro verificado', $html);
        $this->assertStringContainsString('Silva Auto Center', $html);
        $this->assertMatchesRegularExpression('#Emitido em <time datetime="2026-03-12T07:00:00-03:00" class="tabular-nums">12/03/2026</time>#', $html);
        $this->assertStringContainsString('RVL-ABCD-EF', $html);
        $this->assertStringContainsString('role="img" aria-label="QR code para conferir este selo"', $html);
        $this->assertStringContainsString('(abre em nova aba)', $html);
        $this->assertStringNotContainsString('text-teal-800', $html);
        $this->assertLessThan(
            strpos($html, 'Silva Auto Center'),
            strpos($html, 'Selo da oficina'),
            'O rótulo "Selo da oficina" deve vir antes do nome da oficina.'
        );
    }

    public function test_sealed_maintenance_offers_copy_link_and_hidden_native_share(): void
    {
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create();
        $maintenance->forceFill(['verification_code' => 'RVL-SHAR-12'])->saveQuietly();

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', [
            'maintenance' => $maintenance->fresh(),
        ]);
        $url = url('/v/RVL-SHAR-12');

        $this->assertStringContainsString('Copiar link', $html);
        // Copiar código e link pelo x-ui.copy-button (check de 1,5s e aviso anunciado).
        $this->assertMatchesRegularExpression('#data-copy-button\s+data-copy-value="'.preg_quote($url, '#').'"\s+data-copied-label="Link copiado"#', $html);
        $this->assertMatchesRegularExpression('#data-copy-button\s+data-copy-value="RVL-SHAR-12"\s+data-copied-label="Código copiado"#', $html);
        // x-ui.button escreve o atributo booleano como nome="nome" (data-share-verification="data-share-verification").
        $this->assertMatchesRegularExpression('#data-share-verification(?:="data-share-verification")?\s+data-url="'.preg_quote($url, '#').'"\s+hidden(?:="hidden")?\s*>#', $html);
        $this->assertStringNotContainsString('btn-secondary', $html, 'Ações do selo com <x-ui.button>.');
    }

    public function test_provenance_actions_script_only_handles_native_share(): void
    {
        $script = file_get_contents(resource_path('js/provenance-actions.js'));

        $this->assertStringContainsString("'[data-share-verification]'", $script);
        $this->assertStringContainsString("navigator.share({ title: 'Selo da oficina', url })", $script);
        // Se o compartilhamento falhar, o link vai para a área de transferência pelo mesmo helper do x-ui.copy-button.
        $this->assertStringContainsString("import { writeToClipboard } from './ui/copy';", $script);
        $this->assertStringContainsString("title: 'Link copiado'", $script);

        // Copiar é do x-ui.copy-button (resources/js/ui/copy.js), não mais deste script.
        foreach (['data-copy-verification-code', 'data-copy-verification-link', 'data-copy-text', 'data-manual-copy'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $script);
        }
    }

    public function test_declared_maintenance_seal_keeps_declared_copy(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create();

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', [
            'maintenance' => $maintenance->fresh(),
        ]);

        $this->assertStringContainsString('Declarada pelo proprietário', $html);
        $this->assertStringContainsString('não verificada', $html);
        $this->assertStringNotContainsString('Selo da oficina', $html);
    }

    public function test_owner_maintenance_detail_shows_workshop_seal_label(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->actingAs($owner)
            ->get(route('user.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Selo da oficina')
            ->assertSee('Emitido em')
            ->assertDontSee('Registro verificado');
    }

    public function test_legend_hides_decorative_markers_from_assistive_technology(): void
    {
        $html = Blade::render('<x-provenance-legend />');

        $this->assertStringContainsString('Selo da oficina', $html);
        $this->assertStringContainsString('(verificada)', $html);
        $this->assertSame(3, substr_count($html, 'aria-hidden="true"'));
    }

    /**
     * Os cards marcam cada declarada com PR ou LJ conforme quem declarou, e uma mesma lista pode ter
     * as duas: a legenda explica sempre os dois marcadores, com os termos de .ai/rules/theme.md.
     */
    public function test_legend_always_explains_both_declared_markers(): void
    {
        $html = Blade::render('<x-provenance-legend />');
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        $this->assertSame('OF Selo da oficina (verificada) PR Declarada pelo proprietário (não verificada) LJ Declarada pelo lojista (não verificada)', $text);
        $this->assertStringNotContainsString('declared-by', $html);
    }

    public function test_seal_dates_use_the_brasilia_timezone(): void
    {
        // 01:30 UTC do dia 13 é 22:30 do dia 12 em Brasília: o selo mostra o dia 12, como o PDF.
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create();
        $maintenance->forceFill([
            'verified_at' => Carbon::parse('2026-03-13 01:30:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-03-21 02:00:00', 'UTC'),
        ])->saveQuietly();

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $maintenance->fresh()]);

        $this->assertMatchesRegularExpression('#Emitido em <time datetime="2026-03-12T22:30:00-03:00" class="tabular-nums">12/03/2026</time>#', $html);
        $this->assertMatchesRegularExpression('#Atualizada em <time datetime="[^"]+" class="tabular-nums">20/03/2026</time>#', $html);
        $this->assertStringContainsString('verificada em 12/03/2026', $maintenance->fresh()->provenance_meta);
    }

    public function test_seal_says_when_the_service_order_changed_after_the_seal(): void
    {
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create();
        $maintenance->forceFill([
            'verified_at' => Carbon::parse('2026-03-12 10:00:00'),
            'updated_at' => Carbon::parse('2026-03-20 08:15:00'),
        ])->saveQuietly();

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $maintenance->fresh()]);

        $this->assertStringContainsString('data-updated-after-seal', $html);
        $this->assertMatchesRegularExpression('#Atualizada em <time datetime="[^"]+" class="tabular-nums">20/03/2026</time>, depois da emissão do selo\.#', $html);
        $this->assertTrue($maintenance->fresh()->wasUpdatedAfterSeal());

        $maintenance->forceFill(['updated_at' => Carbon::parse('2026-03-12 10:00:40')])->saveQuietly();

        $this->assertFalse($maintenance->fresh()->wasUpdatedAfterSeal(), 'A gravação da própria emissão (menos de 1 minuto) não conta.');
        $this->assertStringNotContainsString('Atualizada em', Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $maintenance->fresh()]));
    }

    /**
     * WRK-23: o logo da oficina aparece inteiro (object-contain numa moldura clara), nunca cortado
     * num círculo.
     */
    public function test_workshop_logo_in_the_seal_is_not_cropped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('r2');
        $workshop = Workshop::factory()->create();
        $logoPath = \App\Support\AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_seal.jpg';
        \Illuminate\Support\Facades\Storage::disk('r2')->put($logoPath, 'fake-logo');
        $workshop->update(['logo_path' => $logoPath]);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshop->id]);

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $maintenance->fresh()->load('verifiedWorkshop')]);

        $this->assertMatchesRegularExpression('#<img\s+src="[^"]+"\s+alt=""\s+class="size-16 shrink-0 rounded-control bg-surface object-contain p-1\.5 ring-1 ring-border"\s+data-slot="provenance-seal-logo"#', $html);
        $this->assertStringNotContainsString('rounded-full object-cover', $html);
        $this->assertStringNotContainsString('object-cover', file_get_contents(resource_path('views/components/provenance-marker.blade.php')));
        $this->assertMatchesRegularExpression('/\.prov-marker--verified img \{[^}]*object-fit: contain;/', file_get_contents(resource_path('css/provenance.css')));
    }

    /**
     * Declarada que cita uma oficina cadastrada (workshop_id): o texto não diz mais que "não passou
     * por uma oficina cadastrada", e sim que a oficina não confirmou (não há Selo da oficina).
     */
    public function test_declared_record_that_cites_a_registered_workshop_says_it_has_no_seal(): void
    {
        $workshop = Workshop::factory()->create(['name' => 'Auto Center Paulista']);
        $maintenance = Maintenance::factory()->declaredByOwner()->create(['workshop_id' => $workshop->id]);

        $html = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $maintenance->fresh()->load('workshop')]);

        $this->assertStringContainsString('Este registro foi feito pelo dono do veículo e cita a oficina Auto Center Paulista, cadastrada no RevisaLog, mas não tem o Selo da oficina: a oficina não confirmou o serviço.', $html);
        $this->assertStringNotContainsString('não passou por uma oficina cadastrada', $html);

        $garage = Maintenance::factory()->declaredByGarage()->create(['workshop_id' => $workshop->id]);
        $garageHtml = Blade::render('<x-provenance-seal :maintenance="$maintenance" />', ['maintenance' => $garage->fresh()->load('workshop')]);

        $this->assertStringContainsString('Este registro foi feito pelo lojista e cita a oficina Auto Center Paulista', $garageHtml);
    }
}
