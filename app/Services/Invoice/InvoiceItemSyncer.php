<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;

class InvoiceItemSyncer
{
    public function applyToMaintenance(Maintenance $maintenance, ParsedInvoice $parsed, Invoice $invoice): int
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
            return 0;
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

        return $created;
    }
}
