<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores uploaded profile photos as small, upright JPEGs.
 *
 * Phone photos are 3-5 MB; an avatar never needs more than ~640 px. Shrinking
 * on upload makes every list, sidebar and chat bubble load fast on mobile data.
 * If GD is unavailable, the image is huge, or anything fails, the original is
 * stored untouched — an upload never fails because of optimisation.
 */
final class ImageOptimizer
{
    private const MAX_SIDE = 640;
    private const MAX_PIXELS = 25_000_000; // decoding needs ~4 bytes per pixel of memory

    /**
     * @return string|false path on the public disk, or false if storing failed
     */
    public static function storePublic(UploadedFile $file, string $directory): string|false
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $file->store($directory, 'public');
        }

        try {
            $realPath = $file->getRealPath();
            $bytes    = $realPath !== false ? file_get_contents($realPath) : false;
            $size     = $bytes !== false ? @getimagesizefromstring($bytes) : false;

            if ($bytes === false || $size === false || ($size[0] * $size[1]) > self::MAX_PIXELS) {
                return $file->store($directory, 'public');
            }

            $source = @imagecreatefromstring($bytes);

            if ($source === false) {
                return $file->store($directory, 'public');
            }

            $source = self::upright($source, $realPath, (string) $file->getMimeType());

            $width  = imagesx($source);
            $height = imagesy($source);
            $scale  = min(1, self::MAX_SIDE / max($width, $height));
            $newW   = max(1, (int) round($width * $scale));
            $newH   = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor($newW, $newH);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // flatten transparency
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 82);
            $jpeg = (string) ob_get_clean();

            if ($jpeg === '') {
                return $file->store($directory, 'public');
            }

            $path = trim($directory, '/') . '/' . Str::random(40) . '.jpg';

            return Storage::disk('public')->put($path, $jpeg) ? $path : false;
        } catch (Throwable) {
            return $file->store($directory, 'public');
        }
    }

    /**
     * Honour the camera's EXIF orientation so portraits aren't stored sideways.
     */
    private static function upright(\GdImage $image, string|false $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || $path === false || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }
}
