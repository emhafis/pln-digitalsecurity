<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    public static function for(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $urlPath = parse_url($url, PHP_URL_PATH);

        return is_string($urlPath) && $urlPath !== '' ? $urlPath : $url;
    }
}
