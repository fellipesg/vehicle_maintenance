<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\AppStorage;
use App\Support\DemoWarrantyPdfValidator;
use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Database\Seeders\DemoWorkshopAccountsSeeder;
use Database\Seeders\DemoWorkshopLogosSeeder;
use Database\Seeders\DevPortalUsersSeeder;
use Database\Seeders\FelipeVehicleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportDemoWarrantyPdfs extends Command
{
    protected $signature = 'demo:export-warranty-pdfs
                            {--seed : Run demo seeders before exporting}
                            {--plate='.DemoMaintenanceWarrantiesSeeder::PLATE.' : License plate to export}';

    protected $description = 'Generate maintenance history PDFs on disk with demo workshop logos and warranties';

    public function handle(VehicleMaintenancePdfExporter $exporter): int
    {
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        if (! app()->environment('local', 'testing')) {
            $this->error('demo:export-warranty-pdfs only runs in local or testing environments.');

            return self::FAILURE;
        }

        if ($this->option('seed')) {
            $this->info('Running demo seeders…');
            $this->call('db:seed', ['--class' => DevPortalUsersSeeder::class]);
            $this->call('db:seed', ['--class' => DemoWorkshopAccountsSeeder::class]);
            $this->call('db:seed', ['--class' => FelipeVehicleSeeder::class]);
            $this->call('db:seed', ['--class' => DemoWorkshopLogosSeeder::class]);
            $this->call('db:seed', ['--class' => DemoMaintenanceWarrantiesSeeder::class]);
        }

        $plate = strtoupper((string) $this->option('plate'));
        $vehicle = Vehicle::query()->where('license_plate', $plate)->first();

        if ($vehicle === null) {
            $this->error("Vehicle with plate {$plate} not found.");

            return self::FAILURE;
        }

        $exportDir = storage_path('app/demo-exports');
        File::ensureDirectoryExists($exportDir);

        $filename = 'historico_'.$plate.'_garantias.pdf';
        $absolutePath = $exportDir.DIRECTORY_SEPARATOR.$filename;

        $file = $exporter->generate($vehicle->fresh());

        try {
            file_put_contents($absolutePath, $file['content']);

            $errors = DemoWarrantyPdfValidator::validationErrors($file['content']);
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $this->printSummary($vehicle, $absolutePath, $file['content']);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }

        return self::SUCCESS;
    }

    private function printSummary(Vehicle $vehicle, string $absolutePath, string $pdfContent): void
    {
        $vehicle->load([
            'maintenances.workshop',
            'maintenances.generalWarranty',
            'maintenances.items.warranty',
        ]);

        $this->newLine();
        $this->info('PDF exportado:');
        $this->line('  '.$absolutePath);

        $this->newLine();
        $this->info('Oficinas com logo_path:');
        $loggedWorkshops = [];

        foreach ($vehicle->maintenances as $maintenance) {
            $workshop = $maintenance->workshop;
            if ($workshop === null || $workshop->logo_path === null || $workshop->logo_path === '') {
                continue;
            }

            if (isset($loggedWorkshops[$workshop->id])) {
                continue;
            }

            $loggedWorkshops[$workshop->id] = true;
            $this->line("  {$workshop->name}: {$workshop->logo_path}");
        }

        $this->newLine();
        $this->info('Logos montados no exporter ($workshopLogos):');

        foreach ($vehicle->maintenances as $maintenance) {
            if ($maintenance->workshop_id === null) {
                continue;
            }

            $logoPath = $maintenance->workshop?->logo_path;
            $embedded = is_string($logoPath) && $logoPath !== '' && AppStorage::coversDisk()->exists($logoPath);
            $this->line(sprintf(
                '  OS #%d (%s): %s',
                $maintenance->id,
                $maintenance->maintenance_date->format('d/m/Y'),
                $embedded ? 'logo embutido de '.$logoPath : 'sem logo',
            ));
        }

        $this->newLine();
        $this->info('Garantias incluídas no PDF:');

        foreach ($vehicle->maintenances as $maintenance) {
            if ($maintenance->generalWarranty) {
                $this->line(sprintf(
                    '  OS #%d geral: %s (até %s)',
                    $maintenance->id,
                    $maintenance->generalWarranty->name,
                    $maintenance->generalWarranty->ends_at->format('d/m/Y'),
                ));
            }

            foreach ($maintenance->items as $item) {
                if ($item->warranty) {
                    $this->line(sprintf(
                        '  OS #%d item "%s": %s (até %s)',
                        $maintenance->id,
                        $item->name,
                        $item->warranty->name,
                        $item->warranty->ends_at->format('d/m/Y'),
                    ));
                }
            }
        }

        $text = DemoWarrantyPdfValidator::extractText($pdfContent);
        $this->newLine();
        $this->info('Âncoras encontradas no texto parseado:');
        $this->line('  '.DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA.': '.(
            str_contains($text, DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA) ? 'sim' : 'não'
        ));
        $this->line('  '.DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA.': '.(
            str_contains($text, DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA) ? 'sim' : 'não'
        ));
    }
}
