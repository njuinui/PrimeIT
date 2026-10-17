<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class SignedFrontendUrl
{
    /**
     * Build a URL pointing at the React app, signed with APP_KEY
     * (same HMAC-SHA256 scheme Laravel uses for signed routes).
     */
    public static function create(string $path, array $params, int $ttlMinutes = 60): string
    {
        $params['expires'] = Carbon::now()->addMinutes($ttlMinutes)->getTimestamp();

        $url = rtrim((string) config('app.frontend_url'), '/').$path.'?'.http_build_query($params);

        return $url.'&signature='.self::signature($url);
    }

    /**
     * Validate a signature supplied by the frontend against the same URL.
     */
    public static function matches(string $path, array $params, string $signature): bool
    {
        $url = rtrim((string) config('app.frontend_url'), '/').$path.'?'.http_build_query($params);

        return hash_equals(self::signature($url), $signature);
    }

    private static function signature(string $url): string
    {
        $key = (string) config('app.key');

        abort_if($key === '', 500, 'APP_KEY is not configured.');

        return hash_hmac('sha256', $url, $key);
    }
}