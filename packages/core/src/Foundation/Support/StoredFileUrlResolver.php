<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Resolves a stored file reference to a browser-usable URL.
 *
 * The canonical owner of the "path → URL" ladder that the table `ImageColumn`
 * and the forms `FileUpload` each used to re-encode. Named in prose, not
 * `@see`-linked, on purpose: this lives in the lowest package layer and must not
 * import its downstream consumers. Stateless and dependency-free, so it is
 * testable on its own; components reach it directly.
 *
 * The reference may already be a complete source — an inline `data:` URI or a
 * full external URL — in which case it is returned untouched. Otherwise it is a
 * disk-relative path resolved through the configured disk:
 *
 *  - a **public** file gets a plain {@see FilesystemAdapter::url()};
 *  - a **private** file gets a signed, expiring {@see FilesystemAdapter::temporaryUrl()},
 *    falling back to `url()` when the driver cannot sign one (the `local` driver
 *    throws unless served through Laravel's temporary-url route) — one
 *    un-signable file must not break the whole render.
 *
 * `FILTER_VALIDATE_URL` deliberately does **not** gate the `data:` case: it
 * rejects a `data:` URI, which would otherwise be sent down the storage path and
 * rendered as `src="/storage/data:image/..."`.
 */
final class StoredFileUrlResolver
{
    /**
     * @param  string|null  $path  the stored reference (disk-relative path, full URL, or `data:` URI)
     * @param  string|null  $disk  disk name; null uses the default filesystem disk
     * @param  string  $visibility  'public' for a plain URL, anything else for a signed temporary URL
     * @param  int  $expiryMinutes  lifetime of the signed URL when one is generated
     * @param  string|null  $default  returned when $path is empty/non-usable (e.g. a placeholder image)
     */
    public static function resolve(
        ?string $path,
        ?string $disk = null,
        string $visibility = 'public',
        int $expiryMinutes = 5,
        ?string $default = null,
    ): ?string {
        if ($path === null || $path === '') {
            return $default;
        }

        // An inline image is already a complete source.
        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        // An already-complete external URL passes through untouched.
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        /** @var FilesystemAdapter $diskInstance */
        $diskInstance = $disk === null ? Storage::disk() : Storage::disk($disk);

        if ($visibility !== 'public') {
            return self::temporaryUrl($diskInstance, $path, $expiryMinutes);
        }

        return $diskInstance->url($path);
    }

    /**
     * A signed, expiring URL for a non-public file, falling back to the plain URL
     * when the driver cannot sign one.
     */
    private static function temporaryUrl(FilesystemAdapter $disk, string $path, int $expiryMinutes): string
    {
        try {
            return $disk->temporaryUrl($path, now()->addMinutes($expiryMinutes));
        } catch (Throwable) {
            return $disk->url($path);
        }
    }
}
