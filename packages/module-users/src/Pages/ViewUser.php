<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WirePanels\Resources\Concerns\InteractsWithRecordTitle;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/** One user, read-only, headed by their own name. */
class ViewUser extends ViewPage
{
    use InteractsWithRecordTitle;

    protected static ?string $resource = UserResource::class;

    /** The column this application keeps a person's name in, not the word "name". */
    protected function recordTitleAttribute(): string
    {
        return UserResource::field('name');
    }
}
