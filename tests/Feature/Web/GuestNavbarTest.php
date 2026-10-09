<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestNavbarTest extends TestCase
{
    use RefreshDatabase;

    private const LANDING_LABELS = ['Como funciona', 'Procedência', 'Produto', 'Para quem', 'Preço'];

    public function test_home_menu_links_landing_sections_with_bare_anchors(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['#como-funciona', '#procedencia', '#produto', '#para-quem', '#preco'] as $anchor) {
            $response->assertSee('href="'.$anchor.'"', false);
        }
    }

    public function test_public_pages_show_full_menu_with_anchors_pointing_to_home(): void
    {
        foreach (['/para-oficinas', route('blog.index')] as $url) {
            $response = $this->get($url)->assertOk();

            foreach (self::LANDING_LABELS as $label) {
                $response->assertSee('>'.$label.'<', false);
            }

            $response->assertSee('>Blog<', false);

            foreach (['#como-funciona', '#procedencia', '#produto', '#para-quem', '#preco'] as $anchor) {
                $response->assertSee('href="'.route('home').$anchor.'"', false);
            }

            $response->assertDontSee('href="#como-funciona"', false);
        }
    }

    public function test_logged_in_owner_dashboard_does_not_show_landing_links(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('home').'#como-funciona"', false)
            ->assertDontSee('href="#como-funciona"', false)
            ->assertDontSee('href="'.route('home').'#preco"', false);
    }
}
