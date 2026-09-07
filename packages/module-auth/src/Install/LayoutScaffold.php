<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Install;

use NyonCode\WireModuleAuth\Exceptions\AuthFrameException;

/**
 * The one file `composer require` cannot write for an application.
 *
 * The screens render inside a frame, and the shell has one — but the shell's
 * frame takes the stylesheet from a `head` slot, because a package cannot know
 * an application's Vite entry names and this repository has refused to guess
 * them since ADR 0028. Nothing was filling that slot, so a login page loaded the
 * framework's markup and **none of the application's styles**: it renders, it
 * has no error, and it looks like a broken package.
 *
 * So the installer writes the same shape `wire-admin:install` already writes for
 * the signed-in layout — an application-owned view that names the package's
 * frame and fills its slots — and `Frame` looks for it first.
 *
 * Idempotent and never overwriting: an application that ran the installer,
 * edited the file and ran it again keeps its edits.
 */
final readonly class LayoutScaffold
{
    /**
     * Where the view goes, and what a component tag would call it.
     *
     * A Blade *component*, not a view name: `resources/views/components/` is
     * what makes `layouts.auth` addressable, and `Frame` hands the component
     * name to `<x-dynamic-component>`. The pair is here so the two cannot drift.
     */
    public const string PATH = 'resources/views/components/layouts/auth.blade.php';

    public const string COMPONENT = 'layouts.auth';

    public function __construct(private string $basePath) {}

    /**
     * Write it, unless the application wrote one already.
     *
     * @return bool True when this run created the file.
     *
     * @throws AuthFrameException When the directory is not there and cannot be made.
     */
    public function write(): bool
    {
        $target = $this->basePath.'/'.self::PATH;

        if (is_file($target)) {
            return false;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw AuthFrameException::layoutNotWritable($directory);
        }

        file_put_contents($target, (string) file_get_contents(__DIR__.'/../../stubs/auth-layout.blade.stub'));

        return true;
    }
}
