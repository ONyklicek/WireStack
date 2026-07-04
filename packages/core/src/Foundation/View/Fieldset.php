<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Standalone fieldset: <x-wire::fieldset legend="…">…</x-wire::fieldset>.
 */
class Fieldset extends Component
{
    public function __construct(public ?string $legend = null) {}

    public function render(): View
    {
        return view('wire-core::foundation.fieldset');
    }
}
