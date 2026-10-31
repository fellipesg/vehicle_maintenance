<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workshop;

class WorkshopPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * A oficina é da conta: só o mesmo tenant lê o cadastro dela. Sem a condição, qualquer conta
     * autenticada com a ability workshops:read lia nome, prazo e termo de garantia de qualquer
     * oficina pelos dois únicos consumidores deste método (WarrantyTemplateController::index e
     * ::show). O diretório de oficinas (GET /api/v1/workshops e /usuario/oficinas) não passa por
     * policy e continua aberto.
     */
    public function view(User $user, Workshop $workshop): bool
    {
        return $workshop->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isWorkshop() && $user->tenant_id !== null;
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $workshop->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $workshop->tenant_id === $user->tenant_id;
    }
}
