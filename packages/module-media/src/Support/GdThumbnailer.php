<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use GdImage;
use NyonCode\WireModuleMedia\Contracts\MakesThumbnails;

/**
 * Thumbnails through GD — the one image library most PHP builds already have.
 *
 * Bound by default so a fresh installation gets thumbnails without installing
 * anything, and replaceable in one line by an application that would rather use
 * Imagick or a CDN. Everything it does is bounded by the same rule: a thumbnail
 * is a convenience, so nothing here fails an upload.
 *
 * **SVG is deliberately absent from what it supports.** It is an image and a
 * browser scales it perfectly on its own, so a raster copy of one is strictly
 * worse than the file it came from. The same goes for a GIF, where a resize
 * would drop the animation — the original is scaled by the browser instead.
 */
final class GdThumbnailer implements MakesThumbnails
{
    /** What GD can read *and* this can write back, without losing what matters. */
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function supports(string $mimeType): bool
    {
        return extension_loaded('gd') && in_array(strtolower($mimeType), self::SUPPORTED, true);
    }

    public function make(string $source, string $destination, int $max): bool
    {
        if (! is_file($source) || $max < 1) {
            return false;
        }

        $image = $this->read($source);

        if (! $image instanceof GdImage) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height));

        // Already small enough. Copied rather than scaled, because enlarging
        // produces a bigger file that looks worse — both halves of the trade
        // going the wrong way.
        $target = $scale >= 1
            ? $image
            : $this->resample($image, (int) max(1, round($width * $scale)), (int) max(1, round($height * $scale)));

        $written = $this->write($target, $destination);

        imagedestroy($image);

        if ($target !== $image) {
            imagedestroy($target);
        }

        return $written;
    }

    private function read(string $source): ?GdImage
    {
        // Suppressed on purpose: GD reports a file it cannot read by warning and
        // returning false, and a warning on an upload is not this library's to
        // raise — the caller records "no thumbnail" and carries on.
        $image = @imagecreatefromstring((string) @file_get_contents($source));

        return $image instanceof GdImage ? $image : null;
    }

    private function resample(GdImage $image, int $width, int $height): GdImage
    {
        $target = imagecreatetruecolor($width, $height);

        // Transparency survives the copy. Without these two calls a PNG with a
        // transparent background comes back with a black one, which is the
        // single most visible way a thumbnail can be wrong.
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $target;
    }

    /**
     * Always WebP, whatever came in.
     *
     * One format for every thumbnail is one thing for a view to reason about,
     * and it is the smallest of the three at the quality a 400-pixel preview
     * needs. The original keeps its own format and its own URL — this replaces
     * nothing.
     */
    private function write(GdImage $image, string $destination): bool
    {
        $directory = dirname($destination);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        return @imagewebp($image, $destination, 82) && is_file($destination);
    }
}
