<?php

namespace App\Services\Vehicle;

use App\Http\Resources\Api\V1\MaintenancePhotoResource;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Maintenance\MaintenancePhotoAccess;
use App\Support\Maintenance\MaintenancePhotoPreview;
use App\Support\Maintenance\MaintenanceRedactor;
use Illuminate\Support\Collection;

class VehicleTimelineBuilder
{
    public function __construct(
        private readonly VehicleMaintenanceReminderService $reminders,
        private readonly VehicleMileageStatsService $mileageStats,
    ) {}

    /**
     * @return array{
     *     vehicle: array<string, mixed>,
     *     registration: array<string, mixed>|null,
     *     events: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     reminder: array<string, mixed>
     * }
     */
    public function build(Vehicle $vehicle, ?User $viewer = null): array
    {
        // loadMissing, não load: quem já pré-carregou um histórico limitado (Vehicle::restrictHistoryTo,
        // para o lojista em consignação) não pode ter essa restrição desfeita aqui.
        $vehicle->loadMissing([
            'maintenances.items.warranty',
            'maintenances.generalWarranty',
            'maintenances.workshop',
            'maintenances.invoices',
        ]);

        // OS de oficina sem proprietário: forma mínima para quem não é a oficina autora, e as
        // ocultadas pelo proprietário saem (MaintenanceRedactor).
        $viewer ??= auth()->user();
        $maintenances = MaintenanceRedactor::redactAll($vehicle->maintenances, $viewer);
        $maintenances->each(fn ($maintenance) => $maintenance->setRelation('vehicle', $vehicle));
        $photosByMaintenance = MaintenancePhotoAccess::allows($viewer) ? $this->photosByMaintenance($maintenances) : new Collection;

        $events = [];
        $currentKm = (int) ($vehicle->current_kilometers ?? 0);

        $registration = $this->resolveRegistrationAnchor($vehicle, $maintenances);

        if ($registration !== null) {
            $hasMaintenanceAtSameKm = $maintenances->contains(
                fn ($maintenance) => $maintenance->kilometers !== null
                    && (int) $maintenance->kilometers === $registration['kilometers'],
            );

            if (! $hasMaintenanceAtSameKm) {
                $events[] = [
                    'type' => 'registration',
                    'id' => 'registration',
                    'label' => 'Cadastro do veículo',
                    'date' => $registration['date'],
                    'kilometers' => $registration['kilometers'],
                    'total_amount' => 0.0,
                    'items' => [],
                    'is_current' => $currentKm === $registration['kilometers'],
                ];
            }
        }

        foreach ($maintenances as $maintenance) {
            if ($maintenance->kilometers === null) {
                continue;
            }

            $items = $maintenance->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                'total_price' => $item->total_price !== null ? (float) $item->total_price : null,
                'has_warranty' => $item->warranty !== null,
                'warranty_name' => $item->warranty?->name,
                'warranty_starts_at' => $item->warranty?->starts_at?->toDateString(),
                'warranty_ends_at' => $item->warranty?->ends_at?->toDateString(),
                'warranty_period_label' => $item->warrantyPeriodLabel(),
                'is_under_warranty' => $item->isUnderWarranty(),
            ])->values()->all();

            $generalWarranty = $maintenance->generalWarranty;

            $events[] = [
                'type' => 'maintenance',
                'id' => $maintenance->id,
                'label' => $maintenance->maintenance_type,
                'description' => $maintenance->description,
                'workshop_name' => $maintenance->displayWorkshopName(),
                'workshop_logo_url' => $maintenance->workshop?->logoUrl(),
                'general_warranty' => $generalWarranty !== null ? [
                    'name' => $generalWarranty->name,
                    'ends_at' => $generalWarranty->ends_at?->toDateString(),
                    'is_vigente' => $generalWarranty->isVigente(),
                    'label' => $generalWarranty->label(),
                ] : null,
                'service_category' => $maintenance->service_category,
                'date' => $maintenance->maintenance_date->toDateString(),
                'kilometers' => (int) $maintenance->kilometers,
                'total_amount' => round(collect($items)->sum(fn (array $item) => $item['total_price'] ?? 0), 2),
                'items_count' => count($items),
                'has_invoice' => $maintenance->invoices->isNotEmpty(),
                'items' => $items,
                'is_current' => false,
                'is_verified' => $maintenance->isVerified(),
                'registered_by_type' => $maintenance->registered_by_type,
                'provenance_label' => $maintenance->provenance_label,
                ...$this->photoSummary($maintenance, $photosByMaintenance->get($maintenance->id, new Collection), $viewer),
            ];
        }

        usort($events, function (array $a, array $b): int {
            $kmComparison = ((int) ($a['kilometers'] ?? 0)) <=> ((int) ($b['kilometers'] ?? 0));

            if ($kmComparison !== 0) {
                return $kmComparison;
            }

            return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
        });

        $this->markCurrentEvent($events, $currentKm);

        $upcoming = $this->reminders->upcomingTimelineEvent($vehicle);
        if ($upcoming !== null) {
            $events[] = $upcoming;
        }

        $maintenanceEvents = array_values(array_filter(
            $events,
            fn (array $event) => $event['type'] === 'maintenance',
        ));

        $reminder = $this->reminders->summarize($vehicle);
        $usage = $this->mileageStats->approximateAnnualKilometers($vehicle);
        $trackProgress = $this->resolveTrackProgress($events, $currentKm);

        return [
            'vehicle' => [
                'id' => $vehicle->id,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'license_plate' => $vehicle->license_plate,
                'year' => $vehicle->year,
                'cover_photo_url' => $vehicle->cover_photo_url,
                'current_kilometers' => $vehicle->current_kilometers,
            ],
            'registration' => $vehicle->odometer_at_registration !== null ? [
                'kilometers' => $vehicle->odometer_at_registration,
                'date' => $vehicle->created_at?->toDateString(),
            ] : null,
            'events' => $events,
            'reminder' => $reminder,
            'summary' => [
                'maintenance_count' => count($maintenanceEvents),
                'total_spent' => round(collect($maintenanceEvents)->sum('total_amount'), 2),
                'first_kilometers' => collect($events)->pluck('kilometers')->filter()->min(),
                'last_kilometers' => $vehicle->current_kilometers,
                'next_due_kilometers' => $reminder['next_due_kilometers'],
                'kilometers_remaining' => $reminder['kilometers_remaining'],
                'progress_percent' => $reminder['progress_percent'],
                'odometer_progress_percent' => $this->resolveOdometerProgressPercent($currentKm, $reminder),
                'track_progress_percent' => $trackProgress['percent'],
                'track_current_index' => $trackProgress['current_index'],
                'is_overdue' => $reminder['is_overdue'],
                'approximate_annual_kilometers' => $usage['approximate_annual_kilometers'] ?? null,
                'usage_is_approximate' => $usage !== null,
                'usage_period_start_date' => $usage['period_start_date'] ?? null,
                'usage_period_end_date' => $usage['period_end_date'] ?? null,
            ],
        ];
    }

    /**
     * Fotos de todas as OS da linha do tempo numa query só (no máximo 24 por OS), na ordem do grupo.
     *
     * @param  Collection<int, Maintenance>  $maintenances
     * @return Collection<int, Collection<int, MaintenancePhoto>>
     */
    private function photosByMaintenance(Collection $maintenances): Collection
    {
        if ($maintenances->isEmpty()) {
            return new Collection;
        }

        return MaintenancePhoto::query()
            ->whereIn('maintenance_id', $maintenances->pluck('id'))
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('maintenance_id');
    }

    /**
     * photos_count, photos_locked e photo_preview de uma OS. Bloqueada (anexos de OS sem dono que o
     * leitor não pode ver): conta o total para o app mostrar o cadeado, sem prévia. Liberada: conta e
     * mostra só o que o leitor enxerga (fora do tenant e da oficina autora, veículo + depois).
     *
     * @param  Collection<int, MaintenancePhoto>  $photos
     * @return array{photos_count: int, photos_locked: bool, photo_preview: list<array<string, mixed>>}
     */
    private function photoSummary(Maintenance $maintenance, Collection $photos, ?User $viewer): array
    {
        // Sem as fotos das manutenções ligadas na conta: nada de contagem, cadeado nem prévia.
        if ($photos->isEmpty() || ! MaintenancePhotoAccess::allows($viewer)) {
            return ['photos_count' => 0, 'photos_locked' => false, 'photo_preview' => []];
        }

        if ($maintenance->hidesAttachmentsFrom($viewer)) {
            return ['photos_count' => $photos->count(), 'photos_locked' => true, 'photo_preview' => []];
        }

        $visible = $maintenance->exposesAllPhotosTo($viewer)
            ? $photos
            : $photos->filter(fn (MaintenancePhoto $photo): bool => $photo->isPublicVisible())->values();

        return [
            'photos_count' => $visible->count(),
            'photos_locked' => false,
            'photo_preview' => MaintenancePhotoPreview::pick($visible)
                ->map(fn (MaintenancePhoto $photo): array => (new MaintenancePhotoResource($photo->setRelation('maintenance', $maintenance)))
                    ->forViewer($viewer, authorized: true)
                    ->resolve(request()))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{kilometers: int, date: string|null}|null
     */
    private function resolveRegistrationAnchor(Vehicle $vehicle, \Illuminate\Support\Collection $maintenances): ?array
    {
        if ($vehicle->odometer_at_registration !== null) {
            return [
                'kilometers' => (int) $vehicle->odometer_at_registration,
                'date' => $vehicle->created_at?->toDateString(),
            ];
        }

        $firstMaintenance = $maintenances
            ->filter(fn ($maintenance) => $maintenance->kilometers !== null)
            ->sortBy('kilometers')
            ->first();

        if ($firstMaintenance !== null) {
            return [
                'kilometers' => (int) $firstMaintenance->kilometers,
                'date' => $firstMaintenance->maintenance_date->toDateString(),
            ];
        }

        if ($vehicle->current_kilometers !== null) {
            return [
                'kilometers' => (int) $vehicle->current_kilometers,
                'date' => $vehicle->created_at?->toDateString(),
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function markCurrentEvent(array &$events, int $currentKm): void
    {
        $bestIndex = null;
        $bestKm = -1;
        $bestDate = '';

        foreach ($events as $index => $event) {
            if (($event['type'] ?? '') === 'upcoming') {
                continue;
            }

            $km = (int) ($event['kilometers'] ?? -1);
            $date = (string) ($event['date'] ?? '');

            if ($km > $currentKm) {
                continue;
            }

            if ($km > $bestKm || ($km === $bestKm && $date >= $bestDate)) {
                $bestKm = $km;
                $bestDate = $date;
                $bestIndex = $index;
            }
        }

        if ($bestIndex !== null) {
            $events[$bestIndex]['is_current'] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{percent: float, current_index: int}
     */
    private function resolveTrackProgress(array $events, int $currentKm): array
    {
        $eventCount = count($events);

        if ($eventCount <= 1) {
            return ['percent' => 0.0, 'current_index' => 0];
        }

        $currentIndex = null;
        $lastMaintenanceIndex = 0;
        $lastMaintenanceKm = 0;
        $upcomingIndex = null;
        $upcomingKm = null;

        foreach ($events as $index => $event) {
            if (($event['type'] ?? '') === 'upcoming') {
                $upcomingIndex = $index;
                $upcomingKm = (int) ($event['kilometers'] ?? 0);

                continue;
            }

            $lastMaintenanceIndex = $index;
            $lastMaintenanceKm = (int) ($event['kilometers'] ?? 0);

            if (($event['is_current'] ?? false) && ($event['type'] ?? '') !== 'upcoming') {
                $currentIndex = $index;
            }
        }

        $currentIndex ??= $lastMaintenanceIndex;
        $progressIndex = (float) $currentIndex;

        if (
            $upcomingIndex !== null
            && $upcomingKm !== null
            && $currentKm > $lastMaintenanceKm
            && $upcomingKm > $lastMaintenanceKm
            && $upcomingIndex > $lastMaintenanceIndex
        ) {
            $kmFraction = ($currentKm - $lastMaintenanceKm) / ($upcomingKm - $lastMaintenanceKm);
            $kmFraction = min(1.0, max(0.0, $kmFraction));
            $progressIndex += $kmFraction * ($upcomingIndex - $lastMaintenanceIndex);
        }

        return [
            'percent' => round(min(100, max(0, ($progressIndex / ($eventCount - 1)) * 100)), 1),
            'current_index' => $currentIndex,
        ];
    }

    /**
     * @param  array<string, mixed>  $reminder
     */
    private function resolveOdometerProgressPercent(int $currentKm, array $reminder): ?float
    {
        $nextDue = $reminder['next_due_kilometers'] ?? null;

        if ($nextDue === null || (int) $nextDue <= 0) {
            return null;
        }

        if (($reminder['is_overdue'] ?? false) === true) {
            return 100.0;
        }

        return round(min(100, max(0, ($currentKm / (int) $nextDue) * 100)), 1);
    }
}
