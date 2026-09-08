<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\View;

use Illuminate\View\Component;
use NyonCode\WireCore\Actions\Concerns\InteractsWithHalt;

/**
 * Renders a halted component's modal — the question it stopped to ask.
 *
 * Usage in a Livewire component view:
 *
 *   <x-wire-actions::halt-host :component="$this" />
 *
 * Only needed by a component that raises halts *without* the action runtime
 * ({@see InteractsWithHalt}). A host already
 * rendering `<x-wire-actions::modal-host>` draws the halt with it — that view
 * includes this one, so there is a single owner for how a halt looks.
 *
 * The state path and the two Livewire methods are configurable because a host
 * may keep its halt somewhere other than the default bag; wire-table does.
 */
class HaltHostComponent extends Component
{
    public function __construct(
        public mixed $component = null,
        public string $showModel = 'mountedHalt.show',
        public string $submitAction = 'submitHaltModal',
        public string $closeAction = 'closeHaltModal',
    ) {}

    public function render(): string
    {
        return 'wire-core::actions.halt-host';
    }
}
