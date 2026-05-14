<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;

class BadgeColumn extends Column
{
    /** @var array<string, string> */
    protected array $colors = [];

    /** @var array<string, string> */
    protected array $icons = [];

    protected ?Closure $colorCallback = null;

    protected ?Closure $iconCallback = null;

    protected string $size = 'md';

    /**
     * @param  array<string, string>  $colors
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function colorUsing(Closure $callback): static
    {
        $this->colorCallback = $callback;

        return $this;
    }

    /**
     * @param  array<string, string>  $icons
     */
    public function icons(array $icons): static
    {
        $this->icons = $icons;

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
            $iconHtml =
                '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">'.
                $this->getIconSvg($icon).
                '</svg>';
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
            return call_user_func($this->colorCallback, $state) ?? 'gray';
        }

        return $this->colors[$state] ?? 'gray';
    }

    public function getIconForState(mixed $state): ?string
    {
        if ($this->iconCallback) {
            return call_user_func($this->iconCallback, $state);
        }

        return $this->icons[$state] ?? null;
    }

    public function getColorClasses(string $color): string
    {
        return match ($color) {
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'success',
            'green',
            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
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

    public function getIconSvg(string $icon): string
    {
        return match ($icon) {
            'check' => '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>',
            'x' => '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>',
            'clock' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>',
            'exclamation' => '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
            default => '',
        };
    }
}
