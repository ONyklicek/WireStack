<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Components;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Toggle entry — an inline switch that writes a boolean directly to the record.
 *
 * The panel counterpart of the table's `ToggleColumn`: same on/off track colors
 * from the canonical {@see HasColor} palette, same optimistic write path via
 * `wireEditableCell`. (No import of the table class — core must not depend on
 * the table package.)
 */
class ToggleEntry extends EditableEntry
{
    protected string $editableType = 'toggle';

    protected ?string $onColor = 'primary';

    protected ?string $offColor = 'gray';

    /** Set the track color when the toggle is on (default primary). */
    public function onColor(string|Color|null $color): static
    {
        $this->onColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the track color when the toggle is off (default gray). */
    public function offColor(string|Color|null $color): static
    {
        $this->offColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function formatForSave(mixed $value): mixed
    {
        return (bool) $value;
    }

    public function getOnColorClass(): string
    {
        return HasColor::getSolidBgClass($this->onColor ?? 'primary');
    }

    public function getOffColorClass(): string
    {
        return HasColor::getSoftBgClass($this->offColor ?? 'gray');
    }

    protected function viewName(): string
    {
        return 'wire-core::panels.entries.toggle';
    }
}
