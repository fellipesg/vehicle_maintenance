<?php

namespace App\Jobs;

use App\Models\MaintenancePhoto;
use App\Services\Maintenance\MaintenancePhotoVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Gera thumb (320px) e display (1280px) de uma foto de manutenção a partir do master. Despachado
 * depois do commit (a linha precisa existir para o worker) pelo MaintenancePhotoService e pelo
 * photos:generate-variants. Idempotente: foto já processada não faz nada.
 */
class ProcessMaintenancePhoto implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public MaintenancePhoto $photo)
    {
        $this->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(MaintenancePhotoVariantGenerator $variants): void
    {
        try {
            $variants->generate($this->photo);
        } catch (RuntimeException $exception) {
            // Arquivo que não é imagem: tentar de novo não muda nada.
            $this->fail($exception);
        }
    }
}
