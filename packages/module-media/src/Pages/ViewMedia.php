<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Pages;

use NyonCode\WireModuleMedia\Resources\MediaResource;
use NyonCode\WirePanels\Resources\Concerns\InteractsWithRecordTitle;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * One file, headed by its own name.
 *
 * The library's grid has a details panel of its own, and this is the page you
 * land on from a link — so it shows the same facts at a size you can read them
 * at, plus the two the panel could not fit: where the file is filed, and what
 * disk and path it actually lives on.
 */
class ViewMedia extends ViewPage
{
    use InteractsWithRecordTitle;

    protected function recordTitleAttribute(): string
    {
        return 'name';
    }

    protected static ?string $resource = MediaResource::class;
}
