<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\View;

use Illuminate\View\Component;

/**
 * Generic action group (dropdown) Blade component.
 *
 * Renders an ActionGroup as a dropdown menu with Alpine.js toggle.
 * The consumer provides the record context for per-record visibility filtering.
 *
 * Usage:
 *   <x-wire-actions::group :group="$group" :record="$record" />
 */
class GroupComponent extends Component
{
    public function __construct(
        public object $group,
        public mixed $record = null,
    ) {}

    public function render(): string
    {
        return 'wire-core::actions.group';
    }
}
