<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AppStorage
{
    public const COVERS_PREFIX = 'vehicle-covers/';

    public static function diskName(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    public static function coversDiskName(): string
    {
        $configured = config('filesystems.covers_disk');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return self::diskName();
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function coversDisk(): Filesystem
    {
        return Storage::disk(self::coversDiskName());
    }

    public static function isCoverPath(string $storagePath): bool
    {
        return str_starts_with($storagePath, self::COVERS_PREFIX);
    }

    public static function diskForPath(string $storagePath): Filesystem
    {
        return self::isCoverPath($storagePath) ? self::coversDisk() : self::disk();
    }

    public static function diskNameForPath(string $storagePath): string
    {
        return self::isCoverPath($storagePath) ? self::coversDiskName() : self::diskName();
    }

    public static function isRemote(): bool
    {
        return config('filesystems.disks.'.self::diskName().'.driver') === 's3';
    }

    public static function isCoversRemote(): bool
    {
        return config('filesystems.disks.'.self::coversDiskName().'.driver') === 's3';
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
     * @return array{path: string, temporary: bool, content?: string}|null
     */
    public static function localCopy(string $storagePath): ?array
    {
        $disk = self::diskForPath($storagePath);
        $isRemote = self::isCoverPath($storagePath) ? self::isCoversRemote() : self::isRemote();

        if (! $isRemote) {
            if (! $disk->exists($storagePath)) {
                return null;
            }

            return [
                'path' => $disk->path($storagePath),
                'temporary' => false,
            ];
        }

        $contents = self::contents($storagePath);
        if ($contents === null) {
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
            'content' => $contents,
        ];
    }

    /**
     * Read object bytes, falling back to a short-lived signed URL fetch.
     */
    public static function contents(string $storagePath): ?string
    {
        try {
            $contents = self::readableDiskForPath($storagePath)->get($storagePath);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $isRemote = self::isCoverPath($storagePath) ? self::isCoversRemote() : self::isRemote();

        if (! $isRemote) {
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->withOptions(array_merge(
                    ['connect_timeout' => 20],
                    StorageEndpointResolver::httpOptions()
                ))
                ->get(self::urlForPath($storagePath, now()->addMinutes(10)));

            if ($response->successful() && $response->body() !== '') {
                return $response->body();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /**
     * The app disk swallows read errors (`throw => false`), which hides the real
     * S3 failure. Reads go through a disk that raises instead.
     */
    private static function readableDisk(): Filesystem
    {
        return self::readableDiskForPath('');
    }

    private static function readableDiskForPath(string $storagePath): Filesystem
    {
        $diskName = self::diskNameForPath($storagePath);
        $config = config('filesystems.disks.'.$diskName);

        if (! is_array($config)) {
            return self::diskForPath($storagePath);
        }

        return Storage::build(array_merge($config, ['throw' => true, 'report' => true]));
    }

    public static function coversUrl(string $path, ?\DateTimeInterface $expiresAt = null): string
    {
        return self::urlOnDisk(self::coversDiskName(), $path, $expiresAt);
    }

    public static function url(string $path, ?\DateTimeInterface $expiresAt = null): string
    {
        return self::urlForPath($path, $expiresAt);
    }

    public static function urlForPath(string $path, ?\DateTimeInterface $expiresAt = null): string
    {
        return self::urlOnDisk(self::diskNameForPath($path), $path, $expiresAt);
    }

    public static function urlOnDisk(string $diskName, string $path, ?\DateTimeInterface $expiresAt = null): string
    {
        $driver = config('filesystems.disks.'.$diskName.'.driver');

        if ($driver === 's3') {
            $publicUrl = config('filesystems.disks.'.$diskName.'.url');
            $visibility = config('filesystems.disks.'.$diskName.'.visibility');

            if (is_string($publicUrl) && $publicUrl !== '' && $visibility === 'public') {
                return rtrim($publicUrl, '/').'/'.ltrim($path, '/');
            }

            $expiresAt ??= now()->addMinutes(60);
            // SigV4 presigned URLs must expire in less than 7 days.
            $maxExpiry = now()->addDays(7)->subMinute();
            if ($expiresAt > $maxExpiry) {
                $expiresAt = $maxExpiry;
            }

            return Storage::disk($diskName)->temporaryUrl($path, $expiresAt);
        }

        return asset('storage/'.$path);
    }
}
