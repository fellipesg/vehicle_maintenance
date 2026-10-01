<?php

namespace App\Services\Maintenance;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaintenanceWarrantyApplicator
{
    /**
     * Apply the warranty templates chosen on the OS form.
     *
     * An issued warranty is kept as is when the request keeps its template (or
     * leaves the field out), even if that template was deactivated later, so a
     * trivial edit never drops a warranty already promised to the customer.
     * New links only accept active templates of the workshop.
     */
    public function sync(Maintenance $maintenance, Request $request, ?Workshop $workshop): void
    {
        if ($workshop === null) {
            $maintenance->warranties()->delete();

            return;
        }

        $this->syncGeneralWarranty($maintenance, $request, $workshop);
        $this->syncItemWarranties($maintenance, $request, $workshop);
    }

    public function recomputeDates(Maintenance $maintenance): void
    {
        $maintenance->load('warranties');

        foreach ($maintenance->warranties as $warranty) {
            $warranty->recomputeDatesFromMaintenance();
            $warranty->save();
        }
    }

    private function syncGeneralWarranty(Maintenance $maintenance, Request $request, Workshop $workshop): void
    {
        if (! $request->has('general_warranty_template_id')) {
            return;
        }

        $issued = $maintenance->warranties()->where('scope', WarrantyScope::Order)->first();
        $requestedTemplateId = $this->requestedTemplateId($request->input('general_warranty_template_id'));

        if ($this->keepsIssuedWarranty($issued, $requestedTemplateId)) {
            return;
        }

        $issued?->delete();

        if ($requestedTemplateId === null) {
            return;
        }

        $template = $this->resolveTemplate($workshop, $requestedTemplateId, WarrantyScope::Order);
        if ($template === null) {
            return;
        }

        $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance);
        $warranty->save();
    }

    private function syncItemWarranties(Maintenance $maintenance, Request $request, Workshop $workshop): void
    {
        $requestItems = $this->normalizedRequestItems($request);
        $items = $maintenance->items()->with('warranty')->orderBy('id')->get();

        if ($items->count() !== count($requestItems)) {
            throw ValidationException::withMessages([
                'items' => 'Não foi possível vincular garantias aos itens. Salve novamente a ordem de serviço.',
            ]);
        }

        $maintenance->warranties()
            ->where('scope', WarrantyScope::Item)
            ->where(function ($query) use ($items) {
                $query->whereNull('maintenance_item_id')
                    ->orWhereNotIn('maintenance_item_id', $items->modelKeys());
            })
            ->delete();

        foreach ($this->pairRowsWithItems($requestItems, $items) as [$row, $item]) {
            if (! array_key_exists('warranty_template_id', $row)) {
                continue;
            }

            $issued = $item->warranty;
            $requestedTemplateId = $this->requestedTemplateId($row['warranty_template_id']);

            if ($this->keepsIssuedWarranty($issued, $requestedTemplateId)) {
                continue;
            }

            $issued?->delete();

            if ($requestedTemplateId === null) {
                continue;
            }

            $template = $this->resolveTemplate($workshop, $requestedTemplateId, WarrantyScope::Item);
            if ($template === null) {
                continue;
            }

            $warranty = MaintenanceWarranty::snapshotFromTemplate($template, $maintenance, $item);
            $warranty->save();
        }
    }

    /**
     * Match each submitted row with its item: rows that carry the id of an item
     * of this OS get that item; the other rows take the remaining items in
     * creation order, which is the order the rows were saved in.
     *
     * @param  list<array<string, mixed>>  $requestItems
     * @param  Collection<int, MaintenanceItem>  $items
     * @return list<array{0: array<string, mixed>, 1: MaintenanceItem}>
     */
    private function pairRowsWithItems(array $requestItems, Collection $items): array
    {
        $itemsById = $items->keyBy('id');
        $claimedIds = [];

        foreach ($requestItems as $index => $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : null;

            if ($itemId !== null && $itemsById->has($itemId) && ! in_array($itemId, $claimedIds, true)) {
                $claimedIds[$index] = $itemId;
            }
        }

        $unclaimedItems = $items
            ->reject(fn (MaintenanceItem $item) => in_array($item->id, $claimedIds, true))
            ->values()
            ->all();

        $pairs = [];

        foreach ($requestItems as $index => $row) {
            $item = isset($claimedIds[$index])
                ? $itemsById->get($claimedIds[$index])
                : array_shift($unclaimedItems);

            if ($item !== null) {
                $pairs[] = [$row, $item];
            }
        }

        return $pairs;
    }

    private function keepsIssuedWarranty(?MaintenanceWarranty $issued, ?int $requestedTemplateId): bool
    {
        if ($issued === null) {
            return false;
        }

        $issuedTemplateId = $issued->warranty_template_id === null ? null : (int) $issued->warranty_template_id;

        return $issuedTemplateId === $requestedTemplateId;
    }

    private function requestedTemplateId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedRequestItems(Request $request): array
    {
        $items = $request->input('items', []);
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item) && ! empty($item['name']);
        }));
    }

    private function resolveTemplate(Workshop $workshop, int $templateId, WarrantyScope $scope): ?WarrantyTemplate
    {
        return WarrantyTemplate::query()
            ->where('workshop_id', $workshop->id)
            ->where('scope', $scope)
            ->where('is_active', true)
            ->find($templateId);
    }
}
