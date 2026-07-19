<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateIcon;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;

class IconColumn extends Column
{
    // colors()/colorUsing()/getColorForState() come from InteractsWithStateColor;
    // icons()/iconUsing()/getIconForState() from InteractsWithStateIcon.
    use InteractsWithStateColor;
    use InteractsWithStateIcon;

    protected string $iconSize = 'md';

    protected bool $boolean = false;

    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    /** Set the icon size (e.g. "sm", "md", "lg"). */
    public function iconSize(string $size): static
    {
        $this->iconSize = $size;

        return $this;
    }

    /** Derive the icon from the value's truthiness (check vs. x). */
    public function boolean(string|Icon $trueIcon = 'check-circle', string|Icon $falseIcon = 'x-circle'): static
    {
        $this->boolean = true;
        $this->trueIcon = $trueIcon instanceof Icon ? $trueIcon->value() : $trueIcon;
        $this->falseIcon = $falseIcon instanceof Icon ? $falseIcon->value() : $falseIcon;

        return $this;
    }

    /** Set the true/false colors used in boolean() mode. */
    public function booleanColors(string|Color $trueColor = 'success', string|Color $falseColor = 'danger'): static
    {
        $this->trueColor = $trueColor instanceof Color ? $trueColor->value : $trueColor;
        $this->falseColor = $falseColor instanceof Color ? $falseColor->value : $falseColor;

        return $this;
    }

    /** Set the icon shown for a truthy value in boolean() mode. */
    public function trueIcon(string|Icon|null $icon): static
    {
        $this->trueIcon = $icon instanceof Icon ? $icon->value() : ($icon ?? 'check-circle');

        return $this;
    }

    /** Set the icon shown for a falsy value in boolean() mode. */
    public function falseIcon(string|Icon $icon): static
    {
        $this->falseIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Set the color used for a truthy value in boolean() mode. */
    public function trueColor(string|Color $color): static
    {
        $this->trueColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the color used for a falsy value in boolean() mode. */
    public function falseColor(string|Color $color): static
    {
        $this->falseColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);
        $icon = $this->getIconForState($state);
        $color = $this->getColorForState($state);

        if (! $icon) {
            return $this->getEmptyCellText();
        }

        // §7: markup is a function of the (low-cardinality) state — memoise the view
        // render by its data so rows sharing a state reuse one render.
        return $this->renderViewCached('tables.columns.icon', [
            'colorClass' => $this->resolveColorClass($color ?? 'gray'),
            'iconHtml' => app(IconManager::class)->render($icon, $this->getSizeClass()),
        ]);
    }

    /** boolean() mode answers from the truthiness of the state, before any map. */
    protected function resolveStateIconOverride(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueIcon : $this->falseIcon;
    }

    /** boolean() mode answers from the truthiness of the state, before any map. */
    protected function resolveStateColorOverride(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueColor : $this->falseColor;
    }

    public function getSizeClass(): string
    {
        return HasSize::getIconSizeClasses($this->iconSize);
    }

    protected function resolveColorClass(string $color): string
    {
        return self::getTextColorClasses($color);
    }
}
