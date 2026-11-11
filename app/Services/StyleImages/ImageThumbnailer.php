<?php

namespace App\Services\StyleImages;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Produces a small JPEG preview of an uploaded image using GD. Best-effort by design:
 * any failure (corrupt file, unsupported format, not enough memory for a huge photo)
 * returns null and the caller simply serves the original — a missing thumbnail must
 * never fail an upload.
 */
class ImageThumbnailer
{
    /** Decoding allocates ~4 bytes/pixel; above this the thumbnail is skipped to stay inside memory_limit. */
    private const MAX_SOURCE_PIXELS = 36_000_000;

    /**
     * @return string|null JPEG bytes, or null if a thumbnail could not be made
     */
    public function make(string $sourcePath, string $mimeType, int $maxSide, int $quality): ?string
    {
        $image = null;
        $resized = null;

        try {
            $info = @getimagesize($sourcePath);
            if ($info === false) {
                return null;
            }

            [$width, $height] = $info;
            if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS) {
                return null;
            }

            $image = $this->decode($sourcePath, $mimeType);
            if ($image === null) {
                return null;
            }

            $image = $this->applyExifOrientation($image, $sourcePath, $mimeType);
            $width = imagesx($image);
            $height = imagesy($image);

            $scale = min(1, $maxSide / max($width, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // JPEG has no alpha — flatten transparent PNG/WebP onto white instead of black.
            imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            imagejpeg($resized, null, $quality);

            return ob_get_clean() ?: null;
        } catch (Throwable $e) {
            Log::warning('Style image thumbnail generation failed; serving original instead', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }
            if ($resized instanceof \GdImage) {
                imagedestroy($resized);
            }
        }
    }

    private function decode(string $path, string $mimeType): ?\GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /** Phone photos are often stored sideways with an EXIF flag; the re-encoded thumbnail would lose it. */
    private function applyExifOrientation(\GdImage $image, string $path, string $mimeType): \GdImage
    {
        if ($mimeType !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
