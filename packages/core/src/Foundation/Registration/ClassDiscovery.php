<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Registration;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

/**
 * The classes of one kind under one directory, found by walking it.
 *
 * What lets an application say "every resource in `app/Resources`" instead of
 * listing them — `config('wire-core.discover')`, read at boot. The directory is
 * mapped to its namespace the way PSR-4 maps it, a file becomes a class name,
 * and a class is kept only when it autoloads, is concrete and is the kind asked
 * for. Anything else under the directory — a trait, an abstract base, a helper —
 * is passed over rather than reported, because a folder of resources is allowed
 * to hold the things resources are built from.
 *
 * It runs on every boot where it is configured, so it is one directory listing
 * and one `class_exists()` per file: cheap for a folder of resources, and the
 * reason it stays opt-in rather than scanning the whole application.
 */
final class ClassDiscovery
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @template T of object
     *
     * @param  string  $namespace  The namespace `$directory` holds, e.g. `App\Resources`.
     * @param  class-string<T>  $kind  What a class must extend or implement to be found.
     * @return array<int, class-string<T>> In file-path order, so a registration is stable.
     */
    public function in(string $directory, string $namespace, string $kind): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $namespace = trim($namespace, '\\');
        $found = [];

        $files = $this->files->allFiles($directory);
        usort($files, fn ($a, $b): int => strcmp($a->getRelativePathname(), $b->getRelativePathname()));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $namespace.'\\'.str_replace('/', '\\', substr($file->getRelativePathname(), 0, -4));

            if (class_exists($class) && is_a($class, $kind, true) && ! (new ReflectionClass($class))->isAbstract()) {
                $found[] = $class;
            }
        }

        return $found;
    }
}
