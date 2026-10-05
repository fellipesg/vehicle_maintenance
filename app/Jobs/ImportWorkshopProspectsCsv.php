<?php

namespace App\Jobs;

use App\Mail\WorkshopProspectImportFinishedMail;
use App\Models\User;
use App\Services\Outreach\WorkshopProspectImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Importa na fila o CSV de oficinas enviado em /admin/prospeccao e avisa por e-mail quem enviou.
 * O conteúdo vai no próprio job: o worker não depende do disco do servidor web.
 */
class ImportWorkshopProspectsCsv implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /** Reimportar é idempotente, mas uma falha deve virar e-mail, não outra rodada silenciosa. */
    public int $tries = 1;

    public function __construct(
        public string $contents,
        public string $filename,
        public int $requestedById,
    ) {
        $this->onConnection('database');
    }

    public function handle(WorkshopProspectImporter $importer): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }

        try {
            $counts = $importer->importCsv($this->contents);
        } catch (InvalidArgumentException $exception) {
            $this->notify(error: $exception->getMessage());

            return;
        }

        $this->notify(counts: $counts);
    }

    public function failed(?Throwable $exception): void
    {
        $this->notify(error: 'Erro inesperado na importação. Os detalhes estão no log do servidor.');
    }

    /**
     * @param  array{created: int, duplicates: int, suppressed: int, customers: int, invalid: int}|null  $counts
     */
    private function notify(?array $counts = null, ?string $error = null): void
    {
        $email = User::query()->whereKey($this->requestedById)->value('email');

        if (blank($email)) {
            return;
        }

        Mail::to($email)->send(new WorkshopProspectImportFinishedMail($this->filename, $counts, $error));
    }
}
