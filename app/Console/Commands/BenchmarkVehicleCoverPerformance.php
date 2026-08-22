<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BenchmarkVehicleCoverPerformance extends Command
{
    private ?string $remoteApiToken = null;

    protected $signature = 'benchmark:vehicle-covers
        {--label=s3-neon : Etiqueta do storage (ex: s3-neon, r2-cloudflare, prod-current)}
        {--user= : E-mail do usuário para simular API / app}
        {--rounds=3 : Repetições do download HTTP por imagem}
        {--remote : Medir produção via API remota (cliente → servidor)}
        {--base-url= : URL base da produção (ex: https://app.laravel.cloud)}';

    protected $description = 'Mede tempo de carregamento das telas (API + capas) e grava baseline para comparar storages';

    public function handle(): int
    {
        $label = (string) $this->option('label');
        $rounds = max(1, (int) $this->option('rounds'));

        if ((bool) $this->option('remote')) {
            return $this->handleRemote($label, $rounds);
        }
        $vehicles = Vehicle::query()
            ->whereNotNull('cover_photo_path')
            ->where('cover_photo_path', '!=', '')
            ->orderBy('id')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->warn('Nenhum veículo com capa cadastrada.');

            return self::SUCCESS;
        }

        $user = $this->resolveUser();
        $userVehicles = $user !== null
            ? $user->currentVehicles()->orderBy('vehicles.id')->get()
            : collect();

        $rows = [];
        foreach ($vehicles as $vehicle) {
            $rows[] = $this->benchmarkVehicle($vehicle, (string) $vehicle->cover_photo_path, $rounds);
        }

        $httpByVehicleId = collect($rows)->keyBy('vehicle_id');
        $screenLoad = $this->benchmarkScreenLoads($user, $userVehicles, $vehicles, $httpByVehicleId, $rounds);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'label' => $label,
            'storage' => $this->storageMeta(),
            'screen_load' => $screenLoad,
            'vehicles' => $rows,
            'summary' => $this->summarize($rows, $screenLoad),
        ];

        $this->writeReport($label, $report);

        return self::SUCCESS;
    }

    private function handleRemote(string $label, int $rounds): int
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: 'https://vehicle-maintenance-production-l6pnoo.laravel.cloud'), '/');
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('Usuário não encontrado para autenticar na API remota.');

            return self::FAILURE;
        }

        $token = $user->createToken('benchmark-remote-'.now()->timestamp)->plainTextToken;
        $this->remoteApiToken = $token;

        try {
            $listPayload = $this->fetchRemoteMyVehicles($baseUrl, $token, $rounds);
            $allVehicles = $this->fetchRemoteAllVehicles($baseUrl, $token);

            if ($listPayload['vehicles'] === []) {
                $this->warn('Nenhuma capa retornada pela API de produção.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($allVehicles as $vehicle) {
                $rows[] = $this->benchmarkRemoteCover($vehicle, $rounds);
            }

            $httpByVehicleId = collect($rows)->keyBy('vehicle_id');
            $listVehicles = collect($listPayload['vehicles']);
            $screenLoad = $this->benchmarkRemoteScreenLoads(
                $listPayload['median_ms'],
                $listVehicles,
                collect($allVehicles),
                $httpByVehicleId,
                $rounds,
            );

            $storageKind = $this->detectRemoteStorageKind($allVehicles);

            $report = [
                'generated_at' => now()->toIso8601String(),
                'label' => $label,
                'mode' => 'remote',
                'base_url' => $baseUrl,
                'storage' => [
                    'covers_disk' => $storageKind,
                    'driver' => 'remote-api',
                    'bucket' => null,
                    'endpoint' => $baseUrl,
                    'public_url' => $this->sampleRemoteCoverUrl($allVehicles),
                ],
                'screen_load' => $screenLoad,
                'vehicles' => $rows,
                'summary' => $this->summarize($rows, $screenLoad),
            ];

            $this->writeReport($label, $report);
        } finally {
            $user->tokens()->where('name', 'like', 'benchmark-remote-%')->delete();
            $this->remoteApiToken = null;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{median_ms: float|null, vehicles: array<int, array<string, mixed>>}
     */
    private function fetchRemoteMyVehicles(string $baseUrl, string $token, int $rounds): array
    {
        $timings = [];
        $vehicles = [];

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            $response = Http::timeout(120)
                ->withToken($token)
                ->acceptJson()
                ->get("{$baseUrl}/api/v1/my-vehicles");
            $timings[] = round((microtime(true) - $started) * 1000, 2);

            if ($response->successful()) {
                $vehicles = $response->json('data') ?? [];
            }
        }

        return [
            'median_ms' => $this->median($timings),
            'vehicles' => is_array($vehicles) ? $vehicles : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemoteAllVehicles(string $baseUrl, string $token): array
    {
        $vehicles = [];

        foreach (Vehicle::query()->whereNotNull('cover_photo_path')->where('cover_photo_path', '!=', '')->orderBy('id')->get() as $vehicle) {
            $response = Http::timeout(120)
                ->withToken($token)
                ->acceptJson()
                ->get("{$baseUrl}/api/v1/vehicles/{$vehicle->id}");

            if (! $response->successful()) {
                continue;
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                continue;
            }

            $url = $data['cover_photo_url'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }

            $vehicles[] = [
                'vehicle_id' => (int) $data['id'],
                'license_plate' => (string) ($data['license_plate'] ?? $vehicle->license_plate),
                'label' => trim(((string) ($data['brand'] ?? $vehicle->brand)).' '.((string) ($data['model'] ?? $vehicle->model))),
                'cover_photo_url' => $url,
            ];
        }

        return $vehicles;
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return array<string, mixed>
     */
    private function benchmarkRemoteCover(array $vehicle, int $rounds): array
    {
        $url = (string) $vehicle['cover_photo_url'];
        $httpMs = [];
        $httpStatus = null;
        $sizeBytes = null;

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            try {
                $response = Http::timeout(120)
                    ->withOptions(['connect_timeout' => 20])
                    ->get($url);
                $httpMs[] = round((microtime(true) - $started) * 1000, 2);
                $httpStatus = $response->status();
                $sizeBytes ??= strlen($response->body());
            } catch (\Throwable) {
                $httpMs[] = round((microtime(true) - $started) * 1000, 2);
            }
        }

        return [
            'vehicle_id' => $vehicle['vehicle_id'],
            'license_plate' => $vehicle['license_plate'],
            'label' => $vehicle['label'],
            'path' => parse_url($url, PHP_URL_PATH) ?: $url,
            'size_kb' => $sizeBytes !== null ? round($sizeBytes / 1024, 1) : null,
            'url_length' => strlen($url),
            'http_download_ms' => $httpMs,
            'http_download_median_ms' => $this->median($httpMs),
            'http_status' => $httpStatus,
            'cover_photo_url' => $url,
            'error' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $listVehicles
     * @param  Collection<int, array<string, mixed>>  $allVehicles
     * @param  Collection<int, array<string, mixed>>  $httpByVehicleId
     * @return array<string, mixed>
     */
    private function benchmarkRemoteScreenLoads(
        ?float $apiListMedianMs,
        Collection $listVehicles,
        Collection $allVehicles,
        Collection $httpByVehicleId,
        int $rounds,
    ): array {
        $listUrls = $listVehicles
            ->pluck('cover_photo_url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $allUrls = $allVehicles
            ->pluck('cover_photo_url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $listHttpMedians = $listVehicles
            ->map(fn (array $vehicle) => (float) ($httpByVehicleId->get($vehicle['id'])['http_download_median_ms'] ?? 0))
            ->filter(fn (float $value) => $value > 0)
            ->values()
            ->all();

        $allHttpMedians = $allVehicles
            ->map(fn (array $vehicle) => (float) ($httpByVehicleId->get($vehicle['vehicle_id'])['http_download_median_ms'] ?? 0))
            ->filter(fn (float $value) => $value > 0)
            ->values()
            ->all();

        $largest = $allVehicles->sortByDesc(fn (array $vehicle) => $httpByVehicleId->get($vehicle['vehicle_id'])['size_kb'] ?? 0)->first();

        $scenarios = [
            'app_lista_veiculos' => $this->screenScenario(
                'Prod — app lista (API remota + capas em paralelo)',
                count($listUrls),
                $apiListMedianMs,
                $listHttpMedians,
                $this->parallelDownloadMedians($listUrls, $rounds),
                'Medido de fora contra '.$this->option('base-url'),
            ),
            'web_lista_veiculos' => $this->screenScenario(
                'Prod — web/app todas as capas (URLs da API remota)',
                count($allUrls),
                0.0,
                $allHttpMedians,
                $this->parallelDownloadMedians($allUrls, $rounds),
                'Download paralelo das capas servidas em produção',
            ),
        ];

        if (is_array($largest)) {
            $detailMs = (float) ($httpByVehicleId->get($largest['vehicle_id'])['http_download_median_ms'] ?? 0);
            $detailApi = $this->benchmarkRemoteVehicleShow($largest['vehicle_id'], $rounds);

            $scenarios['app_detalhe_veiculo'] = [
                'tela' => 'Prod — app detalhe (API remota + capa)',
                'descricao' => 'Pior caso: maior capa servida em produção',
                'veiculo' => $largest['license_plate'],
                'capas' => 1,
                'api_ms' => $detailApi,
                'primeira_capa_visivel_ms' => round(($detailApi ?? 0) + $detailMs, 2),
                'todas_capas_visiveis_ms' => round(($detailApi ?? 0) + $detailMs, 2),
                'size_kb' => $httpByVehicleId->get($largest['vehicle_id'])['size_kb'] ?? null,
            ];
        }

        return $scenarios;
    }

    private function benchmarkRemoteVehicleShow(int $vehicleId, int $rounds): ?float
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: 'https://vehicle-maintenance-production-l6pnoo.laravel.cloud'), '/');

        if ($this->remoteApiToken === null) {
            return null;
        }

        $timings = [];
        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            Http::timeout(120)
                ->withToken($this->remoteApiToken)
                ->acceptJson()
                ->get("{$baseUrl}/api/v1/vehicles/{$vehicleId}");
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return $this->median($timings);
    }

    /**
     * @param  array<int, array<string, mixed>>  $vehicles
     */
    private function detectRemoteStorageKind(array $vehicles): string
    {
        foreach ($vehicles as $vehicle) {
            $url = (string) ($vehicle['cover_photo_url'] ?? '');
            if (str_contains($url, '.r2.dev')) {
                return 'r2';
            }
            if (str_contains($url, 'X-Amz-Signature') || str_contains($url, 'neon.tech')) {
                return 's3';
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<int, array<string, mixed>>  $vehicles
     */
    private function sampleRemoteCoverUrl(array $vehicles): ?string
    {
        $url = $vehicles[0]['cover_photo_url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * @param  Collection<int, Vehicle>  $userVehicles
     * @param  Collection<int, Vehicle>  $allVehicles
     * @param  Collection<int, array<string, mixed>>  $httpByVehicleId
     * @return array<string, mixed>
     */
    private function benchmarkScreenLoads(
        ?User $user,
        Collection $userVehicles,
        Collection $allVehicles,
        Collection $httpByVehicleId,
        int $rounds,
    ): array {
        $scenarios = [];

        if ($user !== null && $userVehicles->isNotEmpty()) {
            $apiList = $this->benchmarkAppListApi($user, $rounds);
            $userUrls = $this->coverUrls($userVehicles);
            $userHttpMedians = $this->httpMediansForVehicles($userVehicles, $httpByVehicleId);

            $scenarios['app_lista_veiculos'] = $this->screenScenario(
                'App — lista de veículos (HomePage): spinner até API responder, depois capas em paralelo',
                $userVehicles->count(),
                $apiList['median_ms'],
                $userHttpMedians,
                $this->parallelDownloadMedians($userUrls, $rounds),
            );
        }

        $largestVehicle = $allVehicles->sortByDesc(fn (Vehicle $vehicle) => $httpByVehicleId->get($vehicle->id)['size_kb'] ?? 0)->first();
        if ($largestVehicle !== null) {
            $detailApi = $this->benchmarkAppDetailApi($largestVehicle, $rounds);
            $detailHttp = (float) ($httpByVehicleId->get($largestVehicle->id)['http_download_median_ms'] ?? 0);

            $scenarios['app_detalhe_veiculo'] = [
                'tela' => 'App — detalhe do veículo (VehicleDetailPage)',
                'descricao' => 'API do veículo + capa hero (pior caso: maior arquivo)',
                'veiculo' => $largestVehicle->license_plate,
                'capas' => 1,
                'api_ms' => $detailApi['median_ms'],
                'primeira_capa_visivel_ms' => round($detailApi['median_ms'] + $detailHttp, 2),
                'todas_capas_visiveis_ms' => round($detailApi['median_ms'] + $detailHttp, 2),
                'size_kb' => $httpByVehicleId->get($largestVehicle->id)['size_kb'] ?? null,
            ];
        }

        if ($user !== null && $userVehicles->isNotEmpty()) {
            $serverDashboard = $this->benchmarkWebServerRender($user, $rounds);
            $dashboardUrls = $this->coverUrls($userVehicles);
            $dashboardHttpMedians = $this->httpMediansForVehicles($userVehicles, $httpByVehicleId);

            $scenarios['web_dashboard'] = $this->screenScenario(
                'Web — dashboard do usuário (user.dashboard)',
                $userVehicles->count(),
                $serverDashboard['median_ms'],
                $dashboardHttpMedians,
                $this->parallelDownloadMedians($dashboardUrls, $rounds),
                'HTML pronto no servidor + navegador baixa <img> em paralelo',
            );
        }

        $indexUrls = $this->coverUrls($allVehicles);
        $indexHttpMedians = $this->httpMediansForVehicles($allVehicles, $httpByVehicleId);
        $indexServer = $this->benchmarkWebIndexRender($allVehicles, $rounds);

        $scenarios['web_lista_veiculos'] = $this->screenScenario(
            'Web — lista de veículos (user.vehicles.index)',
            $allVehicles->count(),
            $indexServer['median_ms'],
            $indexHttpMedians,
            $this->parallelDownloadMedians($indexUrls, $rounds),
            'Todas as capas em variant card',
        );

        if ($largestVehicle !== null) {
            $showServer = $this->benchmarkWebShowRender($largestVehicle, $rounds);
            $detailHttp = (float) ($httpByVehicleId->get($largestVehicle->id)['http_download_median_ms'] ?? 0);

            $scenarios['web_detalhe_veiculo'] = [
                'tela' => 'Web — detalhe do veículo (user.vehicles.show)',
                'descricao' => 'HTML pronto + capa hero (pior caso: maior arquivo)',
                'veiculo' => $largestVehicle->license_plate,
                'capas' => 1,
                'api_ms' => $showServer['median_ms'],
                'primeira_capa_visivel_ms' => round($showServer['median_ms'] + $detailHttp, 2),
                'todas_capas_visiveis_ms' => round($showServer['median_ms'] + $detailHttp, 2),
                'size_kb' => $httpByVehicleId->get($largestVehicle->id)['size_kb'] ?? null,
            ];
        }

        return $scenarios;
    }

    /**
     * @param  array<int, float>  $httpMedians
     * @param  array<int, float>  $parallelMedians
     * @return array<string, mixed>
     */
    private function screenScenario(
        string $tela,
        int $coverCount,
        ?float $apiOrServerMs,
        array $httpMedians,
        array $parallelMedians,
        ?string $descricao = null,
    ): array {
        $apiMs = $apiOrServerMs ?? 0.0;
        $minHttp = $httpMedians !== [] ? min($httpMedians) : 0.0;
        $parallelMs = $this->median($parallelMedians) ?? $minHttp;
        $sequentialMs = array_sum($httpMedians);

        return [
            'tela' => $tela,
            'descricao' => $descricao ?? 'Tempo até conteúdo útil na tela (API/HTML + imagens)',
            'capas' => $coverCount,
            'api_ms' => $apiOrServerMs,
            'primeira_capa_visivel_ms' => round($apiMs + $minHttp, 2),
            'todas_capas_visiveis_ms' => round($apiMs + $parallelMs, 2),
            'todas_capas_sequencial_ms' => round($apiMs + $sequentialMs, 2),
            'parallel_pool_median_ms' => $this->median($parallelMedians),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, string>
     */
    private function coverUrls(Collection $vehicles): array
    {
        return $vehicles
            ->map(fn (Vehicle $vehicle) => AppStorage::coversUrl((string) $vehicle->cover_photo_path))
            ->filter(fn (?string $url) => is_string($url) && $url !== '')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  Collection<int, array<string, mixed>>  $httpByVehicleId
     * @return array<int, float>
     */
    private function httpMediansForVehicles(Collection $vehicles, Collection $httpByVehicleId): array
    {
        return $vehicles
            ->map(fn (Vehicle $vehicle) => (float) ($httpByVehicleId->get($vehicle->id)['http_download_median_ms'] ?? 0))
            ->filter(fn (float $value) => $value > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, float>
     */
    private function parallelDownloadMedians(array $urls, int $rounds): array
    {
        if ($urls === []) {
            return [];
        }

        $timings = [];
        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            try {
                Http::pool(function (Pool $pool) use ($urls) {
                    foreach ($urls as $index => $url) {
                        $pool->as((string) $index)
                            ->timeout(120)
                            ->withOptions(['connect_timeout' => 20])
                            ->get($url);
                    }
                });
            } catch (\Throwable) {
                // Medimos o tempo mesmo com falhas parciais.
            }
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return $timings;
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkAppListApi(User $user, int $rounds): array
    {
        $timings = [];
        $count = 0;

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            $vehicles = $user->currentVehicles()->orderBy('vehicles.id')->get();
            $payload = [
                'success' => true,
                'data' => $vehicles->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'license_plate' => $vehicle->license_plate,
                    'cover_photo_url' => $vehicle->cover_photo_url,
                ])->values()->all(),
            ];
            json_encode($payload, JSON_THROW_ON_ERROR);
            $timings[] = round((microtime(true) - $started) * 1000, 2);
            $count = count($payload['data']);
        }

        return [
            'vehicle_count' => $count,
            'rounds_ms' => $timings,
            'median_ms' => $this->median($timings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkAppDetailApi(Vehicle $vehicle, int $rounds): array
    {
        $timings = [];

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            $loaded = Vehicle::query()->findOrFail($vehicle->id);
            $payload = [
                'success' => true,
                'data' => [
                    'id' => $loaded->id,
                    'license_plate' => $loaded->license_plate,
                    'cover_photo_url' => $loaded->cover_photo_url,
                ],
            ];
            json_encode($payload, JSON_THROW_ON_ERROR);
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return ['rounds_ms' => $timings, 'median_ms' => $this->median($timings)];
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkWebServerRender(User $user, int $rounds): array
    {
        $timings = [];

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            $vehicles = $user->currentVehicles()->withCount('maintenances')->get();
            foreach ($vehicles as $vehicle) {
                $vehicle->cover_photo_url;
            }
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return ['rounds_ms' => $timings, 'median_ms' => $this->median($timings)];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<string, mixed>
     */
    private function benchmarkWebIndexRender(Collection $vehicles, int $rounds): array
    {
        $timings = [];

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            foreach ($vehicles as $vehicle) {
                $vehicle->cover_photo_url;
            }
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return ['rounds_ms' => $timings, 'median_ms' => $this->median($timings)];
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkWebShowRender(Vehicle $vehicle, int $rounds): array
    {
        $timings = [];

        for ($i = 0; $i < $rounds; $i++) {
            $started = microtime(true);
            $loaded = Vehicle::query()->findOrFail($vehicle->id);
            $loaded->cover_photo_url;
            $timings[] = round((microtime(true) - $started) * 1000, 2);
        }

        return ['rounds_ms' => $timings, 'median_ms' => $this->median($timings)];
    }

    /**
     * @return array<string, mixed>
     */
    private function benchmarkVehicle(Vehicle $vehicle, string $path, int $rounds): array
    {
        $disk = AppStorage::coversDisk();

        $sizeBytes = null;
        $diskReadMs = null;
        $urlMs = null;
        $httpMs = [];
        $url = null;
        $httpStatus = null;
        $error = null;

        $started = microtime(true);
        try {
            if ($disk->exists($path)) {
                $sizeBytes = $disk->size($path);
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }
        $diskMetaMs = round((microtime(true) - $started) * 1000, 2);

        $started = microtime(true);
        try {
            $contents = AppStorage::contents($path);
            $diskReadMs = round((microtime(true) - $started) * 1000, 2);
            if ($sizeBytes === null && is_string($contents)) {
                $sizeBytes = strlen($contents);
            }
        } catch (\Throwable $exception) {
            $diskReadMs = round((microtime(true) - $started) * 1000, 2);
            $error ??= $exception->getMessage();
        }

        $started = microtime(true);
        try {
            $url = AppStorage::coversUrl($path);
            $urlMs = round((microtime(true) - $started) * 1000, 2);
        } catch (\Throwable $exception) {
            $urlMs = round((microtime(true) - $started) * 1000, 2);
            $error ??= $exception->getMessage();
        }

        if (is_string($url) && $url !== '') {
            for ($i = 0; $i < $rounds; $i++) {
                $started = microtime(true);
                try {
                    $response = Http::timeout(120)
                        ->withOptions(['connect_timeout' => 20])
                        ->get($url);
                    $httpMs[] = round((microtime(true) - $started) * 1000, 2);
                    $httpStatus = $response->status();
                } catch (\Throwable $exception) {
                    $httpMs[] = round((microtime(true) - $started) * 1000, 2);
                    $error ??= $exception->getMessage();
                }
            }
        }

        return [
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'label' => trim("{$vehicle->brand} {$vehicle->model}"),
            'path' => $path,
            'size_kb' => $sizeBytes !== null ? round($sizeBytes / 1024, 1) : null,
            'disk_meta_ms' => $diskMetaMs,
            'disk_read_ms' => $diskReadMs,
            'url_generate_ms' => $urlMs,
            'url_length' => is_string($url) ? strlen($url) : null,
            'http_download_ms' => $httpMs,
            'http_download_median_ms' => $this->median($httpMs),
            'http_status' => $httpStatus,
            'error' => $error,
        ];
    }

    private function resolveUser(): ?User
    {
        $email = (string) ($this->option('user') ?: 'fgoncalves2008@gmail.com');

        return User::query()->where('email', $email)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function storageMeta(): array
    {
        $diskName = AppStorage::coversDiskName();

        return [
            'covers_disk' => $diskName,
            'driver' => config('filesystems.disks.'.$diskName.'.driver'),
            'bucket' => config('filesystems.disks.'.$diskName.'.bucket'),
            'endpoint' => config('filesystems.disks.'.$diskName.'.endpoint'),
            'public_url' => config('filesystems.disks.'.$diskName.'.url'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $screenLoad
     * @return array<string, mixed>
     */
    private function summarize(array $rows, array $screenLoad): array
    {
        $httpMedians = array_values(array_filter(array_map(
            fn (array $row) => $row['http_download_median_ms'] ?? null,
            $rows
        ), fn ($value) => $value !== null));

        $summary = [
            'covers_count' => count($rows),
            'http_download_median_avg_ms' => $httpMedians !== [] ? round(array_sum($httpMedians) / count($httpMedians), 2) : null,
            'http_download_median_p95_ms' => $this->percentile($httpMedians, 95),
        ];

        foreach ($screenLoad as $key => $scenario) {
            $summary["screen_{$key}_todas_capas_ms"] = $scenario['todas_capas_visiveis_ms'] ?? null;
            $summary["screen_{$key}_primeira_capa_ms"] = $scenario['primeira_capa_visivel_ms'] ?? null;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(string $label, array $report): void
    {
        $dir = storage_path('app/benchmarks');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $jsonPath = "{$dir}/vehicle-covers-{$label}-{$stamp}.json";
        $mdPath = "{$dir}/vehicle-covers-{$label}-{$stamp}.md";
        $latestJson = "{$dir}/vehicle-covers-{$label}-latest.json";
        $latestMd = "{$dir}/vehicle-covers-{$label}-latest.md";

        $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonPath, $encoded);
        file_put_contents($latestJson, $encoded);

        $markdown = $this->toMarkdown($report);
        file_put_contents($mdPath, $markdown);
        file_put_contents($latestMd, $markdown);

        $this->newLine();
        $this->line($markdown);
        $this->newLine();
        $this->info("JSON: {$jsonPath}");
        $this->info("Markdown: {$mdPath}");
        $this->info('Compare: php artisan benchmark:vehicle-covers-compare');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Benchmark — carregamento de telas ('.$report['label'].')';
        $lines[] = '';
        $lines[] = '- **Gerado em:** '.$report['generated_at'];
        if (($report['mode'] ?? null) === 'remote') {
            $lines[] = '- **Modo:** produção remota (API + download cliente)';
            $lines[] = '- **Base URL:** '.($report['base_url'] ?? '—');
        }
        $lines[] = '- **Disco:** '.$report['storage']['covers_disk'].' ('.$report['storage']['driver'].')';
        $lines[] = '- **Bucket:** '.($report['storage']['bucket'] ?? '—');
        $lines[] = '- **Endpoint:** '.($report['storage']['endpoint'] ?? '—');
        if (! empty($report['storage']['public_url'])) {
            $lines[] = '- **Exemplo URL capa:** '.$report['storage']['public_url'];
        }
        $lines[] = '';
        $lines[] = '## Tempo de carregamento das telas';
        $lines[] = '';
        $lines[] = 'Simula o que o usuário percebe: API/HTML primeiro, depois imagens (paralelo como app e navegador).';
        $lines[] = '';
        $lines[] = '| Tela | Capas | API/HTML (ms) | 1ª capa visível (ms) | Todas capas (paralelo, ms) | Todas capas (sequencial, ms) |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: |';

        foreach ($report['screen_load'] as $scenario) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | **%s** | %s |',
                $scenario['tela'] ?? '—',
                $scenario['capas'] ?? '—',
                $scenario['api_ms'] ?? '—',
                $scenario['primeira_capa_visivel_ms'] ?? '—',
                $scenario['todas_capas_visiveis_ms'] ?? '—',
                $scenario['todas_capas_sequencial_ms'] ?? '—',
            );
        }

        $lines[] = '';
        $lines[] = '## Resumo técnico';
        $lines[] = '';
        $lines[] = '| Métrica | Valor |';
        $lines[] = '| --- | ---: |';
        foreach ($report['summary'] as $key => $value) {
            $lines[] = '| '.$key.' | '.($value ?? '—').' |';
        }

        $lines[] = '';
        $lines[] = '## Por veículo (download HTTP individual)';
        $lines[] = '';
        $lines[] = '| ID | Placa | Veículo | KB | HTTP mediana (ms) | Status |';
        $lines[] = '| ---: | --- | --- | ---: | ---: | ---: |';
        foreach ($report['vehicles'] as $row) {
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s |',
                $row['vehicle_id'],
                $row['license_plate'],
                $row['label'],
                $row['size_kb'] ?? '—',
                $row['http_download_median_ms'] ?? '—',
                $row['http_status'] ?? '—',
            );
        }

        $lines[] = '';
        $lines[] = '> Depois de migrar para R2: `php artisan benchmark:vehicle-covers --label=r2-cloudflare`';
        $lines[] = '> Tabela comparativa: `php artisan benchmark:vehicle-covers-compare`';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, float|null>  $values
     */
    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== null));
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = (int) floor(count($values) / 2);

        if (count($values) % 2 === 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
        }

        return round($values[$middle], 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, int $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return round($values[$index], 2);
    }
}
