<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Fill a stub and write it, never over a file that is already there unless told.
 *
 * One pass of `strtr()`, so a replacement that happens to contain another
 * placeholder is written as it is rather than substituted again.
 */
final class StubWriter
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array<string, string>  $replacements  `name` => value, written into `{{ name }}`
     * @return bool Whether the file was written — false when it existed and `$force` was not given.
     */
    public function write(string $stubPath, string $target, array $replacements, bool $force = false): bool
    {
        if (! $force && $this->files->exists($target)) {
            return false;
        }

        $pairs = [];

        foreach ($replacements as $name => $value) {
            $pairs['{{ '.$name.' }}'] = $value;
        }

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->put($target, strtr($this->files->get($stubPath), $pairs));

        return true;
    }
}
