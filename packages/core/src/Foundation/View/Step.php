<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single step inside <x-wire::wizard>. Registers its label with the parent and
 * shows only when it is the current step.
 */
class Step extends Component
{
    public function __construct(public string $label = '') {}

    public function render(): View
    {
        return view('wire-core::foundation.step');
    }
}
