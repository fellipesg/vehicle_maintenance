<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A capa nunca é cortada em card e hero (.ai/rules/components.md): foto inteira com
 * object-contain, altura definida pela variante e object-cover só na miniatura.
 */
class VehicleCoverComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_shows_the_whole_photo_with_its_own_height(): void
    {
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Civic',
            'cover_photo_path' => 'vehicle-covers/civic.jpg',
        ]);

        $html = (string) $this->blade('<x-vehicle-cover :vehicle="$vehicle" variant="card" />', ['vehicle' => $vehicle]);
        $xpath = $this->xpath($html);

        $frameClass = $this->frame($xpath)->getAttribute('class');
        $this->assertStringContainsString('h-48', $frameClass);
        $this->assertStringContainsString('sm:h-52', $frameClass);
        $this->assertStringNotContainsString('aspect-', $html);

        $photoClass = $this->photo($xpath, 'Capa do Honda Civic')->getAttribute('class');
        $this->assertStringContainsString('object-contain', $photoClass);
        $this->assertStringNotContainsString('object-cover', $photoClass);

        $backdrop = $xpath->query('//img[@aria-hidden="true"]');
        $this->assertSame(1, $backdrop->length);
        $this->assertInstanceOf(DOMElement::class, $backdrop->item(0));
        $this->assertSame('', $backdrop->item(0)->getAttribute('alt'));
        $this->assertStringContainsString('blur-2xl', $backdrop->item(0)->getAttribute('class'));
    }

    public function test_hero_with_landscape_and_portrait_keeps_both_uncropped(): void
    {
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Fiat',
            'model' => 'Uno',
            'cover_photo_path' => 'vehicle-covers/uno-landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/uno-portrait.jpg',
        ]);

        $html = (string) $this->blade('<x-vehicle-cover :vehicle="$vehicle" variant="hero" class="mb-6" />', ['vehicle' => $vehicle]);
        $xpath = $this->xpath($html);

        $frameClass = $this->frame($xpath)->getAttribute('class');
        $this->assertStringContainsString('h-64 sm:h-80 lg:h-96', $frameClass);
        $this->assertStringContainsString('mb-6', $frameClass);

        $this->assertSame(1, $xpath->query('//picture[@aria-hidden="true"]')->length);
        $this->assertSame(2, $xpath->query('//source[@media="(min-width: 768px)"]')->length);
        $this->assertStringContainsString('uno-landscape.jpg', $html);
        $this->assertStringContainsString('uno-portrait.jpg', $html);

        $photoClass = $this->photo($xpath, 'Capa do Fiat Uno')->getAttribute('class');
        $this->assertStringContainsString('object-contain', $photoClass);
        $this->assertStringNotContainsString('object-cover', $photoClass);
    }

    public function test_hero_without_photo_is_a_compact_placeholder(): void
    {
        $vehicle = Vehicle::factory()->create(['cover_photo_path' => null, 'cover_photo_portrait_path' => null]);

        $html = (string) $this->blade('<x-vehicle-cover :vehicle="$vehicle" variant="hero" />', ['vehicle' => $vehicle]);
        $xpath = $this->xpath($html);

        $frameClass = $this->frame($xpath)->getAttribute('class');
        $this->assertStringContainsString('h-32 sm:h-40', $frameClass);
        $this->assertStringNotContainsString('h-64', $frameClass);
        $this->assertStringContainsString('Sem foto de capa', $html);
        $this->assertSame(1, $xpath->query('//svg')->length);
        $this->assertSame(0, $xpath->query('//img')->length);
        $this->assertStringNotContainsString('🚗', $html);
    }

    public function test_thumb_is_the_only_variant_that_crops(): void
    {
        $vehicle = Vehicle::factory()->create([
            'brand' => 'VW',
            'model' => 'Gol',
            'cover_photo_path' => 'vehicle-covers/gol.jpg',
        ]);

        $html = (string) $this->blade('<x-vehicle-cover :vehicle="$vehicle" />', ['vehicle' => $vehicle]);
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('h-14 w-14', $this->frame($xpath)->getAttribute('class'));
        $this->assertStringContainsString('object-cover', $this->photo($xpath, 'Capa do VW Gol')->getAttribute('class'));
        $this->assertSame(1, $xpath->query('//img')->length);

        $empty = Vehicle::factory()->create(['cover_photo_path' => null, 'cover_photo_portrait_path' => null]);
        $emptyHtml = (string) $this->blade('<x-vehicle-cover :vehicle="$vehicle" />', ['vehicle' => $empty]);

        $this->assertStringNotContainsString('Sem foto de capa', $emptyHtml);
        $this->assertStringContainsString('<svg', $emptyHtml);
    }

    public function test_public_search_shows_the_cover_as_an_uncropped_hero(): void
    {
        $user = User::factory()->asUser()->create();
        Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Fit',
            'license_plate' => 'FIT1A23',
            'cover_photo_path' => 'vehicle-covers/fit.jpg',
        ]);

        $html = $this->actingAs($user)
            ->get('/buscar-veiculo?identifier=FIT1A23')
            ->assertOk()
            ->assertSee('data-vehicle-cover="hero"', false)
            ->assertDontSee('aspect-[21/9]', false)
            ->getContent();

        $photoClass = $this->photo($this->xpath($html), 'Capa do Honda Fit')->getAttribute('class');
        $this->assertStringContainsString('object-contain', $photoClass);
        $this->assertStringNotContainsString('object-cover', $photoClass);
    }

    public function test_admin_vehicle_detail_uses_the_hero_and_the_list_uses_thumbs(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'cover_photo_path' => 'vehicle-covers/corolla.jpg',
        ]);

        $detail = $this->actingAs($admin)
            ->get(route('admin.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-vehicle-cover="hero"', false)
            ->assertDontSee('h-40 w-64', false)
            ->getContent();

        $photoClass = $this->photo($this->xpath($detail), 'Capa do Toyota Corolla')->getAttribute('class');
        $this->assertStringContainsString('object-contain', $photoClass);

        $this->actingAs($admin)
            ->get(route('admin.vehicles.index'))
            ->assertOk()
            ->assertSee('data-vehicle-cover="thumb"', false)
            ->assertDontSee('h-12 w-20', false)
            ->assertDontSee('input-field', false)
            ->assertSee('id="admin-vehicle-search"', false)
            ->assertSee('form-input', false);
    }

    public function test_owner_portal_covers_come_from_the_blade_component(): void
    {
        $blade = file_get_contents(resource_path('views/components/vehicle-cover.blade.php'));
        $portal = file_get_contents(resource_path('js/user-portal.js'));

        // O portal do proprietário é renderizado no servidor: a capa sai só de x-vehicle-cover, e o
        // JS não tem mais uma cópia da moldura para manter em sincronia.
        $this->assertStringNotContainsString('vehicleCoverHtml', $portal);
        $this->assertStringNotContainsString('object-cover', $portal);
        $this->assertStringNotContainsString('🚗', $portal);

        foreach (['index', 'show', 'edit'] as $view) {
            $source = file_get_contents(resource_path("views/user/vehicles/{$view}.blade.php"));
            $this->assertStringNotContainsString('object-cover', $source, "user/vehicles/{$view}.blade.php");
        }

        // Legenda sem foto: automotive-600 dá 6,06:1 sobre a moldura bg-automotive-100 (automotive-500 dava 4,24:1).
        $this->assertStringContainsString('<span class="text-xs font-medium text-automotive-600">Sem foto de capa</span>', $blade);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function frame(DOMXPath $xpath): DOMElement
    {
        $frame = $xpath->query('//*[@data-vehicle-cover]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $frame, 'Cover frame not found.');

        return $frame;
    }

    private function photo(DOMXPath $xpath, string $alt): DOMElement
    {
        $photo = $xpath->query('//img[@alt="'.$alt.'"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $photo, "Cover image with alt '{$alt}' not found.");

        return $photo;
    }
}
