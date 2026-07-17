<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Components;

use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Checkbox entry — an inline checkbox that writes a boolean directly to the
 * record. Shares the optimistic write path with {@see ToggleEntry}; use it where
 * a checkbox reads more naturally than a switch.
 */
class CheckboxEntry extends EditableEntry
{
    protected string $editableType = 'checkbox';

    public function formatForSave(mixed $value): mixed
    {
        return (bool) $value;
    }

    /**
     * Accent (checked) color class from the canonical palette, defaulting to the
     * primary hue when no color is set.
     */
    public function getAccentColorClass(): string
    {
        return HasColor::getTextColorClasses($this->getColor() ?? 'primary');
    }

    protected function viewName(): string
    {
        return 'wire-core::panels.entries.checkbox';
    }
}
