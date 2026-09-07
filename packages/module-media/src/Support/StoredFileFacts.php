<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use NyonCode\WireModuleMedia\Actions\StoreUpload;

/**
 * What a stored file actually is, read from the disk.
 *
 * The module's oldest rule — *what a browser says a file is, is a claim; what
 * the stored file is, is a fact* — used to live inside {@see StoreUpload}
 * as two private methods. Replacing an original has to establish exactly the
 * same facts about exactly the same kind of file, and a second copy of "hash it,
 * measure it, suppress the warning a PDF raises" would be the copy that drifts.
 *
 * Both answers are allowed to be null, and null is ordinary: a remote disk has
 * no local file to hash, and a PDF has no pixels to measure.
 */
final class StoredFileFacts
{
    /**
     * The sha-256 of the stored bytes, on a disk with real files behind it.
     *
     * Null on a disk that is not local — S3 among them — rather than pulling the
     * file back down to hash it. Duplicate detection is a convenience, and a
     * convenience that downloads every upload twice is not one.
     */
    public static function checksum(string $absolute): ?string
    {
        if (! is_file($absolute)) {
            return null;
        }

        $hash = @hash_file('sha256', $absolute);

        return is_string($hash) ? $hash : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public static function dimensions(string $absolute): array
    {
        if (! is_file($absolute)) {
            return [null, null];
        }

        // Suppressed on purpose: this is asked of every stored file, and a PDF
        // is not an image — which getimagesize() reports by warning rather than
        // by answering.
        $size = @getimagesize($absolute);

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }
}
