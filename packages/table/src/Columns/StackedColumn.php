<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class StackedColumn extends Column
{
    protected ?string $primaryColumn = null;

    protected ?string $secondaryColumn = null;

    protected ?string $avatarColumn = null;

    protected string|Closure|null $avatarUrl = null;

    protected bool $circular = true;

    protected string $avatarSize = 'md';

    protected ?string $avatarBackground = null;

    /** @var array<int, array<string, mixed>> */
    protected array $stack = [];

    /** @var array<int, string> */
    protected array $searchColumns = [];

    /**
     * Set primary (main) text column
     */
    public function primary(string $column): static
    {
        $this->primaryColumn = $column;

        return $this;
    }

    /**
     * Get primary column name
     */
    public function getPrimaryColumn(): ?string
    {
        return $this->primaryColumn;
    }

    /**
     * Set secondary (subtitle) text column
     */
    public function secondary(string $column): static
    {
        $this->secondaryColumn = $column;

        return $this;
    }

    /**
     * Get secondary column name
     */
    public function getSecondaryColumn(): ?string
    {
        return $this->secondaryColumn;
    }

    /**
     * Set avatar image column
     */
    public function avatar(string $column): static
    {
        $this->avatarColumn = $column;

        return $this;
    }

    /**
     * Set avatar URL generator
     */
    public function avatarUrl(Closure|string $url): static
    {
        $this->avatarUrl = $url;

        return $this;
    }

    /**
     * Set avatar as circular (default) or square
     */
    public function circular(bool $circular = true): static
    {
        $this->circular = $circular;

        return $this;
    }

    /**
     * Set avatar as square
     */
    public function square(): static
    {
        $this->circular = false;

        return $this;
    }

    /**
     * Set avatar size
     */
    public function avatarSize(string $size): static
    {
        $this->avatarSize = $size;

        return $this;
    }

    /**
     * Set avatar background color for UI Avatars
     */
    public function avatarBackground(string $color): static
    {
        $this->avatarBackground = $color;

        return $this;
    }

    /**
     * Define custom stack of items
     * Each item: ['column' => 'name', 'class' => 'font-bold', 'format' => fn($value) => ...]
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function stack(array $items): static
    {
        $this->stack = $items;

        return $this;
    }

    /**
     * Override searchable to support both bool and array of column names.
     */
    public function searchable(bool|array $searchable = true, ?Closure $query = null): static
    {
        if (is_array($searchable)) {
            $this->searchColumns = $searchable;
            parent::searchable(true, $query);
        } else {
            parent::searchable($searchable, $query);
        }

        return $this;
    }

    /**
     * Set which columns should be searched
     *
     * @param  array<int, string>  $columns
     */
    public function searchColumns(array $columns): static
    {
        $this->searchColumns = $columns;

        return $this;
    }

    /**
     * Get the columns that should be searched
     */
    public function getSearchColumns(): array
    {
        // If explicitly set, use those
        if (! empty($this->searchColumns)) {
            return $this->searchColumns;
        }

        // Otherwise, auto-detect from primary/secondary
        $columns = [];

        if ($this->primaryColumn) {
            $columns[] = $this->primaryColumn;
        }

        if ($this->secondaryColumn) {
            $columns[] = $this->secondaryColumn;
        }

        // Also check stack items
        foreach ($this->stack as $item) {
            if (! empty($item['column']) && ! empty($item['searchable'])) {
                $columns[] = $item['column'];
            }
        }

        return $columns;
    }

    /**
     * Render the cell
     */
    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        // Build content based on configuration
        $primaryValue = $this->primaryColumn ? $this->resolveColumnValue($record, $this->primaryColumn) : null;
        $secondaryValue = $this->secondaryColumn ? $this->resolveColumnValue($record, $this->secondaryColumn) : null;

        // Apply formatters if set
        if ($primaryValue !== null && $this->formatStateUsing) {
            $primaryValue = ($this->formatStateUsing)($primaryValue, $record);
        }

        $customStack = ! empty($this->stack);

        /** @var array<int, array{class: string, value: string}> $items */
        $items = [];

        if ($customStack) {
            foreach ($this->stack as $item) {
                $column = $item['column'] ?? null;
                $class = $item['class'] ?? 'text-sm text-gray-500 dark:text-gray-400';
                $format = $item['format'] ?? null;
                $prefix = $item['prefix'] ?? '';
                $suffix = $item['suffix'] ?? '';

                $value = $column ? $this->resolveColumnValue($record, $column) : '';

                if ($format && is_callable($format)) {
                    $value = call_user_func($format, $value, $record);
                }

                if ($value !== null && $value !== '') {
                    $items[] = ['class' => $class, 'value' => $prefix.$value.$suffix];
                }
            }
        } else {
            if ($primaryValue !== null && $primaryValue !== '') {
                $items[] = ['class' => 'font-medium text-gray-900 dark:text-white', 'value' => $primaryValue];
            }

            if ($secondaryValue !== null && $secondaryValue !== '') {
                $items[] = ['class' => 'text-sm text-gray-500 dark:text-gray-400', 'value' => $secondaryValue];
            }
        }

        return $this->renderView('tables.columns.stacked', [
            'avatarUrl' => $this->getAvatarUrl($record),
            'avatarClasses' => $this->getAvatarClasses(),
            'items' => $items,
            'linesHtml' => $this->getLinesHtml($items),
            'customStack' => $customStack,
        ]);
    }

    /**
     * Render the stacked text lines as one HTML fragment.
     *
     * Canonical owner of the stacked-line markup so the cell view only emits the
     * result instead of building HTML in a Blade closure. Class and value are
     * escaped.
     *
     * @param  array<int, array{class: string, value: string}>  $items
     */
    public function getLinesHtml(array $items): Htmlable
    {
        $html = '';

        foreach ($items as $item) {
            $html .= '<p class="'.e($item['class']).'">'.e($item['value']).'</p>';
        }

        return new HtmlString($html);
    }

    /**
     * Get avatar URL for record
     */
    public function getAvatarUrl(Model $record): ?string
    {
        // Custom URL generator
        if ($this->avatarUrl) {
            if (is_callable($this->avatarUrl)) {
                return ($this->avatarUrl)($record);
            }

            return $this->avatarUrl;
        }

        // Avatar column
        if ($this->avatarColumn) {
            $value = $this->resolveColumnValue($record, $this->avatarColumn);
            if ($value) {
                return $value;
            }
        }

        // Generate UI Avatars URL from primary column
        if ($this->primaryColumn) {
            $name = $this->resolveColumnValue($record, $this->primaryColumn);
            if ($name) {
                $name = urlencode($name);
                $bg = $this->avatarBackground ?? $this->generateColorFromName($name);
                $size = $this->getAvatarPixelSize();

                return "https://ui-avatars.com/api/?name=$name&background=$bg&color=fff&size=$size";
            }
        }

        return null;
    }

    /**
     * Resolve a column value from record (supports dot notation)
     */
    private function resolveColumnValue(Model $record, string $column): mixed
    {
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $value = $record;
            foreach ($parts as $part) {
                if ($value === null) {
                    return null;
                }
                $value = $value->{$part} ?? null;
            }

            return $value;
        }

        return $record->{$column} ?? null;
    }

    /**
     * Generate consistent color from name
     */
    private function generateColorFromName(string $name): string
    {
        $colors = ['6366f1', 'ec4899', '14b8a6', 'f59e0b', '8b5cf6', 'ef4444', '06b6d4', '84cc16'];
        $hash = crc32($name);

        return $colors[abs($hash) % count($colors)];
    }

    /**
     * Get avatar size in pixels
     */
    public function getAvatarPixelSize(): int
    {
        return match ($this->avatarSize) {
            'xs' => 24,
            'sm' => 32,
            'md' => 36,
            'lg' => 48,
            'xl' => 64,
            '2xl' => 80,
            default => 36,
        };
    }

    /**
     * Get avatar CSS classes
     */
    public function getAvatarClasses(): string
    {
        $sizeClass = match ($this->avatarSize) {
            'xs' => 'w-6 h-6',
            'sm' => 'w-8 h-8',
            'md' => 'w-9 h-9',
            'lg' => 'w-12 h-12',
            'xl' => 'w-16 h-16',
            '2xl' => 'w-20 h-20',
            default => 'w-9 h-9',
        };

        $roundedClass = $this->circular ? 'rounded-full' : 'rounded-lg';

        return "$sizeClass $roundedClass";
    }
}
