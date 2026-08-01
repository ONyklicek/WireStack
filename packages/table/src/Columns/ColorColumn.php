<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\CssColor;

/**
 * Color column — renders the state as a color swatch plus its value.
 *
 * The table-side counterpart of the infolist `ColorEntry`: the state is a CSS
 * color string (hex, rgb, hsl, keyword) stored on the record, not a palette
 * name. Palette-driven coloring belongs on BadgeColumn/IconColumn instead.
 */
class ColorColumn extends Column
{
    protected bool $swatchOnly = false;

    /** Show only the swatch, hiding the literal color value next to it. */
    public function swatchOnly(bool $condition = true): static
    {
        $this->swatchOnly = $condition;

        return $this;
    }

    public function isSwatchOnly(): bool
    {
        return $this->swatchOnly;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);
        $swatch = CssColor::sanitize($state);

        // An unparseable value has no swatch to draw. Fall back to the empty-cell
        // text rather than emitting a `style` we could not vouch for.
        if ($swatch === null) {
            return $this->getEmptyCellText();
        }

        // §7: a palette of stored colors is low-cardinality — memoise the view
        // render by its data so rows sharing a color reuse one render.
        return $this->renderViewCached('tables.columns.color', [
            'swatch' => $swatch,
            'displayValue' => $this->swatchOnly ? '' : $this->formatValue($state, $record),
            'copyable' => $this->isCopyable(),
            'copyValue' => $swatch,
            'copyMessage' => $this->getCopyMessage(),
        ]);
    }
}
