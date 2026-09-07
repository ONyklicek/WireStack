<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Raised when the signed-out screens have no layout to render inside.
 *
 * A named exception rather than Blade's own "unable to locate component",
 * because the two failures need different answers and the second one points at
 * this package's view instead of at the application's missing configuration.
 *
 * The shape of the application rather than a bad argument — no shell installed
 * and no layout named — so `RuntimeException` is the base per ADR 0022, and it
 * carries `WireException` so one `catch` reaches everything this stack throws.
 */
final class AuthFrameException extends RuntimeException implements WireException
{
    /**
     * No shell, and no layout named in its place.
     *
     * The message carries the fix rather than the diagnosis: an application
     * reaching this has one line to write, and which line it is depends on
     * whether it wants the shell's frame or one of its own.
     */
    public static function undecided(): self
    {
        return new self(
            'The signed-out screens have no layout to render inside. Install nyoncode/wire-admin '
            .'and run `php artisan wire-module-auth:install`, which writes one, or name your own '
            ."in config/wire-module-auth.php: 'layout' => 'layouts.guest'."
        );
    }

    /**
     * The installer could not write the application's own layout.
     *
     * Its own case rather than a warning, because it is the file the whole
     * feature hangs on: without it the screens render in the package's frame
     * with none of the application's styles, which reads as a broken package
     * rather than as a step that did not run.
     */
    public static function layoutNotWritable(string $directory): self
    {
        return new self(
            "The signed-out layout could not be written: [{$directory}] does not exist and cannot be "
            .'created. Create it, or copy the layout from the package stub '
            .'(vendor/nyoncode/wire-module-auth/stubs/auth-layout.blade.stub) yourself.'
        );
    }
}
