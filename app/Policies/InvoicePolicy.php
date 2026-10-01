<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;

/**
 * Ver e baixar a nota fiscal segue quem pode ver a manutenção. Alterar ou apagar a nota altera a
 * manutenção, então segue a MaintenancePolicy::update, como o envio (InvoiceController::upload):
 * quem vendeu o carro, o lojista em consignação e o dono diante de uma OS com Selo da oficina não
 * apagam notas.
 */
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

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->canChangeMaintenance($user, $invoice->maintenance);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->canChangeMaintenance($user, $invoice->maintenance);
    }

    private function canAccessMaintenance(User $user, ?Maintenance $maintenance): bool
    {
        if (! $maintenance) {
            return false;
        }

        return app(MaintenancePolicy::class)->view($user, $maintenance);
    }

    private function canChangeMaintenance(User $user, ?Maintenance $maintenance): bool
    {
        if (! $maintenance) {
            return false;
        }

        return app(MaintenancePolicy::class)->update($user, $maintenance)->allowed();
    }
}
