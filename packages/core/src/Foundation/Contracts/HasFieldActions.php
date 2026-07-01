<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use NyonCode\WireCore\Actions\Action;

/**
 * Marks a form component that can expose interactive {@see Action} buttons —
 * prefix/suffix/hint affix actions on inputs, or a standalone Button field.
 *
 * The host Livewire component resolves the field by its state path and calls
 * {@see getFieldAction()} to locate the triggered action, then invokes the
 * action's callback with the field's reactive `$get` / `$set` accessors. This is
 * how field-level actions read and write sibling state without a model context.
 */
interface HasFieldActions
{
    /**
     * Resolve a field-scoped action by name, or null when none matches.
     */
    public function getFieldAction(string $name): ?Action;
}
