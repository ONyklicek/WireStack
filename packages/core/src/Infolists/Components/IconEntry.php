<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateIcon;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Icon entry — renders an icon derived from the state.
 *
 * Three modes:
 *  - `boolean()`: truthy → check icon (success), falsy → x icon (danger).
 *  - `icons([...])`: map of state value → icon name (and `colors([...])` for hue).
 *  - default: a static `icon()` decoration.
 */
class IconEntry extends Entry
{
    // colors()/colorUsing()/getColorForState() come from InteractsWithStateColor;
    // icons()/iconUsing()/getIconForState() from InteractsWithStateIcon.
    use InteractsWithStateColor;
    use InteractsWithStateIcon;

    protected bool $boolean = false;

    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    public function boolean(bool $condition = true): static
    {
        $this->boolean = $condition;

        return $this;
    }

    public function trueIcon(string $icon): static
    {
        $this->trueIcon = $icon;

        return $this;
    }

    public function falseIcon(string $icon): static
    {
        $this->falseIcon = $icon;

        return $this;
    }

    public function trueColor(string $color): static
    {
        $this->trueColor = $color;

        return $this;
    }

    public function falseColor(string $color): static
    {
        $this->falseColor = $color;

        return $this;
    }

    public function getResolvedIcon(): ?string
    {
        return $this->getIconForState($this->getState());
    }

    /** boolean() mode answers from the truthiness of the state, before any map. */
    protected function resolveStateIconOverride(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueIcon : $this->falseIcon;
    }

    /**
     * An entry's map may be a Closure resolved against the state and record.
     *
     * @return array<array-key, string|Icon>|null
     */
    protected function resolveStateIconMap(): ?array
    {
        $map = $this->stateIconMap instanceof Closure
            ? $this->evaluateForState($this->stateIconMap)
            : $this->stateIconMap;

        return is_array($map) ? $map : null;
    }

    public function getResolvedColor(): ?string
    {
        return $this->getColorForState($this->getState());
    }

    /** boolean() mode answers from the truthiness of the state, before any map. */
    protected function resolveStateColorOverride(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueColor : $this->falseColor;
    }

    /**
     * An entry's map may be a Closure resolved against the state and record.
     *
     * @return array<array-key, string|Color>|null
     */
    protected function resolveStateColorMap(): ?array
    {
        $map = $this->stateColorMap instanceof Closure
            ? $this->evaluateForState($this->stateColorMap)
            : $this->stateColorMap;

        return is_array($map) ? $map : null;
    }

    /**
     * An entry has no neutral floor: a state that maps to nothing falls back to
     * the entry's own color(), which may legitimately be unset.
     */
    protected function getDefaultStateColor(): ?string
    {
        return $this->getColor();
    }

    public function getIconColorClass(): string
    {
        return self::getTextColorClasses($this->getResolvedColor() ?? Color::Gray->value);
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.icon';
    }
}
