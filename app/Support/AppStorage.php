<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class AppStorage
{
    public static function diskName(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function isRemote(): bool
    {
        return config('filesystems.disks.'.self::diskName().'.driver') === 's3';
    }

    public static function localPath(string $storagePath): string
    {
        $copy = self::localCopy($storagePath);

        if ($copy === null) {
            throw new \RuntimeException("Arquivo não encontrado: {$storagePath}");
        }

        return $copy['path'];
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    public static function localCopy(string $storagePath): ?array
    {
        $disk = self::disk();

        if (! self::isRemote()) {
            if (! $disk->exists($storagePath)) {
                return null;
            }

            return [
                'path' => $disk->path($storagePath),
                'temporary' => false,
            ];
        }

        try {
            $contents = $disk->get($storagePath);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 'vm_');
        if (is_string($extension) && $extension !== '') {
            $named = $tmp.'.'.$extension;
            rename($tmp, $named);
            $tmp = $named;
        }

        file_put_contents($tmp, $contents);

        return [
            'path' => $tmp,
            'temporary' => true,
        ];
    }

    public static function url(string $path, ?\DateTimeInterface $expiresAt = null): string
    {
        $driver = config('filesystems.disks.'.self::diskName().'.driver');

        if ($driver === 's3') {
            return self::disk()->temporaryUrl($path, $expiresAt ?? now()->addMinutes(60));
        }

        return asset('storage/'.$path);
    }
}
