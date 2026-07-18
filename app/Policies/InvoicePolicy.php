<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canAccessMaintenance($user, $invoice->maintenance);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->canAccessMaintenance($user, $invoice->maintenance);
    }

    private function canAccessMaintenance(User $user, ?\App\Models\Maintenance $maintenance): bool
    {
        if (! $maintenance) {
            return false;
        }

        return app(MaintenancePolicy::class)->view($user, $maintenance);
    }
}
