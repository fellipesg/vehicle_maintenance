<?php

namespace App\Services\User;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Dados do Início do proprietário (/usuario/dashboard), montados no servidor com o mesmo escopo
 * da API: os veículos de que a conta é dona atual (User::currentVehicles(), como /api/v1/my-vehicles)
 * e as manutenções do tenant da conta (como /api/v1/maintenances).
 *
 * - KPIs com totais reais (não o tamanho de uma lista limitada).
 * - Próxima revisão: a mais próxima entre os veículos (VehicleMaintenanceReminderService).
 * - Pendências acionáveis: revisão chegando ou em atraso, chassi não informado, capa ausente e
 *   garantia vencendo em até 30 dias.
 * - Primeiros passos (primeiro uso): adicionar veículo, registrar manutenção, exportar o PDF.
 */
class OwnerDashboard
{
    /**
     * Garantias que vencem neste intervalo entram nas pendências.
     */
    public const WARRANTY_WINDOW_DAYS = 30;

    /**
     * Veículos e manutenções mostrados nas listas do Início.
     */
    public const LIST_LIMIT = 5;

    public function __construct(private readonly VehicleMaintenanceReminderService $reminders) {}

    /**
     * @return array{
     *     vehicles: Collection<int, Vehicle>,
     *     vehicle_count: int,
     *     maintenance_count: int,
     *     sealed_count: int,
     *     declared_count: int,
     *     recent_maintenances: Collection<int, Maintenance>,
     *     next_revision: array{vehicle: Vehicle, next_due_kilometers: int, kilometers_remaining: int, is_overdue: bool, progress_percent: int}|null,
     *     revisions: array<int, array{next_due_kilometers: int|null, kilometers_remaining: int|null, is_overdue: bool, is_near: bool}>,
     *     pending: list<array{key: string, tone: string, icon: string, title: string, description: string, url: string, action: string}>,
     *     first_steps: array{items: list<array{key: string, title: string, description: string, done: bool, url: string|null, action: string}>, done: int, total: int, complete: bool}
     * }
     */
    public function build(User $user): array
    {
        $vehicles = $user->currentVehicles()
            ->with([
                'provenanceStripMaintenances',
                // A estimativa da revisão só usa a quilometragem de cada manutenção.
                'maintenances' => fn ($query) => $query->select(['id', 'vehicle_id', 'kilometers']),
            ])
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn (Builder $query) => $query->whereNotNull('verified_at'),
            ])
            ->orderByDesc('vehicles.created_at')
            ->get();

        $maintenanceCount = $this->maintenancesOf($user)->count();
        $sealedCount = $this->maintenancesOf($user)->whereNotNull('verified_at')->count();

        $recentMaintenances = $this->maintenancesOf($user)
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->withCount(['invoices', 'photos'])
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        $revisions = $this->revisions($vehicles);

        return [
            'vehicles' => $vehicles,
            'vehicle_count' => $vehicles->count(),
            'maintenance_count' => $maintenanceCount,
            'sealed_count' => $sealedCount,
            'declared_count' => $maintenanceCount - $sealedCount,
            'recent_maintenances' => $recentMaintenances,
            'next_revision' => $this->nextRevision($vehicles, $revisions),
            'revisions' => $revisions,
            'pending' => $this->pending($user, $vehicles, $revisions),
            'first_steps' => $this->firstSteps($user, $vehicles, $maintenanceCount),
        ];
    }

    /**
     * Mesmo escopo de /api/v1/maintenances para o proprietário: o tenant da conta.
     *
     * @return Builder<Maintenance>
     */
    public function maintenancesOf(User $user): Builder
    {
        return Maintenance::query()->where('tenant_id', $user->tenant_id);
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, array{next_due_kilometers: int|null, kilometers_remaining: int|null, is_overdue: bool, is_near: bool, progress_percent: float|null}>
     */
    private function revisions(Collection $vehicles): array
    {
        $revisions = [];

        foreach ($vehicles as $vehicle) {
            $summary = $this->reminders->summarize($vehicle);
            $remaining = $summary['kilometers_remaining'];

            $revisions[$vehicle->id] = [
                'next_due_kilometers' => $summary['next_due_kilometers'],
                'kilometers_remaining' => $remaining,
                'is_overdue' => $summary['is_overdue'],
                'is_near' => $summary['next_due_kilometers'] !== null
                    && ! $summary['is_overdue']
                    && $remaining !== null
                    && $remaining <= $summary['notify_before_kilometers'],
                'progress_percent' => $summary['progress_percent'],
            ];
        }

        return $revisions;
    }

    /**
     * A revisão mais próxima entre os veículos: as em atraso primeiro, depois a que falta menos.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  array<int, array{next_due_kilometers: int|null, kilometers_remaining: int|null, is_overdue: bool, is_near: bool, progress_percent: float|null}>  $revisions
     * @return array{vehicle: Vehicle, next_due_kilometers: int, kilometers_remaining: int, is_overdue: bool, progress_percent: int}|null
     */
    private function nextRevision(Collection $vehicles, array $revisions): ?array
    {
        $candidate = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => ($revisions[$vehicle->id]['next_due_kilometers'] ?? null) !== null)
            ->sortBy(fn (Vehicle $vehicle): array => [
                $revisions[$vehicle->id]['is_overdue'] ? 0 : 1,
                (int) $revisions[$vehicle->id]['kilometers_remaining'],
            ])
            ->first();

        if ($candidate === null) {
            return null;
        }

        $revision = $revisions[$candidate->id];

        return [
            'vehicle' => $candidate,
            'next_due_kilometers' => (int) $revision['next_due_kilometers'],
            'kilometers_remaining' => (int) $revision['kilometers_remaining'],
            'is_overdue' => $revision['is_overdue'],
            'progress_percent' => (int) min(100, max(0, round((float) ($revision['progress_percent'] ?? 0)))),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  array<int, array{next_due_kilometers: int|null, kilometers_remaining: int|null, is_overdue: bool, is_near: bool, progress_percent: float|null}>  $revisions
     * @return list<array{key: string, tone: string, icon: string, title: string, description: string, url: string, action: string}>
     */
    private function pending(User $user, Collection $vehicles, array $revisions): array
    {
        $items = [];

        foreach ($vehicles as $vehicle) {
            $name = $this->vehicleName($vehicle);
            $revision = $revisions[$vehicle->id] ?? null;

            if ($revision !== null && ($revision['is_overdue'] || $revision['is_near'])) {
                $items[] = [
                    'key' => 'revisao-'.$vehicle->id,
                    'tone' => $revision['is_overdue'] ? 'danger' : 'warning',
                    'icon' => 'wrench-screwdriver',
                    'title' => $revision['is_overdue']
                        ? "Revisão do {$name} em atraso"
                        : "Revisão do {$name} em ".$this->km((int) $revision['kilometers_remaining']),
                    'description' => 'Estimada aos '.$this->km((int) $revision['next_due_kilometers']).'. Depois do serviço, registre a manutenção com a quilometragem.',
                    'url' => route('user.maintenances.create', ['vehicle_id' => $vehicle->id]),
                    'action' => 'Registrar manutenção',
                ];
            }

            if (blank($vehicle->chassis)) {
                $items[] = [
                    'key' => 'chassi-'.$vehicle->id,
                    'tone' => 'warning',
                    'icon' => 'identification',
                    'title' => "Informe o chassi do {$name}",
                    'description' => 'O chassi identifica o veículo para sempre; a placa pode mudar.',
                    'url' => route('user.vehicles.edit', $vehicle).'#dados',
                    'action' => 'Informar chassi',
                ];
            }

            if ($vehicle->cover_photo_url === null && $vehicle->cover_photo_portrait_url === null) {
                $items[] = [
                    'key' => 'capa-'.$vehicle->id,
                    'tone' => 'info',
                    'icon' => 'photo',
                    'title' => "Adicione uma capa ao {$name}",
                    'description' => 'A foto aparece na ficha do veículo e no PDF do histórico.',
                    'url' => route('user.vehicles.edit', $vehicle).'#capas',
                    'action' => 'Adicionar capa',
                ];
            }
        }

        foreach ($this->expiringWarranties($user, $vehicles) as $warranty) {
            $maintenance = $warranty->maintenance;
            $days = (int) Carbon::today()->diffInDays($warranty->ends_at, false);
            $subject = $warranty->maintenanceItem?->name ?? $maintenance->maintenance_type;

            $items[] = [
                'key' => 'garantia-'.$warranty->id,
                'tone' => 'warning',
                'icon' => 'shield-check',
                'title' => match (true) {
                    $days <= 0 => "Garantia de {$subject} vence hoje",
                    $days === 1 => "Garantia de {$subject} vence amanhã",
                    default => "Garantia de {$subject} vence em {$days} dias",
                },
                'description' => $this->vehicleName($maintenance->vehicle).' · válida até '.$warranty->ends_at->format('d/m/Y').'.',
                'url' => route('user.maintenances.show', $maintenance),
                'action' => 'Ver manutenção',
            ];
        }

        // O mais urgente primeiro: atraso, depois o que vence ou está perto, depois as sugestões.
        $severity = ['danger' => 0, 'warning' => 1, 'info' => 2];

        return collect($items)
            ->sortBy(fn (array $item, int $index): array => [$severity[$item['tone']] ?? 3, $index])
            ->values()
            ->all();
    }

    /**
     * Garantias (do serviço ou de uma peça) dos veículos atuais que vencem nos próximos 30 dias.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return Collection<int, MaintenanceWarranty>
     */
    private function expiringWarranties(User $user, Collection $vehicles): Collection
    {
        if ($vehicles->isEmpty()) {
            return new Collection;
        }

        $today = Carbon::today();

        return MaintenanceWarranty::query()
            ->with(['maintenance.vehicle', 'maintenanceItem'])
            ->whereHas('maintenance', fn (Builder $query) => $query
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('vehicle_id', $vehicles->modelKeys()))
            ->whereDate('ends_at', '>=', $today)
            ->whereDate('ends_at', '<=', $today->copy()->addDays(self::WARRANTY_WINDOW_DAYS))
            ->orderBy('ends_at')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * Primeiros passos do proprietário (primeiro uso): o CRLV-e, a primeira manutenção e o PDF.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array{items: list<array{key: string, title: string, description: string, done: bool, url: string|null, action: string}>, done: int, total: int, complete: bool}
     */
    private function firstSteps(User $user, Collection $vehicles, int $maintenanceCount): array
    {
        $firstVehicle = $vehicles->first();
        $hasVehicle = $firstVehicle !== null;
        $hasMaintenance = $maintenanceCount > 0;
        $hasExportedPdf = VehiclePdfExport::query()->where('user_id', $user->id)->exists();

        $items = [
            [
                'key' => 'veiculo',
                'title' => 'Adicione seu veículo pelo CRLV-e',
                'description' => 'Os dados vêm do documento digital, e o histórico fica ligado ao chassi.',
                'done' => $hasVehicle,
                'url' => route('user.vehicles.create'),
                'action' => 'Adicionar veículo',
            ],
            [
                'key' => 'manutencao',
                'title' => 'Registre a primeira manutenção',
                'description' => 'Com data, quilometragem e a nota fiscal, se tiver.',
                'done' => $hasMaintenance,
                'url' => $hasVehicle ? route('user.maintenances.create', ['vehicle_id' => $firstVehicle->id]) : null,
                'action' => 'Registrar manutenção',
            ],
            [
                'key' => 'pdf',
                'title' => 'Exporte o PDF do histórico',
                'description' => 'Um arquivo para mostrar na venda ou na oficina, com a procedência de cada registro.',
                'done' => $hasExportedPdf,
                'url' => $hasVehicle ? route('user.vehicles.show', $firstVehicle) : null,
                'action' => 'Abrir o veículo',
            ],
        ];

        $done = count(array_filter($items, fn (array $item): bool => $item['done']));

        return [
            'items' => $items,
            'done' => $done,
            'total' => count($items),
            'complete' => $done === count($items),
        ];
    }

    private function vehicleName(?Vehicle $vehicle): string
    {
        return $vehicle === null ? 'veículo' : trim($vehicle->brand.' '.$vehicle->model);
    }

    private function km(int $kilometers): string
    {
        return number_format($kilometers, 0, ',', '.').' km';
    }
}
