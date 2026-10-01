<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Admin\MonthlyProvenanceSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Visão geral do admin: KPIs clicáveis com o que entrou nos últimos 30 dias, a fatia com Selo da
 * oficina, o gráfico mensal Selo × Declaradas, os cadastros recentes e as pendências.
 */
class AdminOverviewTest extends TestCase
{
    use InspectsAdminPages;
    use InspectsUiMarkup;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));
    }

    public function test_kpis_link_to_their_lists_and_show_the_last_30_days(): void
    {
        $admin = $this->adminUser(['created_at' => now()->subYear()]);
        User::factory()->asUser()->create(['created_at' => now()->subDays(5)]);
        User::factory()->asGarage()->create(['created_at' => now()->subDays(90)]);
        $vehicle = Vehicle::factory()->create(['created_at' => now()->subDays(2)]);
        Maintenance::factory()->for($vehicle)->sealedByWorkshop()->create();
        Maintenance::factory()->count(3)->for($vehicle)->declaredByOwner()->create();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.dashboard')));
        $stats = $this->stats($xpath);

        $this->assertSame(route('admin.users.index'), $stats['Usuários']['href']);
        $this->assertSame(route('admin.vehicles.index'), $stats['Veículos']['href']);
        $this->assertSame(route('admin.maintenances.index'), $stats['Manutenções']['href']);
        $this->assertSame(route('admin.workshops.index'), $stats['Oficinas']['href']);

        // O sealedByWorkshop cria uma oficina (e a conta dela): 4 usuários, 1 criado no dia.
        $this->assertSame((string) User::count(), $stats['Usuários']['value']);
        $this->assertStringContainsString('nos últimos 30 dias', $stats['Usuários']['trend']);
        $this->assertStringStartsWith('Aumento: +', $stats['Usuários']['trend']);
        $this->assertSame('Aumento: +1 nos últimos 30 dias', $stats['Veículos']['trend']);
        $this->assertSame('4', $stats['Manutenções']['value']);
        $this->assertSame('25% com Selo da oficina', $stats['Manutenções']['hint']);
        $this->assertSame(1, $xpath->query('//*[@data-slot="sealed-share"][@aria-hidden="true"]/div[contains(@style, "width: 25%")]')->length);
    }

    public function test_kpi_without_new_records_says_so_without_an_arrow_up(): void
    {
        $admin = $this->adminUser(['created_at' => now()->subYear()]);

        $stats = $this->stats($this->adminPage($this->actingAs($admin)->get(route('admin.dashboard'))));

        $this->assertSame('Estável: Nenhum veículo novo nos últimos 30 dias', $stats['Veículos']['trend']);
        $this->assertSame('0% com Selo da oficina', $stats['Manutenções']['hint']);
    }

    public function test_chart_splits_each_month_into_sealed_and_declared(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->for($vehicle)->sealedByWorkshop()->create(['maintenance_date' => '2026-09-03']);
        Maintenance::factory()->count(2)->for($vehicle)->declaredByOwner()->create(['maintenance_date' => '2026-09-10']);
        Maintenance::factory()->for($vehicle)->declaredByOwner()->create(['maintenance_date' => '2026-02-20']);
        Maintenance::factory()->for($vehicle)->declaredByOwner()->create(['maintenance_date' => '2025-06-01']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $chart = $this->adminElement($xpath, '//section[@id="manutencoes-por-mes"]//figure[@data-slot="provenance-chart"]');
        $legend = array_map(fn ($item): string => $this->adminText($item), $this->adminElements($xpath, './/*[@data-slot="provenance-chart-legend"]/li', $chart));
        $this->assertSame(['Selo da oficina', 'Declaradas'], $legend);

        $plot = $this->adminElement($xpath, './/*[@data-slot="provenance-chart-plot"]', $chart);
        $this->assertSame('img', $plot->getAttribute('role'));
        $this->assertStringContainsString('Outubro de 2025 a Setembro de 2026', $plot->getAttribute('aria-label'));
        $this->assertStringContainsString('4 no total, 1 com Selo da oficina (25%) e 3 declaradas', $plot->getAttribute('aria-label'));
        $this->assertStringContainsString('Mês com mais registros: Setembro de 2026, com 3.', $plot->getAttribute('aria-label'));

        $this->assertCount(12, $this->adminElements($xpath, './/*[@data-chart-month]', $chart));
        $september = $this->adminElement($xpath, './/*[@data-chart-month="2026-09"]', $chart);
        $this->assertSame(1, $xpath->query('.//*[@data-segment="sealed"]', $september)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-segment="declared"]', $september)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-segment]', $this->adminElement($xpath, './/*[@data-chart-month="2026-08"]', $chart))->length);

        $rows = [];
        foreach ($this->adminElements($xpath, './/details[@data-slot="provenance-chart-table"]//tbody/tr', $chart) as $row) {
            $rows[$this->adminText($this->adminElement($xpath, './th', $row))] = array_map(fn ($cell): string => $this->adminText($cell), $this->adminElements($xpath, './td', $row));
        }
        $this->assertSame(['1', '2', '3'], $rows['Setembro de 2026']);
        $this->assertSame(['0', '1', '1'], $rows['Fevereiro de 2026']);
        $this->assertArrayNotHasKey('Junho de 2025', $rows, 'Fora da janela de 12 meses.');
    }

    public function test_series_fills_months_without_maintenance_with_zero(): void
    {
        Maintenance::factory()->sealedByWorkshop()->create(['maintenance_date' => '2026-07-31']);

        $series = app(MonthlyProvenanceSeries::class)->lastMonths(3, Carbon::parse('2026-09-30'));

        $this->assertSame(['2026-07', '2026-08', '2026-09'], array_column($series, 'month'));
        $this->assertSame(['Jul', 'Ago', 'Set'], array_column($series, 'label'));
        $this->assertSame([1, 0, 0], array_column($series, 'sealed'));
        $this->assertSame([0, 0, 0], array_column($series, 'declared'));
    }

    public function test_chart_component_requires_a_series(): void
    {
        $this->assertUiRejects('<x-admin.provenance-chart :series="[]" />', 'x-admin.provenance-chart precisa de series');
    }

    public function test_recent_signups_list_the_five_newest_accounts(): void
    {
        $admin = $this->adminUser(['created_at' => now()->subYears(2)]);
        foreach (range(1, 6) as $day) {
            User::factory()->asUser()->create(['name' => "Conta {$day}", 'created_at' => now()->subDays($day)]);
        }

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.dashboard')));
        $section = $this->adminElement($xpath, '//section[@id="cadastros-recentes"]');

        $names = array_map(fn ($link): string => $this->adminText($link), $this->adminElements($xpath, './/ul[@data-slot="recent-users"]/li//a', $section));
        $this->assertSame(['Conta 1', 'Conta 2', 'Conta 3', 'Conta 4', 'Conta 5'], $names);
        $this->assertSame(route('admin.users.index'), $this->adminElement($xpath, './/a[contains(., "Ver todos os usuários")]', $section)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//table[.//caption[contains(., "Usuários cadastrados")]]')->length, 'A lista completa saiu da Visão geral.');
    }

    public function test_pending_items_link_to_the_filtered_lists(): void
    {
        Workshop::factory()->create(['latitude' => null, 'longitude' => null]);
        BlogPost::factory()->scheduled()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));
        $pending = $this->adminElement($xpath, '//section[@id="pendencias"]//ul[@data-slot="pending-items"]');

        $links = [];
        foreach ($this->adminElements($xpath, './li', $pending) as $item) {
            $links[$this->adminText($this->adminElement($xpath, './/p[1]', $item))] = $this->adminElement($xpath, './/a', $item)->getAttribute('href');
        }

        $this->assertSame(route('admin.workshops.index', ['localizacao' => 'sem-coordenadas']), $links[Workshop::count().' '.(Workshop::count() === 1 ? 'oficina sem coordenadas' : 'oficinas sem coordenadas')]);
        $this->assertSame(route('admin.blog.index', ['status' => 'scheduled']), $links['1 artigo agendado']);
        $this->assertCount(2, $links, 'Sem rascunhos, o item de rascunhos não aparece.');
    }

    public function test_nothing_pending_shows_an_empty_state(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $this->assertStringContainsString('Nada pendente', $this->adminText($this->adminElement($xpath, '//section[@id="pendencias"]//*[@data-slot="empty-state"]')));
    }

    /**
     * @return array<string, array{value: string, href: string, hint: string, trend: string}>
     */
    private function stats(\DOMXPath $xpath): array
    {
        $stats = [];

        foreach ($this->adminElements($xpath, '//*[@data-slot="admin-kpis"]/*[@data-slot="stat"]') as $stat) {
            $label = $this->adminText($this->adminElement($xpath, './/dt', $stat));
            $hint = $xpath->query('.//*[@data-slot="stat-hint"]', $stat)->item(0);
            $trend = $xpath->query('.//*[@data-slot="stat-trend"]', $stat)->item(0);
            $stats[$label] = [
                'value' => $this->adminText($this->adminElement($xpath, './/dd', $stat)),
                'href' => $stat->getAttribute('href'),
                'hint' => $hint ? $this->adminText($hint) : '',
                'trend' => $trend ? $this->adminText($trend) : '',
            ];
        }

        return $stats;
    }
}
