<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Layout;

use NyonCode\WireCore\Foundation\Schema\Grid as BaseGrid;

/**
 * Form grid layout.
 *
 * Thin subclass of the canonical {@see BaseGrid} schema layout; it only swaps
 * the Blade view so form grids keep their form-specific markup. Shared column
 * configuration lives in the core schema layout and is reused by infolists.
 *
 * @deprecated v2.0 Use {@see BaseGrid} directly.
 */
class Grid extends BaseGrid
{
    protected function viewName(): string
    {
        return 'wire-forms::layouts.grid';
    }
}
