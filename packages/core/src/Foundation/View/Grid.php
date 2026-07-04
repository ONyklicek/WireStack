<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

/**
 * Standalone grid layout: <x-wire::grid :columns="['md' => 2, 'lg' => 3]">…</x-wire::grid>.
 *
 * Slot-based counterpart to Foundation\Schema\Grid; columns delegate to the
 * canonical ResponsiveGrid (int reflow or per-breakpoint map).
 */
class Grid extends Component
{
    public string $gridClass;

    /**
     * @param  int|string|array<string|int, int|string>  $columns
     */
    public function __construct(int|string|array $columns = 2, public string $gap = 'gap-4')
    {
        if (is_string($columns)) {
            $columns = (int) $columns;
        }

        $this->gridClass = ResponsiveGrid::cols($columns);
    }

    public function render(): View
    {
        return view('wire-core::foundation.grid');
    }
}
