<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Admin\MonthlyProvenanceSeries;
use Illuminate\View\View;

/**
 * Visão geral do admin: KPIs clicáveis (com o que entrou nos últimos 30 dias), a adoção do Selo da
 * oficina, o gráfico mensal Selo × Declaradas, os cadastros recentes e as pendências. A lista
 * completa de usuários fica em admin.users.index.
 */
class DashboardController extends Controller
{
    /**
     * Janela das variações dos KPIs ("+3 nos últimos 30 dias").
     */
    private const RECENT_DAYS = 30;

    public function index(MonthlyProvenanceSeries $monthlySeries): View
    {
        $recentSince = now()->subDays(self::RECENT_DAYS);

        $maintenanceCount = Maintenance::query()->count();
        $sealedMaintenanceCount = Maintenance::query()->whereNotNull('verified_at')->count();

        return view('admin.dashboard', [
            'recentDays' => self::RECENT_DAYS,
            'userCount' => User::query()->count(),
            'newUserCount' => User::query()->where('created_at', '>=', $recentSince)->count(),
            'usersByType' => User::query()
                ->selectRaw('user_type, count(*) as total')
                ->groupBy('user_type')
                ->pluck('total', 'user_type'),
            'vehicleCount' => Vehicle::query()->count(),
            'newVehicleCount' => Vehicle::query()->where('created_at', '>=', $recentSince)->count(),
            'maintenanceCount' => $maintenanceCount,
            'newMaintenanceCount' => Maintenance::query()->where('created_at', '>=', $recentSince)->count(),
            'sealedMaintenanceCount' => $sealedMaintenanceCount,
            'sealedPercent' => $maintenanceCount > 0 ? (int) round($sealedMaintenanceCount / $maintenanceCount * 100) : 0,
            'workshopCount' => Workshop::query()->count(),
            'workshopsWithoutCoordinatesCount' => Workshop::query()
                ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
            'scheduledPostCount' => BlogPost::query()->scheduled()->count(),
            'draftPostCount' => BlogPost::query()->where('status', BlogPost::STATUS_DRAFT)->count(),
            'recentUsers' => User::query()->latest()->latest('id')->limit(5)->get(),
            'monthlySeries' => $monthlySeries->lastMonths(12),
        ]);
    }
}
