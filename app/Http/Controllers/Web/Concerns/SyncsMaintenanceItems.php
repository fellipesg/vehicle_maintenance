<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use Illuminate\Http\Request;

trait SyncsMaintenanceItems
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function maintenanceItemValidationRules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Make the OS items match the submitted list. Rows that carry the id of an
     * item of this OS update it in place, so its issued warranty and invoice
     * links survive; rows without a known id become new items, created in the
     * submitted order; items missing from the list are deleted. An empty or
     * absent list removes every item, which lets the NF-e import fill it.
     */
    protected function syncMaintenanceItems(Maintenance $maintenance, Request $request): void
    {
        $rows = $request->input('items', []);

        if (! is_array($rows)) {
            return;
        }

        $existingItems = $maintenance->items()->get()->keyBy('id');
        $keptItemIds = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }

            $attributes = $this->maintenanceItemAttributes($row);
            $existingItem = isset($row['id']) ? $existingItems->get((int) $row['id']) : null;

            if ($existingItem !== null && ! in_array($existingItem->id, $keptItemIds, true)) {
                $existingItem->update($attributes);
                $keptItemIds[] = $existingItem->id;

                continue;
            }

            $keptItemIds[] = MaintenanceItem::create([
                'maintenance_id' => $maintenance->id,
                ...$attributes,
            ])->id;
        }

        $maintenance->items()->whereNotIn('id', $keptItemIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, description: ?string, quantity: int, unit_price: ?float, total_price: ?float, part_number: ?string, has_warranty: bool, warranty_starts_at: null, warranty_ends_at: null}
     */
    private function maintenanceItemAttributes(array $row): array
    {
        $quantity = (int) ($row['quantity'] ?? 1);
        $unitPrice = isset($row['unit_price']) ? (float) $row['unit_price'] : null;
        $totalPrice = isset($row['total_price'])
            ? (float) $row['total_price']
            : ($unitPrice !== null ? $unitPrice * $quantity : null);

        return [
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'part_number' => $row['part_number'] ?? null,
            'has_warranty' => false,
            'warranty_starts_at' => null,
            'warranty_ends_at' => null,
        ];
    }
}
