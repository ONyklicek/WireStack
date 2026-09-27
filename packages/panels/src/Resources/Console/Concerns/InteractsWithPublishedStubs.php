<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Concerns;

use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;

/**
 * Where a wire-panels generator's template comes from — wire-core's
 * {@see PublishedStubs} rule, pointed at this package's `stubs/`, so a
 * published template is honoured by every generator or by none.
 */
trait InteractsWithPublishedStubs
{
    /** The path of the named stub, published copy first. */
    protected function publishedStubPath(string $file): string
    {
        return (new PublishedStubs('wire-panels', dirname(__DIR__, 4).'/stubs'))->path($file);
    }
}
