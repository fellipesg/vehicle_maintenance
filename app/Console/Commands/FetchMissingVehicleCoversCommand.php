<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverCropper;
use App\Services\Vehicle\VehicleCoverImageResolver;
use App\Services\Vehicle\VehicleCoverService;
use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchMissingVehicleCoversCommand extends Command
{
    protected $signature = 'vehicles:fetch-missing-covers
                            {--missing-orientation : Preenche apenas a orientação que falta}
                            {--force : Replace covers even when already set}
                            {--plate=* : Atualizar apenas estas placas}';

    protected $description = 'Baixa fotos de capa (Wikimedia Commons) para veículos sem imagem';

    public function handle(
        VehicleCoverImageResolver $resolver,
        VehicleCoverCropper $cropper,
        VehicleCoverService $covers,
    ): int {
        $query = Vehicle::query()->orderBy('id');

        $plates = collect($this->option('plate'))
            ->map(fn (string $plate) => strtoupper(trim($plate)))
            ->filter()
            ->values();

        if ($plates->isNotEmpty()) {
            $query->whereIn('license_plate', $plates->all());
        }

        if (! $this->option('force')) {
            if ($this->option('missing-orientation')) {
                $query->where(function ($builder): void {
                    $builder->where(function ($missing): void {
                        $missing->whereNull('cover_photo_path')
                            ->orWhere('cover_photo_path', '');
                    })->orWhere(function ($missing): void {
                        $missing->whereNull('cover_photo_portrait_path')
                            ->orWhere('cover_photo_portrait_path', '');
                    });
                });
            } else {
                $query->where(function ($builder): void {
                    $builder->whereNull('cover_photo_path')
                        ->orWhere('cover_photo_path', '');
                });
            }
        }

        $vehicles = $query->get();

        if ($vehicles->isEmpty()) {
            $this->info('Nenhum veículo pendente de capa.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($vehicles as $vehicle) {
            $needsLandscape = $this->option('force') || ! $vehicle->hasLandscapeCover();
            $needsPortrait = $this->option('force') || ! $vehicle->hasPortraitCover();

            if ($this->option('missing-orientation') && ! $this->option('force')) {
                if ($vehicle->hasLandscapeCover() && ! $vehicle->hasPortraitCover()) {
                    $needsLandscape = false;
                } elseif ($vehicle->hasPortraitCover() && ! $vehicle->hasLandscapeCover()) {
                    $needsPortrait = false;
                }
            }

            if (! $needsLandscape && ! $needsPortrait) {
                continue;
            }

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
            $sourceBytes = $response->body();

            try {
                if ($needsLandscape) {
                    $landscapeBytes = $cropper->cropToLandscape($sourceBytes);
                    $covers->storeLandscapeBytes($vehicle, $landscapeBytes, $extension);
                    $vehicle->refresh();
                    $this->info('  Paisagem salva: '.AppStorage::coversUrl((string) $vehicle->cover_photo_path));
                }

                if ($needsPortrait) {
                    $portraitBytes = $cropper->cropToPortrait($sourceBytes);
                    $covers->storePortraitBytes($vehicle, $portraitBytes, $extension);
                    $vehicle->refresh();
                    $this->info('  Retrato salva: '.AppStorage::coversUrl((string) $vehicle->cover_photo_portrait_path));
                }
            } catch (\Throwable $exception) {
                $this->warn('  Falha ao processar imagem: '.$exception->getMessage());

                continue;
            }

            $updated++;
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
