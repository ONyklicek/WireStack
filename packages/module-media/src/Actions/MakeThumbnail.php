<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NyonCode\WireModuleMedia\Contracts\MakesThumbnails;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Give one file a scaled copy, if it can have one.
 *
 * Separate from {@see StoreUpload} because it is needed twice: once when a file
 * arrives, and once for every file that arrived before thumbnails existed —
 * which is what `php artisan wire-module-media:thumbnails` walks.
 *
 * **It never fails the thing that called it.** Every reason a thumbnail cannot
 * be made is a normal answer here: thumbnails switched off, a file with no
 * pixels, a server built without GD, a disk with no local file to read. Each
 * returns false and leaves `thumb_path` null, and every view falls back to the
 * original. A library that refused an upload because it could not make a smaller
 * copy of it would be a worse library.
 */
final readonly class MakeThumbnail
{
    public function __construct(private MakesThumbnails $thumbnailer) {}

    /**
     * @param  bool  $force  Remake one that already exists — what the backfill
     *                       command passes when the configured width has changed.
     */
    public function __invoke(Media $media, bool $force = false, ?string $only = null): bool
    {
        if (! config('wire-module-media.thumbnails.enabled', true)) {
            return false;
        }

        if (! $force && $media->thumb_path !== null && $only === null) {
            return false;
        }

        if (! $this->thumbnailer->supports((string) $media->mime_type)) {
            return false;
        }

        $storage = Storage::disk((string) $media->disk);
        $source = $storage->path((string) $media->path);

        // Not a local disk, or a file that is not there. Reading it back from S3
        // to make a preview is a download per upload, and a convenience that
        // costs that is not one.
        if (! is_file($source)) {
            return false;
        }

        $sizes = self::sizes();

        // `--size=preview` on the backfill: fill in one name that was added to
        // the config later, without remaking the ones that already exist.
        if ($only !== null) {
            $sizes = array_intersect_key($sizes, [$only => true]);
        }

        $existing = $media->thumb_variants ?? [];
        $made = [];

        foreach ($sizes as $name => $max) {
            if (! $force && isset($existing[$name])) {
                $made[$name] = $existing[$name];

                continue;
            }

            $relative = self::pathFor($media, $name);

            if ($this->thumbnailer->make($source, $storage->path($relative), $max)) {
                $made[$name] = $relative;
            }
        }

        if ($made === []) {
            return false;
        }

        $variants = [...$existing, ...$made];

        // Written last, and only on success: a row pointing at a thumbnail that
        // was never made is a broken image in every grid, which is worse than
        // the full-size original this was meant to avoid.
        //
        // `thumb_path` keeps holding the tile. Every view that predates variants
        // reads it, and a library that upgrades has to render before it has
        // remade anything.
        $media->update([
            'thumb_path' => $variants['tile'] ?? $media->thumb_path ?? reset($variants),
            'thumb_variants' => $variants,
            'placeholder' => $media->placeholder !== null && ! $force
                ? $media->placeholder
                : self::averageColour($storage->path(reset($variants))),
        ]);

        return true;
    }

    /**
     * The sizes to make, by name.
     *
     * A config published before variants existed names one `width`, and the
     * honest reading of it is "this is the tile" — so it becomes exactly that
     * rather than turning an upgrade into a library with no thumbnails.
     *
     * @return array<string, int>
     */
    public static function sizes(): array
    {
        $configured = config('wire-module-media.thumbnails.sizes');
        $sizes = is_array($configured) ? $configured : [];

        // `width` names the tile and always has. Keeping that knob meaningful is
        // what makes this an addition rather than a rename: a published config
        // or an env var that sets it goes on doing exactly what it did.
        $sizes['tile'] = config('wire-module-media.thumbnails.width', 400);

        $sizes = array_map(intval(...), array_filter($sizes, static fn ($max): bool => (int) $max > 0));

        // Ascending, so "the next size up" is a fact about the array rather than
        // about the order somebody happened to write the config in — which is
        // what `Media::srcset()` reads.
        asort($sizes);

        return $sizes;
    }

    /** Where one named copy of one file lives, on the same disk as the original. */
    public static function pathFor(Media $media, string $size): string
    {
        $directory = trim((string) config('wire-module-media.thumbnails.directory', 'thumbnails'), '/');
        $base = Str::beforeLast(basename((string) $media->path), '.');

        // The tile keeps the name it has always had, so an upgrade does not
        // orphan every thumbnail already on the disk.
        return $size === 'tile'
            ? $directory.'/'.$base.'.webp'
            : $directory.'/'.$base.'-'.$size.'.webp';
    }

    /**
     * The picture's average colour, as `#rrggbb`.
     *
     * A one-pixel downscale of a copy that has just been written — free where
     * the resize is already happening, and enough for a grid to have its shape
     * and its rough colours before a single image has arrived. A blurhash would
     * be prettier and is a dependency; this is one GD call.
     *
     * Null on a server without GD, exactly like everything else here: a
     * thumbnail is a convenience and nothing about it may fail an upload.
     */
    private static function averageColour(string $absolute): ?string
    {
        if (! extension_loaded('gd') || ! is_file($absolute)) {
            return null;
        }

        $image = @imagecreatefromstring((string) @file_get_contents($absolute));

        if ($image === false) {
            return null;
        }

        $pixel = imagecreatetruecolor(1, 1);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $rgb = imagecolorat($pixel, 0, 0);

        imagedestroy($image);
        imagedestroy($pixel);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
