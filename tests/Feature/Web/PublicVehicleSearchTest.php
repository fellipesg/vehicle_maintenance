<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePlate;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Busca de veículo (PUB-X02, PUB-05, PUB-10, PUB-12, PUB-X06): a ficha <x-vehicle.detail> como
 * resultado, chassi e RENAVAM parciais para quem não é dono, throttle:search, campo rotulado com
 * dica de formato e estado de envio, duas colunas no lg e estado vazio com dicas e próximo passo.
 */
class PublicVehicleSearchTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const CRV = '998877665544';

    public function test_page_has_one_h1_and_a_labelled_search_field_with_format_hint(): void
    {
        $response = $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search'))
            ->assertOk()
            ->assertSee('<title>Buscar histórico de veículo · Proprietário · RevisaLog</title>', false)
            ->assertDontSee('🔍');
        $page = $this->page($response);

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Buscar histórico de veículo', $this->text($this->element($page, 'h1')));
        $this->assertStringContainsString('Busca por placa, chassi ou RENAVAM', $this->text($this->element($page, '[data-slot="page-header-description"]')));

        $form = $this->element($page, 'form[role="search"]');
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('vehicle.search'), $form->getAttribute('action'));

        $input = $this->element($page, '#vehicle-search-identifier');
        $label = $this->element($page, 'label[for="vehicle-search-identifier"]');
        $this->assertStringContainsString('Placa, chassi ou RENAVAM', $this->text($label));
        $this->assertStringNotContainsString('sr-only', (string) $label->getAttribute('class'), 'O rótulo fica visível, não só para leitor de tela.');
        $this->assertSame('identifier', $input->getAttribute('name'));
        $this->assertContains('vehicle-search-identifier-hint', explode(' ', (string) $input->getAttribute('aria-describedby')));
        $this->assertStringContainsString('ABC1D23', $this->text($this->element($page, '#vehicle-search-identifier-hint')));
        $this->assertStringContainsString('17 caracteres', $this->text($this->element($page, '#vehicle-search-identifier-hint')));
        $this->assertSame('characters', $input->getAttribute('autocapitalize'));
        $this->assertSame('false', $input->getAttribute('spellcheck'));
        $this->assertSame('off', $input->getAttribute('autocomplete'));
        $this->assertSame('search', $input->getAttribute('enterkeyhint'));
        $this->assertTrue($input->hasAttribute('autofocus'), 'Na página vazia o campo já vem focado.');
        $this->assertTrue($input->hasAttribute('required'));

        $submit = $this->element($form, 'button[type="submit"]');
        $this->assertSame('Buscando…', $submit->getAttribute('data-loading-label'));
    }

    public function test_initial_state_explains_what_the_result_shows_in_a_two_column_layout(): void
    {
        $page = $this->page($this->actingAs(User::factory()->asUser()->create())->get(route('vehicle.search'))->assertOk());

        $panel = $this->element($page, '[data-slot="vehicle-search-panel"]');
        $this->assertStringContainsString('lg:sticky', (string) $panel->getAttribute('class'));
        $this->assertStringContainsString('lg:grid-cols-[20rem_minmax(0,1fr)]', (string) $panel->parentElement?->getAttribute('class'));

        $empty = $this->element($page, '[data-slot="vehicle-search-result"] [data-slot="empty-state"]');
        $this->assertSame('Digite a placa, o chassi ou o RENAVAM', $this->text($this->element($empty, 'h2')));
        $this->assertNotNull($empty->querySelector('[data-slot="provenance-legend"]'));
    }

    public function test_non_owner_sees_partial_identifiers_without_copy_crv_or_engine(): void
    {
        $vehicle = $this->vehicleWithHistory();
        $response = $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'ABC1D23']))
            ->assertOk()
            ->assertSee('9BW••••••••••4251', false)
            ->assertSee('•••••••8901', false)
            ->assertDontSee(self::CHASSIS, false)
            ->assertDontSee(self::RENAVAM, false)
            ->assertDontSee(self::CRV, false)
            ->assertDontSee('ENG-SECRET-77', false)
            ->assertSee('Dados parciais para proteger o proprietário');
        $page = $this->page($response);

        $this->assertNull($page->querySelector('button[data-copy-button]'), 'Sem "Copiar chassi" para quem não é dono.');
        $this->assertSame('public', $this->element($page, '[data-vehicle-detail]')->getAttribute('data-portal'));
        $this->assertCount(0, $page->querySelectorAll('#historico-lista a[href*="/usuario/manutencoes/"]'), 'Quem não é dono não recebe links para telas de outro portal.');
        $this->assertNull($page->querySelector('a[href="'.route('user.vehicles.show', $vehicle).'"]'));
        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Volkswagen Gol', $this->text($this->element($page, '[data-slot="vehicle-detail-header"] h2')));
    }

    public function test_owner_sees_full_identifiers_copy_button_and_links_to_the_own_portal(): void
    {
        $vehicle = $this->vehicleWithHistory();
        $owner = User::factory()->asUser()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $maintenance = $vehicle->maintenances()->firstOrFail();

        $response = $this->actingAs($owner)
            ->get(route('vehicle.search', ['identifier' => self::RENAVAM]))
            ->assertOk()
            ->assertSee(self::CHASSIS, false)
            ->assertSee(self::RENAVAM, false)
            ->assertSee(self::CRV, false)
            ->assertDontSee('9BW••••••••••4251', false)
            ->assertDontSee('Dados parciais para proteger o proprietário')
            ->assertSee('Veículo da sua conta')
            ->assertSee('encontrado pelo RENAVAM');
        $page = $this->page($response);

        $copyChassis = $this->element($page, '[data-slot="copy-button"] button[data-copy-button]');
        $this->assertSame(self::CHASSIS, $copyChassis->getAttribute('data-copy-value'));
        $this->assertSame('Chassi copiado', $copyChassis->getAttribute('data-copied-label'));
        $this->assertSame('Copiar chassi', $this->text($copyChassis));
        $this->assertSame('user', $this->element($page, '[data-vehicle-detail]')->getAttribute('data-portal'));
        $this->assertSame(route('user.vehicles.show', $vehicle), $this->link($page, 'Abrir ficha do veículo')->getAttribute('href'));
        $this->assertNotNull($page->querySelector('#manutencao-'.$maintenance->id.' a[href="'.route('user.maintenances.show', $maintenance).'"]'));
    }

    public function test_dealer_that_owns_the_vehicle_sees_everything_and_opens_the_stock_page(): void
    {
        $vehicle = $this->vehicleWithHistory();
        $garage = User::factory()->asGarage()->create();
        $this->attachVehicleToUser($garage, $vehicle);

        $page = $this->page($this->actingAs($garage)
            ->get(route('vehicle.search', ['identifier' => self::CHASSIS]))
            ->assertOk()
            ->assertSee(self::CHASSIS, false)
            ->assertSee('encontrado pelo chassi'));

        $this->assertSame(route('garage.vehicles.show', $vehicle), $this->link($page, 'Abrir ficha do veículo')->getAttribute('href'));
        $this->assertSame('garage', $this->element($page, '[data-vehicle-detail]')->getAttribute('data-portal'));
    }

    public function test_admin_that_is_not_the_owner_sees_partial_data_and_a_link_to_the_admin_panel(): void
    {
        $vehicle = $this->vehicleWithHistory();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $page = $this->page($this->actingAs($admin)
            ->get(route('vehicle.search', ['identifier' => 'ABC1D23']))
            ->assertOk()
            ->assertDontSee(self::CHASSIS, false)
            ->assertSee('9BW••••••••••4251', false));

        $this->assertSame(route('admin.vehicles.show', $vehicle), $this->link($page, 'Abrir no painel admin')->getAttribute('href'));
    }

    public function test_previous_plate_shows_an_info_notice_with_the_current_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'CUR4D56']);
        VehiclePlate::factory()->create([
            'vehicle_id' => $vehicle->id,
            'plate' => 'OLD5E67',
            'ended_at' => now()->subMonth(),
            'source' => 'manual',
        ]);

        $page = $this->page($this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'old5e67']))
            ->assertOk()
            ->assertSee('encontrado por uma placa anterior'));

        $alert = $this->element($page, '[data-slot="vehicle-search-result"] [data-slot="alert"]:not([data-ownership-unverified])');
        $this->assertSame('status', $alert->getAttribute('role'));
        $this->assertStringContainsString('A placa OLD5E67 pertenceu a este veículo até', $this->text($alert));
        $this->assertStringContainsString('Placa atual: CUR4D56.', $this->text($alert));
    }

    public function test_not_found_offers_tips_retry_and_the_portal_add_action(): void
    {
        $page = $this->page($this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'NAO0000']))
            ->assertOk()
            ->assertDontSee('bg-yellow-50', false));

        $empty = $this->element($page, '[data-vehicle-search-empty]');
        $this->assertSame('Nenhum veículo com “NAO0000”', $this->text($this->element($empty, 'h2')));
        $this->assertStringContainsString('O chassi tem 17 caracteres e o RENAVAM, 11 dígitos.', $this->text($empty));
        $this->assertStringContainsString('Placas antigas do veículo também são pesquisadas.', $this->text($empty));
        $this->assertSame(route('user.vehicles.create'), $this->link($empty, 'Adicionar veículo')->getAttribute('href'));
        $this->assertSame('#vehicle-search-identifier', $this->link($empty, 'Tentar outra busca')->getAttribute('href'));
        $this->assertSame('NAO0000', $this->element($page, '#vehicle-search-identifier')->getAttribute('value'));
        $this->assertFalse($this->element($page, '#vehicle-search-identifier')->hasAttribute('autofocus'), 'Com resultado, o teclado não abre sozinho sobre as dicas.');
    }

    public function test_not_found_action_follows_the_portal(): void
    {
        $dealerEmpty = $this->element(
            $this->page($this->actingAs(User::factory()->asGarage()->create())->get(route('vehicle.search', ['identifier' => 'NAO0000']))->assertOk()),
            '[data-vehicle-search-empty]',
        );
        $this->assertSame(route('garage.vehicles.create'), $this->link($dealerEmpty, 'Adicionar ao estoque')->getAttribute('href'));

        $workshopEmpty = $this->element(
            $this->page($this->actingAs(User::factory()->asWorkshop()->create())->get(route('vehicle.search', ['identifier' => 'NAO0000']))->assertOk()),
            '[data-vehicle-search-empty]',
        );
        $this->assertCount(1, $workshopEmpty->querySelectorAll('a'), 'A oficina só recebe "Tentar outra busca".');
    }

    public function test_typed_identifier_is_escaped(): void
    {
        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_identifier_that_is_not_text_is_ignored(): void
    {
        $this->actingAs(User::factory()->asUser()->create())
            ->get('/buscar-veiculo?identifier[]=ABC1D23')
            ->assertOk()
            ->assertSee('Digite a placa, o chassi ou o RENAVAM');
    }

    public function test_search_is_throttled(): void
    {
        $user = User::factory()->asUser()->create();

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->actingAs($user)->get(route('vehicle.search', ['identifier' => 'AAA'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT)]))->assertOk();
        }

        $this->actingAs($user)
            ->get(route('vehicle.search', ['identifier' => 'ZZZ9999']))
            ->assertTooManyRequests()
            ->assertSee('Muitas tentativas seguidas');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('vehicle.search', ['identifier' => 'ABC1D23']))->assertRedirect(route('login'));
    }

    private function vehicleWithHistory(): Vehicle
    {
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Volkswagen',
            'model' => 'Gol',
            'year' => 2019,
            'license_plate' => 'ABC1D23',
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
            'crv_number' => self::CRV,
            'engine' => 'ENG-SECRET-77',
        ]);

        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão 20 mil',
            'kilometers' => 20000,
            'maintenance_date' => '2024-02-10',
        ]);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Troca de pastilhas',
            'kilometers' => 30000,
            'maintenance_date' => '2025-01-15',
        ]);

        return $vehicle;
    }

    private function page(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString((string) $response->getContent(), LIBXML_NOERROR);
    }

    private function element(HTMLDocument|Element $root, string $selector): Element
    {
        $element = $root->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function link(HTMLDocument|Element $root, string $label): Element
    {
        foreach ($root->querySelectorAll('a') as $anchor) {
            if ($this->text($anchor) === $label) {
                return $anchor;
            }
        }

        $this->fail("Nenhum link \"{$label}\".");
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));
    }
}
