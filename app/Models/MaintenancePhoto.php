<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePhoto extends Model
{
    use HasFactory;

    public const SUBJECT_VEHICLE = 'vehicle';

    public const SUBJECT_PART = 'part';

    public const STAGE_BEFORE = 'before';

    public const STAGE_AFTER = 'after';

    public const STAGE_DURING = 'during';

    public const MAX_PER_GROUP = 4;

    protected $fillable = [
        'maintenance_id',
        'maintenance_item_id',
        'subject',
        'stage',
        'path',
        'legacy_path',
        'thumb_path',
        'display_path',
        'width',
        'height',
        'bytes',
        'dominant_color',
        'processed_at',
        'original_name',
        'sort',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (MaintenancePhoto $photo): void {
            $photo->maintenance?->markAttachmentsPendingIfNeeded();
        });

        // Apagar a foto apaga todos os arquivos dela (master, thumb e display), qualquer que seja o
        // caminho que a apague: serviço, controller ou a exclusão da manutenção.
        static::deleting(function (MaintenancePhoto $photo): void {
            $photo->deleteStoredFiles();
        });
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => AppStorage::url($this->path));
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Diretório `maintenance-photos/{maintenance_id}/{uuid}` das fotos novas, que guarda master,
     * thumb e display juntos. Nulo nas fotos antigas, cujo `path` é um arquivo solto
     * (`maintenance-photos/{id}_{uniqid}.ext`) até o backfill (`photos:generate-variants`).
     */
    public function storageDirectory(): ?string
    {
        $path = (string) $this->path;

        if (preg_match('#^('.preg_quote(AppStorage::MAINTENANCE_PHOTOS_PREFIX, '#').'\d+/[0-9a-f-]{36})/master\.jpg$#', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Apaga os arquivos no storage: o diretório inteiro no layout novo, o arquivo solto (mais as
     * variantes, se existirem) no antigo e, se o backfill migrou a foto, o arquivo antigo guardado em
     * `legacy_path` (apagar a foto é a vontade do usuário; o backfill nunca apaga o legado). Não mexe
     * na linha.
     */
    public function deleteStoredFiles(): void
    {
        $disk = AppStorage::disk();
        $directory = $this->storageDirectory();

        if ($directory !== null) {
            $disk->deleteDirectory($directory);
        }

        $paths = $directory !== null
            ? [$this->legacy_path]
            : [$this->path, $this->thumb_path, $this->display_path, $this->legacy_path];

        foreach (array_filter($paths) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function maintenanceItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublicVisible(): bool
    {
        return $this->subject === self::SUBJECT_VEHICLE
            && $this->stage === self::STAGE_AFTER;
    }
}
