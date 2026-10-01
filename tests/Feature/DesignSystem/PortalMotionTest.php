<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Movimento nos portais (NAV-29, USR-34, WRK-33, ADM-33, GAR-24): só microinterações funcionais,
 * curtas e com os tokens (150–250ms, ease-smooth-out), todas desligadas por prefers-reduced-motion:
 * check de 1,5s ao copiar, toast ao salvar, fade de 150ms na troca de painel, aba ou lista e a
 * abertura de sheet, dropdown e diálogo. Nada decorativo do ObsidianUI (click-spark, liquid-metal).
 */
class PortalMotionTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_every_page_respects_reduced_motion_globally(): void
    {
        $stylesheet = File::get(resource_path('css/app.css'));
        $reduce = substr($stylesheet, (int) strrpos($stylesheet, '@media (prefers-reduced-motion: reduce)'));

        foreach (['animation-duration: 0.01ms !important;', 'animation-iteration-count: 1 !important;', 'transition-duration: 0.01ms !important;', 'scroll-behavior: auto !important;'] as $declaration) {
            $this->assertStringContainsString($declaration, $reduce);
        }

        $this->assertStringContainsString('<html lang="pt-BR" class="motion-safe:scroll-smooth">', File::get(resource_path('views/layouts/app.blade.php')));
        $this->assertStringNotContainsString('scroll-smooth', File::get(resource_path('views/layouts/admin.blade.php')));
    }

    public function test_motion_tokens_stay_between_150_and_500_ms_with_the_smooth_out_easing(): void
    {
        $stylesheet = File::get(resource_path('css/app.css'));

        preg_match_all('/--duration-(\w+):\s*(\d+)ms;/', $stylesheet, $durations, PREG_SET_ORDER);

        $this->assertNotEmpty($durations);

        foreach ($durations as [, $name, $milliseconds]) {
            $this->assertGreaterThanOrEqual(150, (int) $milliseconds, "--duration-{$name}");
            $this->assertLessThanOrEqual(500, (int) $milliseconds, "--duration-{$name}");
        }

        $this->assertStringContainsString('--ease-smooth-out: cubic-bezier(0.22, 1, 0.36, 1);', $stylesheet);
    }

    public function test_copy_confirms_with_a_check_for_one_and_a_half_seconds(): void
    {
        $this->assertStringContainsString('export const COPIED_FEEDBACK_MS = 1500;', File::get(resource_path('js/ui/copy.js')));

        $component = File::get(resource_path('views/components/ui/copy-button.blade.php'));
        $this->assertStringContainsString('group-data-copied/copy:opacity-100', $component);
        $this->assertStringContainsString('motion-reduce:transition-none', $component);
    }

    public function test_tab_panels_and_toasts_fade_briefly_and_only_with_motion(): void
    {
        $panel = File::get(resource_path('views/components/ui/tab-panel.blade.php'));
        $this->assertStringContainsString('motion-safe:transition-opacity motion-safe:duration-fast motion-safe:ease-smooth-out motion-safe:starting:opacity-0', $panel);

        $toast = File::get(resource_path('js/ui/toast.js'));
        $this->assertStringContainsString('duration-base ease-smooth-out data-[state=closed]:duration-fast motion-reduce:transition-none', $toast);
        $this->assertStringContainsString('motion-safe:translate-y-2', $toast, 'Com prefers-reduced-motion o toast só muda a opacidade.');
    }

    public function test_provenance_filter_fades_returning_cards_only_after_the_first_filter(): void
    {
        $stylesheet = File::get(resource_path('css/app.css'));
        $script = File::get(resource_path('js/provenance-ui.js'));

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s*\[data-provenance-filtered\] \[data-provenance-list\] > \[data-verified\] \{\s*transition: opacity var\(--duration-fast\) var\(--ease-smooth-out\);/', $stylesheet);
        $this->assertMatchesRegularExpression('/@starting-style \{\s*\[data-provenance-filtered\] \[data-provenance-list\] > \[data-verified\] \{\s*opacity: 0;/', $stylesheet);
        $this->assertStringContainsString("root.setAttribute('data-provenance-filtered', '');", $script);
        // O card com link tem transição própria (utilitário vence a camada components): inclui opacity.
        $this->assertStringContainsString("'relative transition-[box-shadow,opacity] duration-fast ease-smooth-out motion-reduce:transition-none hover:shadow-md' => \$cardIsLink", File::get(resource_path('views/components/provenance-card.blade.php')));
        $this->assertStringNotContainsString('data-provenance-filtered', File::get(resource_path('views/components/vehicle/detail.blade.php')), 'Ao carregar a ficha nada pisca: o atributo só entra com o primeiro filtro.');
    }

    public function test_admin_maintenance_list_fades_while_it_reloads(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index')));
        $results = $this->adminElement($xpath, '//div[@data-admin-maintenances-results]');
        $classes = preg_split('/\s+/', trim($results->getAttribute('class')));

        foreach (['transition-opacity', 'duration-fast', 'ease-smooth-out', 'motion-reduce:transition-none'] as $class) {
            $this->assertContains($class, $classes);
        }

        $this->assertStringContainsString("const LOADING_CLASSES = ['opacity-60', 'pointer-events-none'];", File::get(resource_path('js/admin-maintenances-filters.js')));
    }

    public function test_portals_do_not_ship_decorative_effects(): void
    {
        foreach (File::allFiles(resource_path('js')) as $file) {
            $source = $file->getContents();

            foreach (['lenis', 'gsap', 'click-spark', 'liquid-metal', 'framer-motion'] as $effect) {
                $this->assertStringNotContainsStringIgnoringCase($effect, $source, "{$file->getRelativePathname()}: sem {$effect}.");
            }
        }

        $package = json_decode(File::get(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $dependencies = array_keys(($package['dependencies'] ?? []) + ($package['devDependencies'] ?? []));

        foreach (['gsap', 'lenis', 'motion', 'framer-motion', '@studio-freight/lenis'] as $library) {
            $this->assertNotContains($library, $dependencies, "{$library} não entra: o movimento é CSS e Web Animations.");
        }
    }
}
