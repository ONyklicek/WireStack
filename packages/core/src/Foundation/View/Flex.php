<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Schema\Flex as SchemaFlex;

/**
 * Standalone flex layout: <x-wire::flex from="md" justify="between">…</x-wire::flex>.
 *
 * Slot-based counterpart to Foundation\Schema\Flex; class computation is reused
 * from the schema owner so both surfaces stay in sync.
 */
class Flex extends Component
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
        $flex = SchemaFlex::make()->from($from)->gap((int) $gap)->wrap($wrap)->grow($grow);

        if ($justify !== null) {
            $flex->justify($justify);
        }

        if ($align !== null) {
            $flex->align($align);
        }

        $this->rowClass = $flex->getRowClass();
        $this->gapClass = $flex->getGapClass();
        $this->justifyClass = $flex->getJustifyClass();
        $this->alignClass = $flex->getAlignClass();
        $this->wrap = $flex->isWrap();
        $this->grow = $flex->isGrow();
    }

    public function render(): View
    {
        return view('wire-core::foundation.flex');
    }
}
