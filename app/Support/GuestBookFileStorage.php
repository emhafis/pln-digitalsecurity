<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GuestBookFileStorage
{
    public const DISK = 'public';
    public const BASE_DIRECTORY = 'guest-book';

    public static function storeUploadedFile(UploadedFile $file, string $type, int $unitId, ?string $personName = null): string
    {
        $type = self::normalizeType($type);
        $directory = self::BASE_DIRECTORY.'/'.$type.'/'.$unitId;

        $extension = UploadFilename::extensionFromUpload($file, 'bin');
        $code = $type === 'selfie' ? 'GB-FOTO' : 'GB-TTD';
        $filename = UploadFilename::dated([
            $code,
            UploadFilename::short($personName ?? 'TAMU', 12),
        ], $extension);
        $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);
        if ($stored === false) {
            throw new \RuntimeException('Gagal menyimpan berkas.');
        }

        return $directory.'/'.$filename;
    }

    /**
     * Store a base64 data URL (e.g. canvas signature) and return its relative path.
     */
    public static function storeDataUrl(string $dataUrl, string $type, int $unitId, ?string $personName = null): ?string
    {
        $type = self::normalizeType($type);
        if (! preg_match('/^data:(image\/(?:png|jpeg|jpg|webp));base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $m)) {
            return null;
        }

        $mime = strtolower($m[1]);
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            return null;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $directory = self::BASE_DIRECTORY.'/'.$type.'/'.$unitId;
        $code = $type === 'selfie' ? 'GB-FOTO' : 'GB-TTD';
        $filename = UploadFilename::dated([
            $code,
            UploadFilename::short($personName ?? 'TAMU', 12),
        ], $extension);
        $path = $directory.'/'.$filename;

        $stored = Storage::disk(self::DISK)->put($path, $binary);
        if ($stored === false) {
            throw new \RuntimeException('Gagal menyimpan berkas.');
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return;
        }
        if (! str_starts_with($path, self::BASE_DIRECTORY.'/')) {
            return;
        }
        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['selfie', 'signature'], true)) {
            throw new \InvalidArgumentException('Jenis berkas tidak valid.');
        }
        return $type;
    }
}
