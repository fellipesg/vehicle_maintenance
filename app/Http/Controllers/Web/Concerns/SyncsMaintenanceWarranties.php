<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Maintenance;
use App\Models\Workshop;
use App\Services\Maintenance\MaintenanceWarrantyApplicator;
use Illuminate\Http\Request;

trait SyncsMaintenanceWarranties
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function maintenanceWarrantyValidationRules(): array
    {
        return [
            'general_warranty_template_id' => ['nullable', 'integer', 'exists:warranty_templates,id'],
            'items.*.warranty_template_id' => ['nullable', 'integer', 'exists:warranty_templates,id'],
        ];
    }

    protected function syncMaintenanceWarranties(Maintenance $maintenance, Request $request, ?Workshop $workshop): void
    {
        app(MaintenanceWarrantyApplicator::class)->sync($maintenance, $request, $workshop);
    }

    protected function recomputeMaintenanceWarrantyDates(Maintenance $maintenance): void
    {
        app(MaintenanceWarrantyApplicator::class)->recomputeDates($maintenance);
    }
}
