<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A step of `wire-admin:install` that could not be taken.
 *
 * Every case is about the shape of the application rather than a bad argument —
 * a directory that cannot be created, a provider list that is not there or is
 * not the file Laravel generates — so `RuntimeException` is the base, per
 * ADR 0022, and each message ends with the line the developer has to add or fix
 * by hand.
 *
 * Thrown rather than returned as a status, because the installer's hooks are
 * deliberately not wrapped in a try/catch by the toolkit: a step that cannot
 * finish stops the run instead of printing a tick beside something that did not
 * happen. The one case the command catches on purpose is the provider list,
 * where the rest of the install is still worth completing — see
 * `WireAdminServiceProvider::scaffoldApplication()`.
 */
final class AdminInstallException extends RuntimeException implements WireException
{
    public static function layoutDirectoryNotWritable(string $directory): self
    {
        return new self(
            "The admin layout could not be written: [{$directory}] does not exist and cannot be created. ".
            'Create it, or copy the layout from the package stub '.
            '(vendor/nyoncode/wire-admin/stubs/admin-layout.blade.stub) yourself.'
        );
    }

    public static function stylesheetMissing(string $file, string $line): self
    {
        return new self(
            "[{$file}] does not exist, so Tailwind was not pointed at the packages' views. ".
            "Add [{$line}] to whichever stylesheet imports Tailwind, with the path adjusted to where it lives. ".
            'Until it is there the shell renders unstyled — Tailwind skips vendor/ because .gitignore does.'
        );
    }

    public static function stylesheetNotTailwind(string $file, string $line): self
    {
        return new self(
            "[{$file}] does not import Tailwind, so [{$line}] was not written into a file that would ignore it. ".
            'Add that line to the stylesheet that does import Tailwind, below the import. '.
            'Until it is there the shell renders unstyled — Tailwind skips vendor/ because .gitignore does.'
        );
    }

    public static function providerListMissing(string $file, string $provider): self
    {
        return new self(
            "[{$file}] does not exist, so [{$provider}] could not be registered. ".
            'This is an application that keeps its providers elsewhere — add '.
            "[{$provider}::class] to that list by hand. Until it is registered, ".
            'pages render in whatever layout the application already names.'
        );
    }

    public static function providerListNotEditable(string $file, string $provider): self
    {
        return new self(
            "[{$file}] is not the list Laravel generates — it does not end in a returned array — ".
            "so [{$provider}] was not added to it rather than being written into something ".
            'this installer does not understand. Add it by hand.'
        );
    }
}
