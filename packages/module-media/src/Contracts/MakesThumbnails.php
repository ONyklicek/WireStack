<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Contracts;

/**
 * Something that can produce a smaller copy of an image.
 *
 * A contract rather than a class the library calls directly, for two reasons
 * that are the same reason: the ability depends on what the *server* has, and on
 * what the application would rather use. GD ships with most PHP builds and is
 * what this package binds by default; an application that already has Imagick,
 * Intervention, or a resizing CDN swaps the binding and everything else here is
 * unchanged.
 *
 * **Nothing that implements this may throw for a file it cannot handle.** A
 * thumbnail is a convenience: an upload that fails because a PDF has no pixels,
 * or because the server was built without GD, is a worse library than one that
 * shows the original scaled down in a grid. `supports()` is asked first and
 * `make()` answers null rather than raising.
 */
interface MakesThumbnails
{
    /**
     * Whether this can make a thumbnail of that kind of file at all.
     *
     * Asked before any work happens, so a caller can record "no thumbnail" as a
     * fact rather than discovering it by failing.
     */
    public function supports(string $mimeType): bool;

    /**
     * Write a scaled copy of `$source` to `$destination`.
     *
     * `$max` is the longest edge in pixels; the aspect ratio is kept, and an
     * image already smaller than that is left at its own size rather than
     * scaled up — enlarging a small image produces a bigger file that looks
     * worse, which is both halves of the trade going the wrong way.
     *
     * @return bool Whether a file now exists at `$destination`.
     */
    public function make(string $source, string $destination, int $max): bool;
}
