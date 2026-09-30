<?php

namespace Tests\Feature\DesignSystem;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class PaginationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_vehicle_list_renders_the_project_pagination_in_portuguese(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        Vehicle::factory()->count(25)->create();

        $this->actingAs($admin)
            ->get(route('admin.vehicles.index'))
            ->assertOk()
            ->assertSee('aria-label="Paginação"', false)
            ->assertSee('Mostrando', false)
            ->assertSee('1–20', false)
            ->assertSee('Próxima', false)
            ->assertSee('rel="next"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Ir para a página 2', false)
            ->assertDontSee('rel="prev"', false)
            ->assertDontSee('Pagination Navigation', false);
    }

    public function test_second_page_links_back_and_marks_the_last_page_as_unavailable(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        Vehicle::factory()->count(25)->create();

        $this->actingAs($admin)
            ->get(route('admin.vehicles.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('21–25', false)
            ->assertSee('Anterior', false)
            ->assertSee('rel="prev"', false)
            ->assertDontSee('rel="next"', false)
            ->assertSee('aria-disabled="true"', false);
    }

    public function test_single_page_lists_render_no_pagination(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        Vehicle::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.vehicles.index'))
            ->assertOk()
            ->assertDontSee('aria-label="Paginação"', false);
    }

    public function test_length_aware_view_shows_page_position_for_mobile(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 50, 10, 3, ['path' => '/lista']);

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('aria-label="Paginação"', $html);
        $this->assertMatchesRegularExpression('/Página\s*<span[^>]*>3<\/span>\s*de\s*<span[^>]*>5<\/span>/', $html);
        $this->assertStringContainsString('href="/lista?page=2"', $html);
        $this->assertStringContainsString('href="/lista?page=4"', $html);
        $this->assertStringContainsString('bg-automotive-900 text-white', $html);
        $this->assertStringNotContainsString('dark:', $html);
    }

    public function test_length_aware_view_keeps_a_compact_page_window_on_long_lists(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 20), 240, 20, 6, ['path' => '/lista']);

        $html = $paginator->links()->toHtml();

        foreach ([1, 5, 7, 12] as $page) {
            $this->assertStringContainsString("aria-label=\"Ir para a página {$page}\"", $html);
        }

        foreach ([2, 3, 4, 8, 9, 10, 11] as $page) {
            $this->assertStringNotContainsString("aria-label=\"Ir para a página {$page}\"", $html);
        }

        $this->assertSame(2, substr_count($html, '…'));
        $this->assertStringContainsString('101–120', $html);
    }

    public function test_simple_view_uses_portuguese_labels_and_project_tokens(): void
    {
        $paginator = new Paginator(range(1, 3), 2, 2, ['path' => '/lista']);

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('aria-label="Paginação"', $html);
        $this->assertStringContainsString('Anterior', $html);
        $this->assertStringContainsString('Próxima', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('border-automotive-200', $html);
        $this->assertStringNotContainsString('dark:', $html);
        $this->assertStringNotContainsString('Previous', $html);
    }
}
