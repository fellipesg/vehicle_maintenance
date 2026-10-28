<?php

namespace App\Services\Maintenance;

use App\Jobs\ProcessMaintenancePhoto;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MaintenancePhotoService
{
    public function __construct(private readonly MaintenancePhotoImageProcessor $images) {}

    /**
     * Guarda o master (até 2048px, JPEG q85, sem EXIF e com a orientação aplicada) em
     * `maintenance-photos/{maintenance_id}/{uuid}/master.jpg` e enfileira o ProcessMaintenancePhoto,
     * que gera thumb e display. O original enviado não é guardado: a foto do celular traz a
     * localização de quem fotografou.
     */
    public function store(
        Maintenance $maintenance,
        UploadedFile $file,
        string $subject,
        string $stage,
        User $user,
        ?int $maintenanceItemId = null,
    ): MaintenancePhoto {
        $this->assertGroupLimit($maintenance, $subject, $stage);

        try {
            $master = $this->images->master($this->contentsOf($file));
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'photo' => 'Não foi possível processar a imagem enviada.',
            ]);
        }

        $directory = AppStorage::MAINTENANCE_PHOTOS_PREFIX.$maintenance->id.'/'.Str::uuid();
        $path = $directory.'/master.jpg';
        AppStorage::disk()->put($path, $master['contents']);

        $sort = (int) MaintenancePhoto::query()
            ->where('maintenance_id', $maintenance->id)
            ->where('subject', $subject)
            ->where('stage', $stage)
            ->max('sort') + 1;

        try {
            $photo = MaintenancePhoto::create([
                'maintenance_id' => $maintenance->id,
                'maintenance_item_id' => $maintenanceItemId,
                'subject' => $subject,
                'stage' => $stage,
                'path' => $path,
                'width' => $master['width'],
                'height' => $master['height'],
                'bytes' => $master['bytes'],
                'dominant_color' => $master['dominant_color'],
                'original_name' => $file->getClientOriginalName(),
                'sort' => $sort,
                'created_by' => $user->id,
            ]);
        } catch (Throwable $exception) {
            AppStorage::disk()->deleteDirectory($directory);

            throw $exception;
        }

        ProcessMaintenancePhoto::dispatch($photo);

        return $photo;
    }

    /**
     * Apaga a linha; o evento `deleting` do model remove todos os arquivos (master, thumb, display).
     */
    public function delete(MaintenancePhoto $photo): void
    {
        $photo->delete();
    }

    public function assertGroupLimit(Maintenance $maintenance, string $subject, string $stage): void
    {
        $count = MaintenancePhoto::query()
            ->where('maintenance_id', $maintenance->id)
            ->where('subject', $subject)
            ->where('stage', $stage)
            ->count();

        if ($count >= MaintenancePhoto::MAX_PER_GROUP) {
            throw ValidationException::withMessages([
                'photo' => 'Limite de '.MaintenancePhoto::MAX_PER_GROUP.' fotos atingido para este grupo.',
            ]);
        }
    }

    private function contentsOf(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'photo' => 'Não foi possível ler o arquivo enviado.',
            ]);
        }

        return $contents;
    }
}
