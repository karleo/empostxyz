<?php

namespace App\Support;

class EpglBaseUrl
{
    /**
     * Normalize a user-provided EPGL URL to the API host root.
     *
     * Accepts either:
     * - https://api-stg.epgl.ae
     * - https://api-stg.epgl.ae/api/v1/auth/authenticate
     * - https://api-stg.epgl.ae/api/v1/...
     */
    public static function normalize(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $url = rtrim($url, '/');

        // Strip full auth endpoint or any /api/v1... path down to the host root.
        if (preg_match('#^(https?://[^/]+)/api(?:/v\d+)?(?:/.*)?$#i', $url, $matches)) {
            return $matches[1];
        }

        return $url;
    }
}
