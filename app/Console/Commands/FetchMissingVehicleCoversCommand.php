<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverImageResolver;
use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchMissingVehicleCoversCommand extends Command
{
    protected $signature = 'vehicles:fetch-missing-covers
                            {--force : Replace covers even when already set}
                            {--plate=* : Atualizar apenas estas placas}';

    protected $description = 'Baixa fotos de capa (Wikimedia Commons) para veículos sem imagem';

    public function handle(VehicleCoverImageResolver $resolver): int
    {
        $query = Vehicle::query()->orderBy('id');

        $plates = collect($this->option('plate'))
            ->map(fn (string $plate) => strtoupper(trim($plate)))
            ->filter()
            ->values();

        if ($plates->isNotEmpty()) {
            $query->whereIn('license_plate', $plates->all());
        } elseif (! $this->option('force')) {
            $query->where(function ($builder): void {
                $builder->whereNull('cover_photo_path')
                    ->orWhere('cover_photo_path', '');
            });
        }

        $vehicles = $query->get();

        if ($vehicles->isEmpty()) {
            $this->info('Nenhum veículo pendente de capa.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($vehicles as $vehicle) {
            $this->line("Buscando capa para #{$vehicle->id} {$vehicle->brand} {$vehicle->model} ({$vehicle->license_plate})...");

            $downloadUrl = $resolver->resolveDownloadUrl($vehicle);

            if ($downloadUrl === null) {
                $this->warn('  Nenhuma imagem encontrada.');

                continue;
            }

            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'VehicleMaintenanceBot/1.0 (cover seeding)'])
                ->get($downloadUrl);

            if (! $response->successful()) {
                $this->warn("  Falha ao baixar: {$downloadUrl}");

                continue;
            }

            $extension = $this->guessExtension($downloadUrl, $response->header('Content-Type'));
            $storagePath = 'vehicle-covers/'.$vehicle->id.'_'.time().'.'.$extension;

            if ($vehicle->cover_photo_path && AppStorage::coversDisk()->exists($vehicle->cover_photo_path)) {
                AppStorage::coversDisk()->delete($vehicle->cover_photo_path);
            }

            AppStorage::coversDisk()->put($storagePath, $response->body());
            $vehicle->update(['cover_photo_path' => $storagePath]);

            $updated++;
            $this->info('  Capa salva: '.AppStorage::coversUrl($storagePath));
        }

        $this->info("Concluído. {$updated} veículo(s) atualizado(s).");

        return self::SUCCESS;
    }

    private function guessExtension(string $url, ?string $contentType): string
    {
        $pathExtension = Str::lower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($pathExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $pathExtension === 'jpeg' ? 'jpg' : $pathExtension;
        }

        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
