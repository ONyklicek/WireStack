<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A single panel inside <x-wire::tabs>. Registers its label with the parent and
 * shows only when active.
 */
class Tab extends Component
{
    public function __construct(public string $label = '') {}

    public function render(): View
    {
        return view('wire-core::foundation.tab');
    }
}
