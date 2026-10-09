<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Support\AppStorage;

/**
 * Apaga as notas fiscais e as fotos de uma OS (arquivo e linha). A OS e os itens ficam.
 */
class MaintenanceAttachmentPurger
{
    public function purge(Maintenance $maintenance): int
    {
        $count = 0;

        foreach ($maintenance->photos()->get() as $photo) {
            if (AppStorage::disk()->exists($photo->path)) {
                AppStorage::disk()->delete($photo->path);
            }
            $photo->delete();
            $count++;
        }

        foreach ($maintenance->invoices()->get() as $invoice) {
            if (AppStorage::disk()->exists($invoice->file_path)) {
                AppStorage::disk()->delete($invoice->file_path);
            }
            $invoice->delete();
            $count++;
        }

        return $count;
    }
}
