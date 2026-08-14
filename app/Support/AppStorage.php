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

    public static function localPath(string $storagePath): string
    {
        $disk = self::disk();
        $driver = config('filesystems.disks.'.self::diskName().'.driver');

        if ($driver === 'local') {
            return $disk->path($storagePath);
        }

        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 'vm_');
        if (is_string($extension) && $extension !== '') {
            $named = $tmp.'.'.$extension;
            rename($tmp, $named);
            $tmp = $named;
        }

        file_put_contents($tmp, $disk->get($storagePath));

        return $tmp;
    }

    public static function url(string $path): string
    {
        $driver = config('filesystems.disks.'.self::diskName().'.driver');

        if ($driver === 's3') {
            return self::disk()->temporaryUrl($path, now()->addMinutes(60));
        }

        return asset('storage/'.$path);
    }
}
