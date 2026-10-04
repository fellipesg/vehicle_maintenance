<?php

namespace App\Observers;

use App\Enums\WorkshopProspectStatus;
use App\Models\Workshop;
use App\Models\WorkshopProspect;

class WorkshopObserver
{
    /**
     * Oficina criada com o e-mail de uma prospectada: a prospecção converteu.
     */
    public function created(Workshop $workshop): void
    {
        if (blank($workshop->email)) {
            return;
        }

        WorkshopProspect::query()
            ->where('email', mb_strtolower(trim($workshop->email)))
            ->where('status', '!=', WorkshopProspectStatus::Converted)
            ->get()
            ->each(fn (WorkshopProspect $prospect) => $prospect->markConverted($workshop));
    }
}
