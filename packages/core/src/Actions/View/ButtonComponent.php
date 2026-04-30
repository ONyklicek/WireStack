<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\View;

use Illuminate\View\Component;

/**
 * Generic action button Blade component.
 *
 * Renders a button or link with action styling. The consumer (table, forms)
 * provides the wire:click handler via the `wireClick` prop.
 *
 * Usage:
 *   <x-wire-actions::button
 *       :action="$action"
 *       :record="$record"
 *       wire-click="executeTableAction('{{ $recordKey }}', '{{ $actionName }}')"
 *   />
 */
class ButtonComponent extends Component
{
    public function __construct(
        public object $action,
        public mixed $record = null,
        public ?string $wireClick = null,
        public ?string $wireClickModifiers = null,
    ) {}

    public function render(): string
    {
        return 'wire-core::actions.button';
    }
}
