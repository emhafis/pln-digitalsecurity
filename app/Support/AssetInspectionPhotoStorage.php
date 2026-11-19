<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AssetInspectionPhotoStorage
{
    public const DISK = 'local';

    public const BASE_DIRECTORY = 'asset-inspections';

    /**
     * Store an inspection photo on the private local disk and return its relative path.
     */
    public static function store(UploadedFile $file, string $assetType, int $assetId): string
    {
        $type = self::normalizeAssetType($assetType);
        $directory = self::directory($type, $assetId);

        $extension = UploadFilename::extensionFromUpload($file, 'jpg');
        $filename = UploadFilename::make(['FOTO-INSPEKSI', $type, $assetId], $extension);
        $path = $directory.'/'.$filename;

        $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);
        if ($stored === false) {
            throw new \RuntimeException('Gagal menyimpan foto inspeksi.');
        }

        return $path;
    }

    /**
     * Delete a single stored photo path when it belongs to asset inspection storage.
     * Returns true if deleted or already absent.
     */
    public static function delete(?string $path): bool
    {
        $path = self::normalizePath($path);
        if ($path === null || ! self::isManagedPath($path)) {
            return false;
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path)) {
            return true;
        }

        return (bool) $disk->delete($path);
    }

    /**
     * @param  array<int, string|null>  $paths
     * @return array<int, string> deleted/absent managed paths
     */
    public static function deleteMany(array $paths): array
    {
        $handled = [];
        foreach ($paths as $path) {
            $normalized = self::normalizePath(is_string($path) ? $path : null);
            if ($normalized !== null && self::isManagedPath($normalized)) {
                self::delete($normalized);
                $handled[] = $normalized;
            }
        }

        return $handled;
    }

    public static function directory(string $assetType, int $assetId): string
    {
        $type = self::normalizeAssetType($assetType);

        return self::BASE_DIRECTORY.'/'.$type.'/'.$assetId;
    }

    public static function normalizeAssetType(string $assetType): string
    {
        $type = strtolower(trim($assetType));
        if (! in_array($type, ['apar', 'hydrant'], true)) {
            throw new \InvalidArgumentException('Jenis aset inspeksi tidak valid.');
        }

        return $type;
    }

    public static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '../') || str_starts_with($path, '/')) {
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
