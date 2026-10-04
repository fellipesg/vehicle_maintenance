<?php

namespace App\Console\Commands;

use App\Services\Outreach\WorkshopProspectImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportWorkshopProspects extends Command
{
    protected $signature = 'outreach:import {csv : CSV gerado por outreach:extract-receita}';

    protected $description = 'Importa oficinas prospectadas de um CSV';

    public function handle(WorkshopProspectImporter $importer): int
    {
        $path = (string) $this->argument('csv');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        try {
            $counts = $importer->importFile($path);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Criadas: {$counts['created']}");
        $this->line("Duplicadas: {$counts['duplicates']}");
        $this->line("Descadastradas/devolvidas (supressão): {$counts['suppressed']}");
        $this->line("Já clientes: {$counts['customers']}");
        $this->line("Inválidas: {$counts['invalid']}");

        return self::SUCCESS;
    }
}
