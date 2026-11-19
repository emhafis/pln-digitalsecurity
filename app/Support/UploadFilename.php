<?php

namespace App\Support;

use Illuminate\Support\Str;

class UploadFilename
{
    /**
     * Build a descriptive, unique, UPPERCASE filename (keeps extension lowercased for mime friendliness).
     *
     * @param  array<int, string|int|null>  $parts
     */
    public static function make(array $parts, string $extension, int $maxBaseLength = 80): string
    {
        $segments = [];
        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }
            $value = self::sanitize((string) $part);
            if ($value !== '') {
                $segments[] = $value;
            }
        }

        $base = implode('-', $segments);
        if ($base === '') {
            $base = 'FILE';
        }
        if (strlen($base) > $maxBaseLength) {
            $base = rtrim(substr($base, 0, $maxBaseLength), '-');
        }

        $token = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
        $ext = strtolower(ltrim($extension, '.'));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext === '') {
            $ext = 'bin';
        }

        return $base.'-'.$token.'.'.$ext;
    }

    public static function sanitize(string $value): string
    {
        $value = Str::upper(Str::ascii($value));
        $value = (string) preg_replace('/[^A-Z0-9]+/', '-', $value);
        $value = trim((string) preg_replace('/-+/', '-', $value), '-');

        return $value;
    }

    public static function extensionFromUpload(\Illuminate\Http\UploadedFile $file, string $fallback = 'jpg'): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: $fallback);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return $ext;
    }
}