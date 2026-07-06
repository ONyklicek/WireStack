<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

class IconColumn extends Column
{
    /** @var array<string, string> state → resolved icon name */
    protected array $icons = [];

    /** @var array<string, string> state → resolved color name */
    protected array $colors = [];

    protected ?Closure $iconCallback = null;

    protected ?Closure $colorCallback = null;

    protected string $iconSize = 'md';

    protected bool $boolean = false;

    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    /**
     * @param  array<string, string|Icon>  $icons
     */
    public function icons(array $icons): static
    {
        $this->icons = array_map(
            static fn (string|Icon $icon): string => $icon instanceof Icon ? $icon->value() : $icon,
            $icons,
        );

        return $this;
    }

    public function iconUsing(Closure $callback): static
    {
        $this->iconCallback = $callback;

        return $this;
    }

    /**
     * @param  array<string, string|Color>  $colors
     */
    public function colors(array $colors): static
    {
        $this->colors = array_map(
            static fn (string|Color $color): string => $color instanceof Color ? $color->value : $color,
            $colors,
        );

        return $this;
    }

    public function colorUsing(Closure $callback): static
    {
        $this->colorCallback = $callback;

        return $this;
    }

    public function iconSize(string $size): static
    {
        $this->iconSize = $size;

        return $this;
    }

    public function boolean(string|Icon $trueIcon = 'check-circle', string|Icon $falseIcon = 'x-circle'): static
    {
        $this->boolean = true;
        $this->trueIcon = $trueIcon instanceof Icon ? $trueIcon->value() : $trueIcon;
        $this->falseIcon = $falseIcon instanceof Icon ? $falseIcon->value() : $falseIcon;

        return $this;
    }

    public function booleanColors(string|Color $trueColor = 'success', string|Color $falseColor = 'danger'): static
    {
        $this->trueColor = $trueColor instanceof Color ? $trueColor->value : $trueColor;
        $this->falseColor = $falseColor instanceof Color ? $falseColor->value : $falseColor;

        return $this;
    }

    public function trueIcon(string|Icon|null $icon): static
    {
        $this->trueIcon = $icon instanceof Icon ? $icon->value() : ($icon ?? 'check-circle');

        return $this;
    }

    public function falseIcon(string|Icon $icon): static
    {
        $this->falseIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function trueColor(string|Color $color): static
    {
        $this->trueColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

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
            return $this->getPlaceholder();
        }

        return $this->renderView('tables.columns.icon', [
            'colorClass' => $this->resolveColorClass($color ?? 'gray'),
            'iconHtml' => app(IconManager::class)->render($icon, $this->getSizeClass()),
        ]);
    }

    public function getIconForState(mixed $state): ?string
    {
        if ($this->boolean) {
            return $state ? $this->trueIcon : $this->falseIcon;
        }

        if ($this->iconCallback) {
            $result = ($this->iconCallback)($state);

            return $result instanceof Icon ? $result->value() : $result;
        }

        $key = EnumResolver::scalar($state);

        if (isset($this->icons[$key])) {
            return $this->icons[$key];
        }

        // Enum carrying its own icon via the opt-in HasIcon contract.
        $enumIcon = EnumResolver::icon($state);

        if ($enumIcon !== null) {
            return $enumIcon instanceof Icon ? $enumIcon->value() : $enumIcon;
        }

        return null;
    }

    public function getColorForState(mixed $state): ?string
    {
        if ($this->boolean) {
            return $state ? $this->trueColor : $this->falseColor;
        }

        if ($this->colorCallback) {
            $result = ($this->colorCallback)($state);

            return $result instanceof Color ? $result->value : $result;
        }

        $key = EnumResolver::scalar($state);

        if (isset($this->colors[$key])) {
            return $this->colors[$key];
        }

        // Enum carrying its own color via the opt-in HasColor contract.
        $enumColor = EnumResolver::color($state);

        if ($enumColor !== null) {
            return $enumColor instanceof Color ? $enumColor->value : $enumColor;
        }

        return 'gray';
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
