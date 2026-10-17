<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from the "public" disk (profile photos etc.) when the
 * public/storage symlink from `php artisan storage:link` is missing.
 * When the link exists the web server serves files directly and never gets here.
 */
class PublicFileController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $disk = Storage::disk('public');

        // Flysystem already rejects "../" traversal; this keeps the check explicit.
        abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }
}
