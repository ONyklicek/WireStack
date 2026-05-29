<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;

class BadgeColumn extends Column
{
    /** @var array<string, string> state → resolved color name */
    protected array $colors = [];

    /** @var array<string, string> state → resolved icon name */
    protected array $icons = [];

    protected ?Closure $colorCallback = null;

    protected ?Closure $iconCallback = null;

    protected string $size = 'md';

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

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

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
        $displayValue = $this->formatValue($state, $record);

        $colorClasses = $this->getColorClasses($color);
        $sizeClasses = $this->getSizeClasses();

        $iconHtml = '';
        if ($icon) {
            $path = app(IconManager::class)->getPath($icon);
            $iconHtml = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">'.$path.'</svg>';
        }

        return <<<HTML
        <span class="inline-flex items-center $sizeClasses $colorClasses rounded-full font-medium">
            $iconHtml{$displayValue}
        </span>
        HTML;
    }

    public function getColorForState(mixed $state): string
    {
        if ($this->colorCallback) {
            $result = ($this->colorCallback)($state);

            return $result instanceof Color ? $result->value : ($result ?? 'gray');
        }

        return $this->colors[(string) $state] ?? 'gray';
    }

    public function getIconForState(mixed $state): ?string
    {
        if ($this->iconCallback) {
            $result = ($this->iconCallback)($state);

            return $result instanceof Icon ? $result->value() : $result;
        }

        return $this->icons[(string) $state] ?? null;
    }

    public function getColorClasses(string $color): string
    {
        return match ($color) {
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'success', 'green', 'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'warning', 'yellow', 'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'danger', 'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'info', 'blue', 'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            'secondary', 'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            'purple', 'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
            'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function getSizeClasses(): string
    {
        return match ($this->size) {
            'xs' => 'px-1.5 py-0.5 text-[10px]',
            'sm' => 'px-2 py-0.5 text-xs',
            'md' => 'px-2.5 py-1 text-xs',
            'lg' => 'px-3 py-1 text-sm',
            default => 'px-2.5 py-1 text-xs',
        };
    }
}
