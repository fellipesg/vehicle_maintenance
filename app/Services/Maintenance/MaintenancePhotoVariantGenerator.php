<?php

namespace App\Services\Maintenance;

use App\Models\MaintenancePhoto;
use App\Support\AppStorage;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gera thumb e display de uma foto de manutenção e preenche as colunas (ProcessMaintenancePhoto e
 * photos:generate-variants --sync usam o mesmo caminho).
 *
 * Foto nova já tem master no layout `maintenance-photos/{maintenance_id}/{uuid}/master.jpg`: só as
 * variantes são criadas ao lado dele. Foto antiga (arquivo solto, original com EXIF) ganha também um
 * master novo, sem metadados, no layout novo; a linha passa a apontar para ele e o arquivo antigo
 * fica intacto: a linha guarda o caminho antigo em `legacy_path` e nenhum comando o apaga (a limpeza
 * do legado é uma decisão separada e explícita). Só apagar a foto remove o `legacy_path`.
 */
class MaintenancePhotoVariantGenerator
{
    public function __construct(private readonly MaintenancePhotoImageProcessor $images) {}

    /**
     * @throws FileNotFoundException quando o arquivo da foto não está no storage (pode ser atraso; vale tentar de novo)
     * @throws RuntimeException quando o arquivo não é uma imagem decodificável (não adianta tentar de novo)
     */
    public function generate(MaintenancePhoto $photo): void
    {
        if ($photo->isProcessed()) {
            return;
        }

        $disk = AppStorage::disk();
        $bytes = $this->read($disk, (string) $photo->path);

        if ($bytes === null) {
            throw new FileNotFoundException("Maintenance photo file not found: photo {$photo->id}");
        }

        $attributes = [];
        $legacyPath = null;
        $directory = $photo->storageDirectory();

        if ($directory === null) {
            $master = $this->images->master($bytes);
            $directory = AppStorage::MAINTENANCE_PHOTOS_PREFIX.$photo->maintenance_id.'/'.Str::uuid();
            $legacyPath = $photo->path;
            $bytes = $master['contents'];

            $disk->put($directory.'/master.jpg', $bytes);
            $attributes = [
                'path' => $directory.'/master.jpg',
                'legacy_path' => $legacyPath,
                'width' => $master['width'],
                'height' => $master['height'],
                'bytes' => $master['bytes'],
                'dominant_color' => $master['dominant_color'],
            ];
        } elseif ($photo->width === null || $photo->dominant_color === null) {
            $size = getimagesizefromstring($bytes);
            $attributes = [
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
                'bytes' => strlen($bytes),
            ];
        }

        $thumb = $this->images->thumb($bytes);
        $display = $this->images->display($bytes);

        $disk->put($directory.'/thumb.'.$thumb['extension'], $thumb['contents']);
        $disk->put($directory.'/display.'.$display['extension'], $display['contents']);

        $photo->forceFill($attributes + [
            'thumb_path' => $directory.'/thumb.'.$thumb['extension'],
            'display_path' => $directory.'/display.'.$display['extension'],
            'processed_at' => now(),
        ])->save();
    }

    private function read(Filesystem $disk, string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $contents = $disk->get($path);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }
}
