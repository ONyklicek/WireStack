<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WirePanels\Resources\Concerns\InteractsWithRecordTitle;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * One role, read-only.
 *
 * A role used to be edit-or-nothing, on the argument that a name and a list of
 * permission names is everything an edit form already shows. That argument only
 * holds while the list is short. A role carrying forty permissions is a
 * multi-select with forty chips inside a control sized for picking, and reading
 * it means scrolling a widget built for choosing — so the answer to "what can
 * this role actually do" is the one screen the module did not have.
 *
 * What this adds over the form is grouping: what the role *is* on top, what it
 * *grants* underneath, and wildcards apart from the permissions they cover.
 */
class ViewRole extends ViewPage
{
    use InteractsWithRecordTitle;

    protected static ?string $resource = RoleResource::class;

    protected function recordTitleAttribute(): string
    {
        return 'name';
    }
}
