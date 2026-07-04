<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Schema\Split as SchemaSplit;

/**
 * Standalone flex/split layout: <x-wire::split from="md" justify="between">…</x-wire::split>.
 *
 * Slot-based counterpart to Foundation\Schema\Split; class computation is reused
 * from the schema owner so both surfaces stay in sync.
 */
class Split extends Component
{
    public string $rowClass;

    public string $gapClass;

    public string $justifyClass;

    public string $alignClass;

    public bool $wrap;

    public bool $grow;

    public function __construct(
        string $from = 'md',
        ?string $justify = null,
        ?string $align = null,
        int|string $gap = 4,
        bool $wrap = false,
        bool $grow = true,
    ) {
        $split = SchemaSplit::make()->from($from)->gap((int) $gap)->wrap($wrap)->grow($grow);

        if ($justify !== null) {
            $split->justify($justify);
        }

        if ($align !== null) {
            $split->align($align);
        }

        $this->rowClass = $split->getRowClass();
        $this->gapClass = $split->getGapClass();
        $this->justifyClass = $split->getJustifyClass();
        $this->alignClass = $split->getAlignClass();
        $this->wrap = $split->isWrap();
        $this->grow = $split->isGrow();
    }

    public function render(): View
    {
        return view('wire-core::foundation.split');
    }
}
