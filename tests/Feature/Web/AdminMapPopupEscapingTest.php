<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os pinos dos mapas do admin chegam ao navegador num <script type="application/json"> com o texto
 * escapado, e resources/js/admin-map.js monta o popup com nós DOM (textContent), nunca com HTML.
 */
class AdminMapPopupEscapingTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT_NAME = '<script>alert("xss")</script>';

    private const IMG_PAYLOAD = '<img src=x onerror=alert(1)>';

    public function test_users_map_encodes_the_pin_payload(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $pinned = User::factory()->asUser()->create([
            'name' => self::SCRIPT_NAME,
            'email' => 'pin-privado@example.test',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'street' => self::IMG_PAYLOAD,
            'number' => '10',
            'city' => self::IMG_PAYLOAD,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.maps.users'))
            ->assertOk()
            ->assertDontSee(self::SCRIPT_NAME, false)
            ->assertDontSee('<img src=x onerror', false)
            ->assertSee('<script type="application/json" data-admin-map-pins>', false)
            ->assertSee($this->scriptSafeJson(self::SCRIPT_NAME), false)
            ->assertSee($this->scriptSafeJson(self::IMG_PAYLOAD), false)
            ->assertDontSee($pinned->email)
            ->assertDontSee('bindPopup', false);

        $pins = $response->viewData('pins');

        $this->assertCount(1, $pins);
        $this->assertSame(['id', 'name', 'lat', 'lng', 'city', 'label'], array_keys($pins[0]));
        $this->assertSame(self::SCRIPT_NAME, $pins[0]['name']);
    }

    public function test_workshops_map_encodes_the_pin_payload_without_the_owner_account(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $workshop = Workshop::factory()->create([
            'name' => self::SCRIPT_NAME,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'street' => self::IMG_PAYLOAD,
            'number' => '1000',
            'city' => 'São Paulo',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.maps.workshops'))
            ->assertOk()
            ->assertDontSee(self::SCRIPT_NAME, false)
            ->assertDontSee('<img src=x onerror', false)
            ->assertSee($this->scriptSafeJson(self::SCRIPT_NAME), false)
            ->assertSee($this->scriptSafeJson(self::IMG_PAYLOAD), false);

        $pins = $response->viewData('pins');

        $this->assertCount(1, $pins);
        $this->assertSame(['id', 'name', 'lat', 'lng', 'city', 'label'], array_keys($pins[0]));
        $this->assertArrayNotHasKey('user_id', $pins[0], 'O link do cadastro é montado no servidor, fora do JSON.');
        $this->assertSame([$workshop->id => route('admin.users.show', $workshop->user_id)], $response->viewData('pinLinks'));
    }

    public function test_map_script_builds_the_popup_with_text_nodes(): void
    {
        $script = file_get_contents(resource_path('js/admin-map.js'));

        $this->assertStringContainsString('title.textContent = pin.name', $script);
        $this->assertStringContainsString('label.textContent = pin.label', $script);
        $this->assertStringContainsString("link.textContent = 'Abrir cadastro'", $script);
        $this->assertStringContainsString('marker.bindPopup(buildPinPopup(pin, profileHref(root, pin.id)))', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
        $this->assertStringNotContainsString('bindPopup(\'<', $script);
        $this->assertStringContainsString('getPropertyValue(token)', $script, 'A cor do pino vem dos tokens do CSS.');
    }

    /**
     * The value as @json writes it: tags, quotes and ampersands hex-escaped, so the string cannot
     * close the script block.
     */
    private function scriptSafeJson(string $value): string
    {
        return trim(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), '"');
    }
}
