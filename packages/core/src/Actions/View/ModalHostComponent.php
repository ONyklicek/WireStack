<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\View;

use Illuminate\View\Component;

/**
 * Renders the currently mounted action's modal / slide-over / wizard /
 * confirmation dialog (plus its form or infolist body and footer actions) for a
 * WithActions host.
 *
 * Usage in a Livewire component view:
 *
 *   <x-wire-actions::modal-host :component="$this" />
 *
 * The state path and the submit/close Livewire methods are configurable so the
 * same view can back a standalone WithActions host (the defaults) or, later, a
 * wire-table host that delegates to it.
 */
class ModalHostComponent extends Component
{
    public function __construct(
        public mixed $component = null,
        public string $showModel = 'mountedAction.show',
        public string $submitAction = 'callMountedAction',
        public string $closeAction = 'unmountAction',
        public string $nextStepAction = 'nextActionModalStep',
        public string $prevStepAction = 'prevActionModalStep',
    ) {}

    public function render(): string
    {
        return 'wire-core::actions.modal-host';
    }
}
