<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireTable\Columns\Column;

/**
 * A column whose value is a number to be compared down the column.
 *
 * Three defaults, shared by every such column so they cannot drift apart:
 *
 * - **Right-aligned.** Figures are read against a fixed right edge. The
 *   alignment carries a second meaning here — `Support\MobileCard` derives the
 *   stacked card's metric slot from the last right-aligned column — so a figure
 *   column becomes the number a phone shows on the card without being told.
 * - **Tabular digits.** Proportional digits give `1` and `7` different widths and
 *   the decimal points wander even under a right edge. Same vocabulary the
 *   summary footers and the mobile card already use.
 * - **No wrapping.** A number broken across two lines stops being one number.
 *
 * Defaults, not rules: `->alignment('left')` still wins, and the text classes are
 * appended to the canonical ones rather than replacing them, so size, weight,
 * colour and font family are untouched.
 *
 * @phpstan-require-extends Column
 */
trait RendersAsFigure
{
    /**
     * Apply the figure defaults. Called from the constructor of a column that is
     * a figure; a caller may override any of them afterwards.
     */
    protected function readAsFigure(): void
    {
        $this->alignment(Alignment::Right);
    }

    public function getTextClasses(): string
    {
        return trim(parent::getTextClasses().' tabular-nums whitespace-nowrap');
    }
}
