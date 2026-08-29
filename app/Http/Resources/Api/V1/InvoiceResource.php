<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'maintenance_item_id' => $this->maintenance_item_id,
            'invoice_type' => $this->invoice_type,
            'file_name' => $this->file_name,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'download_url' => $this->when(
                $request->user() !== null,
                fn () => url('/api/v1/invoices/'.$this->id.'/download'),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
