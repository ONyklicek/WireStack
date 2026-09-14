<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireModuleUsers\Concerns\ResolvesScopedRecord;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WirePanels\Resources\Concerns\InteractsWithRecordTitle;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/** One user, read-only, headed by their own name. */
class ViewUser extends ViewPage
{
    use InteractsWithRecordTitle;
    use ResolvesScopedRecord;

    protected static ?string $resource = UserResource::class;

    /** The column this application keeps a person's name in, not the word "name". */
    protected function recordTitleAttribute(): string
    {
        return UserResource::field('name');
    }

    protected function scopeRecordQuery(Builder $query): Builder
    {
        return Teams::scopeMembers($query);
    }
}
