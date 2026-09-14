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
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function syncMaintenanceItems(Maintenance $maintenance, Request $request): void
    {
        $items = $request->input('items', []);

        if (! is_array($items)) {
            return;
        }

        $maintenance->items()->delete();

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
            $totalPrice = isset($item['total_price'])
                ? (float) $item['total_price']
                : ($unitPrice !== null ? $unitPrice * $quantity : null);

            MaintenanceItem::create([
                'maintenance_id' => $maintenance->id,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'part_number' => $item['part_number'] ?? null,
                'has_warranty' => false,
                'warranty_starts_at' => null,
                'warranty_ends_at' => null,
            ]);
        }
    }
}
