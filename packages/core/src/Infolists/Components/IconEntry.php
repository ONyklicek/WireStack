<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

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
    protected bool $boolean = false;

    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    /** @var array<int|string, string>|Closure|null */
    protected array|Closure|null $iconMap = null;

    /** @var array<int|string, string>|Closure|null */
    protected array|Closure|null $colorMap = null;

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

    /**
     * Map state values to icon names.
     *
     * @param  array<int|string, string>|Closure  $map
     */
    public function icons(array|Closure $map): static
    {
        $this->iconMap = $map;

        return $this;
    }

    /**
     * Map state values to color names.
     *
     * @param  array<int|string, string>|Closure  $map
     */
    public function colors(array|Closure $map): static
    {
        $this->colorMap = $map;

        return $this;
    }

    public function getResolvedIcon(): ?string
    {
        $state = $this->getState();

        if ($this->boolean) {
            return $state ? $this->trueIcon : $this->falseIcon;
        }

        // Enum-cast state cannot index a map directly; resolve a scalar key first.
        $key = EnumResolver::scalar($state);

        if ($this->iconMap !== null) {
            $map = $this->iconMap instanceof Closure
                ? $this->evaluateForState($this->iconMap)
                : $this->iconMap;

            if (is_array($map) && is_scalar($key) && array_key_exists($key, $map)) {
                return $map[$key];
            }
        }

        // Enum carrying its own icon via the opt-in HasIcon contract.
        $enumIcon = EnumResolver::icon($state);

        if ($enumIcon !== null) {
            return $enumIcon instanceof Icon ? $enumIcon->value() : $enumIcon;
        }

        return $this->getIcon();
    }

    public function getResolvedColor(): ?string
    {
        $state = $this->getState();

        if ($this->boolean) {
            return $state ? $this->trueColor : $this->falseColor;
        }

        // Enum-cast state cannot index a map directly; resolve a scalar key first.
        $key = EnumResolver::scalar($state);

        if ($this->colorMap !== null) {
            $map = $this->colorMap instanceof Closure
                ? $this->evaluateForState($this->colorMap)
                : $this->colorMap;

            if (is_array($map) && is_scalar($key) && array_key_exists($key, $map)) {
                return $map[$key];
            }
        }

        // Enum carrying its own color via the opt-in HasColor contract.
        $enumColor = EnumResolver::color($state);

        if ($enumColor !== null) {
            return $enumColor instanceof Color ? $enumColor->value : $enumColor;
        }

        return $this->getColor();
    }

    public function getIconColorClass(): string
    {
        return self::getTextColorClasses($this->getResolvedColor() ?? 'gray');
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.icon';
    }
}
