<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AnnouncementImageStorage
{
    public const DISK = 'public';

    public const BASE_DIRECTORY = 'announcements';

    public static function store(UploadedFile $file, ?string $title = null): string
    {
        $directory = self::BASE_DIRECTORY;
        $extension = UploadFilename::extensionFromUpload($file, 'jpg');
        $filename = UploadFilename::dated([
            'PENGUMUMAN',
            UploadFilename::short($title ?? '', 15),
        ], $extension);
        $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        if ($stored === false) {
            throw new \RuntimeException('Gagal menyimpan gambar pengumuman.');
        }

        return $directory.'/'.$filename;
    }

    public static function delete(?string $path): void
    {
        $path = self::normalizePath($path);

        if ($path === null || ! self::isManagedPath($path)) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }

    public static function isManagedPath(string $path): bool
    {
        $path = self::normalizePath($path);

        return $path !== null && str_starts_with($path, self::BASE_DIRECTORY.'/');
    }
}
