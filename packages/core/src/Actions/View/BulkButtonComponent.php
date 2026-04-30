<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\View;

use Illuminate\View\Component;

/**
 * Generic bulk action button Blade component.
 *
 * Renders a BulkAction as a button. The consumer provides the wire:click handler.
 *
 * Usage:
 *   <x-wire-actions::bulk-button
 *       :action="$action"
 *       wire-click="executeBulkAction('{{ $action->getName() }}')"
 *   />
 */
class BulkButtonComponent extends Component
{
    public function __construct(
        public object $action,
        public ?string $wireClick = null,
        public ?string $wireClickModifiers = null,
    ) {}

    public function render(): string
    {
        return 'wire-core::actions.bulk-button';
    }
}
