<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

class BadgeColumn extends Column
{
    /** @var array<string, string> state → resolved color name */
    protected array $colors = [];

    /** @var array<string, string> state → resolved icon name */
    protected array $icons = [];

    protected ?Closure $colorCallback = null;

    protected ?Closure $iconCallback = null;

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

    // size()/getSize() come from Foundation\Concerns\HasSize (via Column).

    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $state = $this->getState($record);

        if ($state === null || $state === '') {
            return $this->getPlaceholder();
        }

        $color = $this->getColorForState($state);
        $icon = $this->getIconForState($state);

        return $this->renderView('tables.columns.badge', [
            'sizeClasses' => $this->getSizeClasses(),
            'colorClasses' => $this->getColorClasses($color),
            'iconHtml' => $icon ? app(IconManager::class)->render($icon, 'w-3.5 h-3.5 mr-1') : '',
            'displayValue' => $this->formatValue($state, $record),
        ]);
    }

    public function getColorForState(mixed $state): string
    {
        if ($this->colorCallback) {
            $result = ($this->colorCallback)($state);

            return $result instanceof Color ? $result->value : ($result ?? 'gray');
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

    public function getIconForState(mixed $state): ?string
    {
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

    public function getColorClasses(string $color): string
    {
        return self::getBadgeColorClasses($color);
    }

    public function getSizeClasses(): string
    {
        return self::getBadgeSizeClasses($this->getSize());
    }
}
