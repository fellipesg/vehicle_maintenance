<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Início do Lojista: KPIs com os totais reais do estoque, "Prontos para vender" (veículos com ao menos
 * um Selo da oficina), "Precisam de atenção" (sem histórico, só declaradas e procuração pendente ou
 * recusada) e as manutenções recentes do estoque.
 *
 * KPIs e listas contam o histórico dos veículos do estoque (inclusive Selo da oficina e declaradas
 * por donos anteriores), não só o que o lojista registrou. Consignação só entra no histórico com a
 * procuração aprovada, como em VehiclePolicy::viewMaintenances.
 */
class DashboardController extends Controller
{
    /**
     * Quantos veículos cada lista do Início mostra; o resto fica no Estoque, pelo "Ver todos".
     */
    public const LIST_LIMIT = 5;

    public function index(Request $request): View
    {
        $user = $request->user();
        $vehicles = $user->stockVehicles()
            ->with('provenanceStripMaintenances')
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn (Builder $query) => $query->whereNotNull('verified_at'),
            ])
            ->orderByDesc('user_vehicles.created_at')
            ->orderByDesc('vehicles.id')
            ->get();

        $historyVehicles = $vehicles->filter(fn (Vehicle $vehicle): bool => $user->canViewStockVehicleHistory($vehicle))->values();
        $historyVehicleIds = $historyVehicles->modelKeys();
        $readyVehicles = $historyVehicles->filter(fn (Vehicle $vehicle): bool => (int) $vehicle->verified_maintenances_count > 0)->values();
        $declaredOnlyVehicles = $historyVehicles
            ->filter(fn (Vehicle $vehicle): bool => (int) $vehicle->maintenances_count > 0 && (int) $vehicle->verified_maintenances_count === 0)
            ->values();
        $withoutHistoryVehicles = $historyVehicles->filter(fn (Vehicle $vehicle): bool => (int) $vehicle->maintenances_count === 0)->values();

        $stats = [
            'vehicles' => $vehicles->count(),
            'consignment' => $vehicles->filter(fn (Vehicle $vehicle): bool => $user->holdsOnConsignment($vehicle))->count(),
            'sealed_vehicles' => $readyVehicles->count(),
            'declared_only_vehicles' => $declaredOnlyVehicles->count(),
            'without_history' => $withoutHistoryVehicles->count(),
            'sealed' => Maintenance::query()->whereIn('vehicle_id', $historyVehicleIds)->whereNotNull('verified_at')->count(),
            'declared' => Maintenance::query()->whereIn('vehicle_id', $historyVehicleIds)->whereNull('verified_at')->count(),
        ];

        $attention = $this->attentionItems($user, $vehicles, $declaredOnlyVehicles, $withoutHistoryVehicles);

        $recentMaintenances = Maintenance::query()
            ->whereIn('vehicle_id', $historyVehicleIds)
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->withCount(['invoices', 'photos'])
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        return view('garage.dashboard', [
            'stats' => $stats,
            'readyVehicles' => $readyVehicles->sortByDesc('verified_maintenances_count')->take(self::LIST_LIMIT)->values(),
            'attention' => $attention->take(self::LIST_LIMIT)->values(),
            'attentionTotal' => $attention->count(),
            'recentMaintenances' => $recentMaintenances,
        ]);
    }

    /**
     * Veículos que pedem uma ação do lojista, na ordem de urgência: procuração recusada ou não
     * enviada, procuração em análise, sem nenhuma manutenção e histórico só com declaradas.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, Vehicle>  $declaredOnlyVehicles
     * @param  Collection<int, Vehicle>  $withoutHistoryVehicles
     * @return Collection<int, array{vehicle: Vehicle, reason: string, can_open: bool, can_add_maintenance: bool}>
     */
    private function attentionItems(User $user, Collection $vehicles, Collection $declaredOnlyVehicles, Collection $withoutHistoryVehicles): Collection
    {
        $consignmentWaiting = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => $user->holdsOnConsignment($vehicle) && ! $user->canViewStockVehicleHistory($vehicle))
            ->map(fn (Vehicle $vehicle): array => [
                'vehicle' => $vehicle,
                'reason' => match ($user->consignmentFor($vehicle)?->history_access_status) {
                    VehicleConsignment::HISTORY_PENDING => 'consignment_pending',
                    VehicleConsignment::HISTORY_REJECTED => 'consignment_rejected',
                    default => 'consignment_missing',
                },
                // A consignação declarada já abre o veículo e aceita manutenção; o que falta aqui
                // é só o histórico anterior.
                'can_open' => true,
                'can_add_maintenance' => $user->consignmentFor($vehicle)?->allowsMaintenance() ?? false,
            ])
            ->sortBy(fn (array $item): int => $item['reason'] === 'consignment_pending' ? 1 : 0);

        $historyItem = fn (string $reason): \Closure => fn (Vehicle $vehicle): array => [
            'vehicle' => $vehicle,
            'reason' => $reason,
            'can_open' => true,
            'can_add_maintenance' => ! $user->holdsOnConsignment($vehicle),
        ];

        return $consignmentWaiting
            ->concat($withoutHistoryVehicles->map($historyItem('without_history')))
            ->concat($declaredOnlyVehicles->map($historyItem('declared_only')))
            ->values();
    }
}
