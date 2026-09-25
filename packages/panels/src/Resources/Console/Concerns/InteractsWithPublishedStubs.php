<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Concerns;

/**
 * Where a generator's template comes from: the application's copy when it
 * published one, this package's otherwise.
 *
 * `stubs/wire-panels/` first — where `vendor:publish --tag=wire-panels::stubs`
 * puts it, the toolkit namespacing published stubs by package so two packages
 * shipping the same file name cannot overwrite each other — then `stubs/`,
 * Laravel's own `stub:publish` convention. One rule for every wire-panels
 * generator, so a published template is honoured by all of them or by none.
 */
trait InteractsWithPublishedStubs
{
    /** The path of the named stub, published copy first. */
    protected function publishedStubPath(string $file): string
    {
        foreach ([base_path('stubs/wire-panels/'.$file), base_path('stubs/'.$file)] as $published) {
            if (file_exists($published)) {
                return $published;
            }
        }

        return dirname(__DIR__, 4).'/stubs/'.$file;
    }
}
