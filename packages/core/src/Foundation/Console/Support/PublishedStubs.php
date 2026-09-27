<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Console\Support;

/**
 * Where a generator's template comes from: the application's copy when it
 * published one, the package's otherwise.
 *
 * `stubs/{package}/` first — where `vendor:publish --tag={package}::stubs`
 * puts it, the toolkit namespacing published stubs by package so two packages
 * shipping a `dashboard.stub` cannot overwrite each other — then `stubs/`,
 * Laravel's own `stub:publish` convention and where a file put there by hand
 * belongs.
 *
 * One rule for every generator in every package. It was written three times
 * before it was written here, and the first copy looked in `stubs/` alone for
 * as long as it existed: publishing a stub and editing it changed nothing, and
 * nothing said so.
 */
final class PublishedStubs
{
    /**
     * @param  string  $package  The package's short name, e.g. `wire-table` — the published folder.
     * @param  string  $directory  The package's own `stubs/` directory, the fallback.
     */
    public function __construct(
        private readonly string $package,
        private readonly string $directory,
    ) {}

    /** The path of the named stub, published copy first. */
    public function path(string $file): string
    {
        foreach ([base_path('stubs/'.$this->package.'/'.$file), base_path('stubs/'.$file)] as $published) {
            if (file_exists($published)) {
                return $published;
            }
        }

        return rtrim($this->directory, '/').'/'.$file;
    }
}
