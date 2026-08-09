<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\ActionGroup;

/**
 * An action that can render itself as a row inside a dropdown menu.
 *
 * The menu surface is its own thing, next to {@see RendersAsButton}: an
 * {@see ActionGroup} asks each of its items for a menu
 * row and never probes the concrete class. That is what lets one group hold row
 * actions and record-less ones (a header action) alike — the record is optional,
 * exactly as it is for a button.
 */
interface RendersAsMenuItem
{
    /**
     * Render this action as a single dropdown menu item.
     *
     * Returns an empty string when the viewer may not run it, so a caller can
     * concatenate without filtering.
     */
    public function renderForDropdown(?Model $record = null, ?ResolvesActionClick $click = null): string;
}
