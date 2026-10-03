<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;

class InvoiceItemSyncer
{
    public function applyToMaintenance(Maintenance $maintenance, ParsedInvoice $parsed, Invoice $invoice): int
    {
        return $this->sync($maintenance, $parsed, $invoice)['items_created'];
    }

    /**
     * Copy the NF-e data to the invoice and the OS, then import the NF-e items
     * when the OS has none yet. `import_skipped` tells an import that did not
     * run because the OS already had items apart from a file whose items could
     * not be read, so callers only warn about the latter.
     *
     * @return array{items_created: int, items_skipped: int, import_skipped: bool}
     */
    public function sync(Maintenance $maintenance, ParsedInvoice $parsed, Invoice $invoice): array
    {
        $invoice->update(array_filter([
            'invoice_number' => $parsed->invoiceNumber,
            'invoice_date' => $parsed->invoiceDate,
            'total_amount' => $parsed->totalAmount,
        ], fn ($value) => $value !== null));

        if ($parsed->kilometers && ! $maintenance->kilometers) {
            $maintenance->update(['kilometers' => $parsed->kilometers]);
        }

        if ($maintenance->items()->exists()) {
            return [
                'items_created' => 0,
                'items_skipped' => count($parsed->items),
                'import_skipped' => true,
            ];
        }

        $created = 0;

        foreach ($parsed->items as $item) {
            MaintenanceItem::create([
                'maintenance_id' => $maintenance->id,
                'name' => $item->name,
                'quantity' => (int) max(1, round($item->quantity)),
                'unit_price' => $item->unitPrice,
                'total_price' => $item->totalPrice,
                'part_number' => $item->partNumber,
            ]);
            $created++;
        }

        return [
            'items_created' => $created,
            'items_skipped' => 0,
            'import_skipped' => false,
        ];
    }
}
