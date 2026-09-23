<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppStorage
{
    public const COVERS_PREFIX = 'vehicle-covers/';

    public const WORKSHOP_LOGOS_PREFIX = 'workshop-logos/';

    public const MAINTENANCE_PHOTOS_PREFIX = 'maintenance-photos/';

    public const BLOG_COVERS_PREFIX = 'blog-covers/';

    public const BRAND_PREFIX = 'brand/revisalog/';

    public const LANDING_PREFIX = 'landing/';

    public const PUBLIC_CACHE_CONTROL = 'public, max-age=31536000, immutable';

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

    public static function isWorkshopLogoPath(string $storagePath): bool
    {
        return str_starts_with($storagePath, self::WORKSHOP_LOGOS_PREFIX);
    }

    public static function isBlogCoverPath(string $storagePath): bool
    {
        return str_starts_with($storagePath, self::BLOG_COVERS_PREFIX);
    }

    public static function isBrandPath(string $storagePath): bool
    {
        return str_starts_with($storagePath, self::BRAND_PREFIX);
    }

    public static function isLandingPath(string $storagePath): bool
    {
        return str_starts_with($storagePath, self::LANDING_PREFIX);
    }

    public static function isPublicCacheEligiblePath(string $storagePath): bool
    {
        return self::isCoverPath($storagePath)
            || self::isWorkshopLogoPath($storagePath)
            || self::isBlogCoverPath($storagePath)
            || self::isBrandPath($storagePath)
            || self::isLandingPath($storagePath);
    }

    /**
     * @return array{visibility: string, CacheControl: string}
     */
    public static function publicObjectOptions(): array
    {
        return [
            'visibility' => 'public',
            'CacheControl' => self::PUBLIC_CACHE_CONTROL,
        ];
    }

    public static function putPublic(string $path, string $contents): void
    {
        if (! self::isPublicCacheEligiblePath($path)) {
            throw new \InvalidArgumentException("Path not eligible for public cache headers: {$path}");
        }

        self::coversDisk()->put($path, $contents, self::publicObjectOptions());
    }

    public static function usesCoversDisk(string $storagePath): bool
    {
        return self::isCoverPath($storagePath)
            || self::isWorkshopLogoPath($storagePath)
            || self::isBlogCoverPath($storagePath)
            || self::isBrandPath($storagePath)
            || self::isLandingPath($storagePath);
    }

    public static function diskForPath(string $storagePath): Filesystem
    {
        return self::usesCoversDisk($storagePath) ? self::coversDisk() : self::disk();
    }

    public static function diskNameForPath(string $storagePath): string
    {
        return self::usesCoversDisk($storagePath) ? self::coversDiskName() : self::diskName();
    }

    public static function isRemote(): bool
    {
        return config('filesystems.disks.'.self::diskName().'.driver') === 's3';
    }

    public static function isCoversRemote(): bool
    {
        return config('filesystems.disks.'.self::coversDiskName().'.driver') === 's3';
    }

    public static function isRemotePath(string $storagePath): bool
    {
        return self::usesCoversDisk($storagePath) ? self::isCoversRemote() : self::isRemote();
    }

    public static function isPublicRemotePath(string $storagePath): bool
    {
        if (! self::isRemotePath($storagePath)) {
            return false;
        }

        $diskName = self::diskNameForPath($storagePath);

        return config('filesystems.disks.'.$diskName.'.visibility') === 'public'
            && is_string(config('filesystems.disks.'.$diskName.'.url'))
            && config('filesystems.disks.'.$diskName.'.url') !== '';
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
        $copies = self::localCopies([$storagePath]);

        return $copies[$storagePath] ?? null;
    }

    /**
     * Batch-fetch storage objects. Deduplicates paths and prefetches remote
     * bytes via Http::pool (public CDN URL first, S3 GetObject fallback).
     *
     * @param  list<string>  $storagePaths
     * @return array<string, array{path: string, temporary: bool, content?: string}>
     */
    public static function localCopies(array $storagePaths): array
    {
        $uniquePaths = array_values(array_unique(array_filter(
            $storagePaths,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        )));

        if ($uniquePaths === []) {
            return [];
        }

        $results = [];
        $remotePaths = [];

        foreach ($uniquePaths as $path) {
            if (! self::isRemotePath($path)) {
                $copy = self::localCopyFromDisk($path);
                if ($copy !== null) {
                    $results[$path] = $copy;
                }

                continue;
            }

            $remotePaths[] = $path;
        }

        if ($remotePaths === []) {
            return $results;
        }

        $bytesByPath = self::fetchRemoteBytes($remotePaths);

        foreach ($remotePaths as $path) {
            $bytes = $bytesByPath[$path] ?? null;
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            $results[$path] = self::bytesToTempCopy($path, $bytes);
        }

        return $results;
    }

    /**
     * Read object bytes, preferring public HTTP for covers/logos/brand.
     */
    public static function contents(string $storagePath): ?string
    {
        if (self::isPublicRemotePath($storagePath)) {
            $cached = self::cachedPublicBytes($storagePath);
            if ($cached !== null) {
                return $cached;
            }

            $url = self::publicUrlForPath($storagePath);
            if ($url !== null) {
                $bytes = self::httpGetBytes($url);
                if ($bytes !== null) {
                    self::cachePublicBytes($storagePath, $bytes);

                    return $bytes;
                }
            }
        }

        try {
            $contents = self::readableDiskForPath($storagePath)->get($storagePath);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (! self::isRemotePath($storagePath)) {
            return null;
        }

        try {
            $response = self::configureHttp(Http::connectTimeout(5))
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
     * @param  list<string>  $storagePaths
     * @return array<string, string>
     */
    private static function fetchRemoteBytes(array $storagePaths): array
    {
        $results = [];
        $toFetch = [];

        foreach ($storagePaths as $path) {
            if (self::isPublicRemotePath($path)) {
                $cached = self::cachedPublicBytes($path);
                if ($cached !== null) {
                    $results[$path] = $cached;

                    continue;
                }

                $url = self::publicUrlForPath($path);
                if ($url !== null) {
                    $toFetch[$path] = $url;

                    continue;
                }
            }

            $toFetch[$path] = self::urlForPath($path, now()->addMinutes(10));
        }

        if ($toFetch !== []) {
            foreach (self::httpPoolGet($toFetch) as $path => $bytes) {
                $results[$path] = $bytes;

                if (self::isPublicRemotePath($path)) {
                    self::cachePublicBytes($path, $bytes);
                }
            }
        }

        foreach ($storagePaths as $path) {
            if (isset($results[$path])) {
                continue;
            }

            $bytes = self::s3GetObjectBytes($path);
            if ($bytes !== null) {
                $results[$path] = $bytes;

                if (self::isPublicRemotePath($path)) {
                    self::cachePublicBytes($path, $bytes);
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, string>  $pathToUrl
     * @return array<string, string>
     */
    private static function httpPoolGet(array $pathToUrl): array
    {
        if ($pathToUrl === []) {
            return [];
        }

        try {
            $responses = Http::pool(
                function (Pool $pool) use ($pathToUrl) {
                    $requests = [];
                    foreach ($pathToUrl as $path => $url) {
                        $requests[] = self::configureHttp($pool->as($path))->get($url);
                    }

                    return $requests;
                },
                concurrency: 5,
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $results = [];

        foreach ($pathToUrl as $path => $url) {
            $response = $responses[$path] ?? null;
            if ($response instanceof \Illuminate\Http\Client\Response
                && $response->successful()
                && $response->body() !== '') {
                $results[$path] = $response->body();
            }
        }

        return $results;
    }

    private static function httpGetBytes(string $url): ?string
    {
        try {
            $response = self::configureHttp(Http::connectTimeout(5))->get($url);

            if ($response->successful() && $response->body() !== '') {
                return $response->body();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    private static function configureHttp(\Illuminate\Http\Client\PendingRequest $request): \Illuminate\Http\Client\PendingRequest
    {
        return $request
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 100, function (Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException && $exception->response->serverError();
            }, throw: false)
            ->withOptions(StorageEndpointResolver::httpOptions());
    }

    private static function s3GetObjectBytes(string $storagePath): ?string
    {
        try {
            $contents = self::readableDiskForPath($storagePath)->get($storagePath);

            return is_string($contents) && $contents !== '' ? $contents : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array{path: string, temporary: bool, content?: string}|null
     */
    private static function localCopyFromDisk(string $storagePath): ?array
    {
        $disk = self::diskForPath($storagePath);

        if (! $disk->exists($storagePath)) {
            return null;
        }

        return [
            'path' => $disk->path($storagePath),
            'temporary' => false,
        ];
    }

    /**
     * @return array{path: string, temporary: bool, content: string}
     */
    private static function bytesToTempCopy(string $storagePath, string $bytes): array
    {
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 'vm_');
        if (is_string($extension) && $extension !== '') {
            $named = $tmp.'.'.$extension;
            rename($tmp, $named);
            $tmp = $named;
        }

        file_put_contents($tmp, $bytes);

        return [
            'path' => $tmp,
            'temporary' => true,
            'content' => $bytes,
        ];
    }

    private static function cacheKey(string $storagePath): string
    {
        return 'app_storage_bytes:'.self::diskNameForPath($storagePath).':'.$storagePath;
    }

    private static function cachedPublicBytes(string $storagePath): ?string
    {
        $cached = Cache::get(self::cacheKey($storagePath));

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    private static function cachePublicBytes(string $storagePath, string $bytes): void
    {
        Cache::put(self::cacheKey($storagePath), $bytes, now()->addDays(7));
    }

    public static function publicUrlForPath(string $path): ?string
    {
        if (! self::isPublicRemotePath($path)) {
            return null;
        }

        return self::urlOnDisk(self::diskNameForPath($path), $path);
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

    public static function brandUrl(string $filename): string
    {
        $path = self::BRAND_PREFIX.ltrim($filename, '/');

        if (self::isCoversRemote() && self::isPublicRemotePath($path)) {
            return self::coversUrl($path);
        }

        return asset('images/brand/'.ltrim($filename, '/'));
    }

    public static function landingUrl(string $filename): string
    {
        $path = self::LANDING_PREFIX.ltrim($filename, '/');

        if (self::isCoversRemote() && self::isPublicRemotePath($path)) {
            return self::coversUrl($path);
        }

        return asset('images/landing/'.ltrim($filename, '/'));
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
