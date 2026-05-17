<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Components\DataComponent;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Concerns\HasSummary;
use Throwable;

/** @phpstan-consistent-constructor */
class Column extends DataComponent implements Htmlable
{
    use HasSummary;

    /** @var bool Whether the column can be sorted */
    protected bool $sortable = false;

    /** @var bool Whether the column is included in search */
    protected bool $searchable = false;

    /** @var array<int, string> Explicit DB columns to search (Filament-style: searchable(['first_name', 'last_name'])) */
    protected array $searchColumns = [];

    /** @var Closure|null Custom search query callback: fn(Builder, string) => */
    protected ?Closure $searchCallback = null;

    /** @var Closure|null Custom sort query callback: fn(Builder, string) => */
    protected ?Closure $sortCallback = null;

    /** @var bool Whether the column is hidden by default */
    protected bool $hidden = false;

    /** @var string|null Show column from this breakpoint (sm, md, lg, xl, 2xl) */
    protected ?string $visibleFrom = null;

    /** @var string|null Hide column from this breakpoint (sm, md, lg, xl, 2xl) */
    protected ?string $hiddenFrom = null;

    /** @var Closure|null Custom display for mobile view */
    protected ?Closure $mobileDisplayUsing = null;

    /** @var Closure|null Custom display for desktop view */
    protected ?Closure $desktopDisplayUsing = null;

    /** @var string Mobile breakpoint for display switching */
    protected string $mobileBreakpoint = 'md';

    /** @var bool Whether the column can be toggled in the UI */
    protected bool $toggleable = true;

    /** @var string|null Fixed width of the column (e.g., '100px', '20%') */
    protected ?string $width = null;

    /** @var string|null Text alignment within the column ('left', 'center', 'right') */
    protected ?string $alignment = null;

    /** @var Closure|null Custom formatter for the cell value */
    protected ?Closure $formatStateUsing = null;

    /** @var Closure|null Custom display logic for the cell */
    protected ?Closure $displayUsing = null;

    /** @var mixed Default value when the cell value is empty */
    protected mixed $default = null;

    /** @var string|null Tooltip text shown on hover */
    protected ?string $tooltip = null;

    /** @var bool Whether the cell content is copyable */
    protected bool $copyable = false;

    /** @var string|null Message shown when content is copied */
    protected ?string $copyMessage = null;

    /** @var string|null Additional HTML attributes for the cell */
    protected ?string $extraAttributes = null;

    /** @var array<string, string> Additional HTML attributes for the column header */
    protected array $extraHeaderAttributes = [];

    /** @var bool Whether to wrap text in the cell */
    protected bool $wrap = false;

    /** @var int|null Maximum number of characters to show before truncating */
    protected ?int $limit = null;

    /** @var string|null Placeholder text when value is empty */
    protected ?string $placeholder = null;

    /** @var string|null Text to prepend to the cell content */
    protected ?string $prefix = null;

    /** @var string|null Text to append to the cell content */
    protected ?string $suffix = null;

    /** @var Closure|null Callback to generate URL for clickable cells */
    protected ?Closure $urlCallback = null;

    /** @var bool Whether to open links in a new tab */
    protected bool $openUrlInNewTab = false;

    /** @var string|null Text/icon color (e.g., 'primary', 'danger', '#FF0000') */
    protected ?string $color = null;

    /** @var string|null Icon class or name */
    protected ?string $icon = null;

    /** @var string|null Icon position relative to text ('before' or 'after') */
    protected ?string $iconPosition = null;

    /** @var bool Whether the cell content contains raw HTML */
    protected bool $html = false;

    /** @var Closure|null Callback to determine if the column should be visible */
    protected ?Closure $visibleCallback = null;

    /** @var string|null Required permission to view this column */
    protected ?string $permission = null;

    /** @var bool Whether this column is for a pivot table */
    protected bool $isPivot = false;

    // Inline editing properties
    /** @var bool Whether the column is editable */
    protected bool $editable = false;

    /** @var string|null Type of input for inline editing (e.g., 'text', 'select', 'date') */
    protected ?string $editableType = 'text';

    /** @var array<string, string> Options for editable fields (e.g., select options) */
    protected array $editableOptions = [];

    /** @var Closure|null Validation rules for inline editing */
    protected ?Closure $editableRules = null;

    /** @var Closure|null Callback to handle the edit action */
    protected ?Closure $editableCallback = null;

    // Column filtering properties
    /** @var bool Whether the column can be filtered */
    protected bool $filterable = false;

    /**
     * Type of filter input.
     * Supported: 'text', 'select', 'date', 'date_range', 'number_range', 'boolean'
     */
    protected ?string $filterType = 'text';

    /** @var array<string, string> Options for filter dropdowns */
    protected array $filterOptions = [];

    /** @var string|null Placeholder text for filter input */
    protected ?string $filterPlaceholder = null;

    /** @var Closure|null Custom filter query logic */
    protected ?Closure $filterQueryCallback = null;

    /**
     * SQL operator for text filter.
     * Supported: 'like' (default), 'equals', 'starts_with', 'ends_with',
     *            '>', '>=', '<', '<=', '!=', 'between'
     */
    protected string $filterOperator = 'like';

    /** @var string|null Minimum date for date/date_range filters */
    protected ?string $filterMinDate = null;

    /** @var string|null Maximum date for date/date_range filters */
    protected ?string $filterMaxDate = null;

    /** @var float|null Min value for number_range filter input */
    protected ?float $filterMinValue = null;

    /** @var float|null Max value for number_range filter input */
    protected ?float $filterMaxValue = null;

    /** @var float|null Step for number_range filter */
    protected ?float $filterStep = null;

    /** @var int Debounce in milliseconds for text/number column filters */
    protected int $filterDebounce = 300;

    /** @var string|null Boolean filter true label */
    protected ?string $filterTrueLabel = null;

    /** @var string|null Boolean filter false label */
    protected ?string $filterFalseLabel = null;

    // Text styling properties
    /** @var string|null Text size class or value (e.g., 'sm', 'lg', '1.2rem') */
    protected ?string $textSize = null;

    /** @var string|null Font weight (e.g., 'bold', '500') */
    protected ?string $textWeight = null;

    /** @var string|null Text color (e.g., 'red-500', '#FF0000') */
    protected ?string $textColor = null;

    /** @var string|Closure|null Additional description text shown below/above the cell content */
    protected string|Closure|null $description = null;

    /** @var string Position of the description relative to the cell content ('below' or 'above') */
    protected string $descriptionPosition = 'below';

    /** @var Closure|null Callback to determine the state of the column */
    protected ?Closure $stateCallback = null;

    protected Closure $hiddenCallback;

    /**
     * Constructor.
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * Get the relation of the column.
     * Delegates to DataComponent's RelationPath infrastructure.
     */
    public function getRelation(): ?string
    {
        return $this->getRelationName();
    }

    /**
     * Set the column as a pivot column.
     */
    public function pivot(bool $isPivot = true): static
    {
        $this->isPivot = $isPivot;

        return $this;
    }

    /**
     * Check if the column is a pivot column.
     */
    public function isPivot(): bool
    {
        return $this->isPivot;
    }

    /**
     * Set the label of the column.
     */
    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set a callback to determine the state of the column.
     */
    public function state(Closure $callback): static
    {
        $this->stateCallback = $callback;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Set whether the column is searchable.
     *
     * Filament-style API:
     *   ->searchable()                                    // auto-detect
     *   ->searchable(['first_name', 'last_name'])         // explicit DB columns
     *   ->searchable(query: fn($query, $search) => ...)   // custom query callback
     *
     * @param  bool|array<int, string>  $searchable
     */
    public function searchable(bool|array $searchable = true, ?Closure $query = null): static
    {
        if (is_array($searchable)) {
            $this->searchable = true;
            $this->searchColumns = $searchable;
        } else {
            $this->searchable = $searchable;
        }

        if ($query !== null) {
            $this->searchCallback = $query;
        }

        // Bridge to capability system
        $this->capabilities = $this->searchable
            ? $this->capabilities->add(Capability::Searchable)
            : $this->capabilities->remove(Capability::Searchable);

        return $this;
    }

    /**
     * Check if the column is searchable.
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Get explicit search columns (empty = use auto-detection).
     *
     * @return array<int, string>
     */
    public function getSearchColumns(): array
    {
        return $this->searchColumns;
    }

    /**
     * Set a custom search query callback.
     *
     * @param  Closure  $callback  fn(Builder $query, string $search): Builder
     */
    public function searchUsing(Closure $callback): static
    {
        $this->searchable = true;
        $this->searchCallback = $callback;

        return $this;
    }

    /**
     * Get the custom search callback.
     */
    public function getSearchCallback(): ?Closure
    {
        return $this->searchCallback;
    }

    /**
     * Set whether the column is sortable.
     *
     * Supports custom sort callback:
     *   ->sortable()
     *   ->sortable(query: fn($query, $direction) => ...)
     */
    public function sortable(bool $sortable = true, ?Closure $query = null): static
    {
        $this->sortable = $sortable;

        if ($query !== null) {
            $this->sortCallback = $query;
        }

        // Bridge to capability system
        $this->capabilities = $this->sortable
            ? $this->capabilities->add(Capability::Sortable)
            : $this->capabilities->remove(Capability::Sortable);

        return $this;
    }

    /**
     * Set a custom sort query callback.
     *
     * @param  Closure  $callback  fn(Builder $query, string $direction): Builder
     */
    public function sortUsing(Closure $callback): static
    {
        $this->sortable = true;
        $this->sortCallback = $callback;

        return $this;
    }

    /**
     * Get the custom sort callback.
     */
    public function getSortCallback(): ?Closure
    {
        return $this->sortCallback;
    }

    /**
     * Set whether the column is hidden.
     */
    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
        } else {
            $this->hidden = $hidden;
        }

        return $this;
    }

    /**
     * Check if the column is hidden.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Show column only from specified breakpoint and up.
     * Column will be hidden on smaller screens.
     *
     * @param  string  $breakpoint  sm, md, lg, xl, 2xl
     */
    public function visibleFrom(string $breakpoint): static
    {
        $this->visibleFrom = $breakpoint;

        return $this;
    }

    /**
     * Hide column from specified breakpoint and up.
     * Column will be visible only on smaller screens.
     *
     * @param  string  $breakpoint  sm, md, lg, xl, 2xl
     */
    public function hiddenFrom(string $breakpoint): static
    {
        $this->hiddenFrom = $breakpoint;

        return $this;
    }

    /**
     * Get CSS classes for responsive visibility.
     */
    public function getResponsiveClasses(): string
    {
        $classes = [];

        if ($this->visibleFrom) {
            // Hidden by default, shown from breakpoint
            $classes[] = 'hidden';
            $classes[] = match ($this->visibleFrom) {
                'sm' => 'sm:table-cell',
                'md' => 'md:table-cell',
                'lg' => 'lg:table-cell',
                'xl' => 'xl:table-cell',
                '2xl' => '2xl:table-cell',
                default => 'md:table-cell',
            };
        }

        if ($this->hiddenFrom) {
            // Shown by default, hidden from breakpoint
            $classes[] = match ($this->hiddenFrom) {
                'sm' => 'sm:hidden',
                'md' => 'md:hidden',
                'lg' => 'lg:hidden',
                'xl' => 'xl:hidden',
                '2xl' => '2xl:hidden',
                default => 'md:hidden',
            };
        }

        return implode(' ', $classes);
    }

    /**
     * Check if column has responsive visibility settings.
     */
    public function hasResponsiveVisibility(): bool
    {
        return $this->visibleFrom !== null || $this->hiddenFrom !== null;
    }

    /**
     * Show column only on tablet and up (hidden below sm).
     */
    public function onlyOnTabletAndUp(): static
    {
        $this->visibleFrom = 'sm';

        return $this;
    }

    /**
     * Show column only on large screens (hidden below lg).
     */
    public function onlyOnLargeScreens(): static
    {
        $this->visibleFrom = 'lg';

        return $this;
    }

    /**
     * Alias for onlyOnMobile - shorter syntax.
     */
    public function mobileOnly(): static
    {
        return $this->onlyOnMobile();
    }

    /**
     * Show column only on mobile (hidden on md and up).
     */
    public function onlyOnMobile(): static
    {
        $this->hiddenFrom = 'md';

        return $this;
    }

    /**
     * Alias for onlyOnDesktop - shorter syntax.
     */
    public function desktopOnly(): static
    {
        return $this->onlyOnDesktop();
    }

    /**
     * Show column only on desktop (hidden below md).
     */
    public function onlyOnDesktop(): static
    {
        $this->visibleFrom = 'md';

        return $this;
    }

    /**
     * Set custom display for mobile devices.
     * Use this when you want different content on mobile vs desktop.
     *
     * @param  Closure  $callback  fn($record, $column) => string
     */
    public function mobileDisplayUsing(Closure $callback): static
    {
        $this->mobileDisplayUsing = $callback;

        return $this;
    }

    /**
     * Set custom display for desktop devices.
     *
     * @param  Closure  $callback  fn($record, $column) => string
     */
    public function desktopDisplayUsing(Closure $callback): static
    {
        $this->desktopDisplayUsing = $callback;

        return $this;
    }

    /**
     * Set the breakpoint that separates mobile from desktop.
     *
     * @param  string  $breakpoint  sm, md, lg, xl, 2xl
     */
    public function mobileBreakpoint(string $breakpoint): static
    {
        $this->mobileBreakpoint = $breakpoint;

        return $this;
    }

    /**
     * Render cell with responsive wrappers if needed.
     */
    public function renderResponsiveCell(Model $record): string
    {
        if (! $this->hasResponsiveDisplay()) {
            return $this->renderCell($record);
        }

        $bp = $this->mobileBreakpoint;
        $mobileContent = $this->renderMobileCell($record);
        $desktopContent = $this->renderDesktopCell($record);

        // If content is the same, no need for wrappers
        if ($mobileContent === $desktopContent) {
            return $mobileContent;
        }

        return sprintf(
            '<span class="%s:hidden">%s</span><span class="hidden %s:inline">%s</span>',
            $bp,
            $mobileContent,
            $bp,
            $desktopContent,
        );
    }

    /**
     * Check if column has separate mobile/desktop displays.
     */
    public function hasResponsiveDisplay(): bool
    {
        return $this->mobileDisplayUsing !== null || $this->desktopDisplayUsing !== null;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $state = $this->getState($record);

        if ($this->displayUsing) {
            $content = (string) call_user_func($this->displayUsing, $state, $record);
        } else {
            $content = $this->formatValue($state, $record);
        }

        // Apply text styling
        $classes = $this->getTextClasses();
        if ($classes && ! $this->html) {
            $content = '<span class="'.$classes.'">'.e($content).'</span>';
        } elseif ($classes) {
            $content = '<span class="'.$classes.'">'.$content.'</span>';
        } elseif (! $this->html) {
            $content = e($content);
        }

        // Add icon if set
        if ($this->icon) {
            $iconHtml = $this->renderIcon($this->icon);
            if ($this->iconPosition === 'after') {
                $content = $content.' '.$iconHtml;
            } else {
                $content = $iconHtml.' '.$content;
            }
        }

        // Wrap in URL if set
        $url = $this->getUrl($record);
        if ($url) {
            $target = $this->openUrlInNewTab ? ' target="_blank"' : '';
            $content =
                '<a href="'.
                e($url).
                '"'.
                $target.
                ' class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">'.
                $content.
                '</a>';
        }

        // Add copyable button if enabled
        if ($this->copyable) {
            $copyValue = e(str_replace("'", "\\'", (string) $state));
            $copyMessage = e($this->copyMessage ?? Trans::get('wire-table::messages.copied'));
            $copyTitle = e(Trans::get('wire-table::messages.copy'));
            $content = <<<HTML
            <span class="inline-flex items-center gap-1.5 group" x-data="{ copied: false }">
                {$content}
                <button
                    type="button"
                    x-on:click="
                        navigator.clipboard.writeText('$copyValue');
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                    class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="$copyTitle"
                >
                    <template x-if="!copied">
                        <svg class="w-4 h-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </template>
                </button>
                <span
                    x-show="copied"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-x-1"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="text-xs text-emerald-600 dark:text-emerald-400 font-medium"
                >$copyMessage</span>
            </span>
            HTML;
        }

        // Add tooltip if set
        if ($this->tooltip) {
            $tooltipText = e($this->tooltip);
            $content = '<span title="'.$tooltipText.'" class="cursor-help">'.$content.'</span>';
        }

        // Add description if set
        if ($this->description) {
            $descriptionText = is_callable($this->description)
                ? call_user_func($this->description, $record)
                : $this->description;

            if ($descriptionText) {
                $descriptionHtml =
                    '<p class="text-sm text-gray-500 dark:text-gray-400">'.e($descriptionText).'</p>';

                if ($this->descriptionPosition === 'above') {
                    $content = $descriptionHtml.$content;
                } else {
                    $content = $content.$descriptionHtml;
                }

                $content = '<div>'.$content.'</div>';
            }
        }

        return $content;
    }

    public function canView(): bool
    {
        if (! $this->permission) {
            return true;
        }

        /** @var Authenticatable|null $user */
        $user = auth()->guard()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }

        // Spatie Permission support
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($this->permission);
        }

        return true;
    }

    /**
     * Returns the state of the given record.
     *
     * If the column has a state callback set, it will be used to resolve the state.
     * Otherwise, it will fallback to resolving the value using `resolveValue` and
     * then formatting it using `formatStateUsing` if it is set.
     *
     * If the resolved value is null or empty, it will return the default value set on the column.
     */
    public function getState(Model $record): mixed
    {
        // Has set stateCallback use it
        if ($this->stateCallback instanceof Closure) {
            return ($this->stateCallback)($record);
        }

        // fallback: resolveValue + formatStateUsing
        $value = $this->resolveValue($record);

        if ($this->formatStateUsing) {
            $value = call_user_func($this->formatStateUsing, $value, $record);
        }

        if ($value === null || $value === '') {
            return $this->default;
        }

        return $value;
    }

    private function resolveValue(Model $record): mixed
    {
        $name = $this->name;

        // Handle pivot data
        /** @var Pivot|null $pivot */
        $pivot = $record->getAttribute('pivot');
        if ($this->isPivot && $pivot) {
            $attribute = Str::afterLast($name, '.');

            return $pivot->{$attribute};
        }

        // Handle dot notation for relations
        if (Str::contains($name, '.')) {
            return data_get($record, $name);
        }

        return $record->{$name};
    }

    public function formatValue(mixed $value, Model $record): string
    {
        if ($value === null || $value === '') {
            return $this->getPlaceholder() ?? '';
        }

        $formatted = (string) $value;

        if ($this->limit !== null && strlen($formatted) > $this->limit) {
            $formatted = Str::limit($formatted, $this->limit);
        }

        if ($this->prefix) {
            $formatted = $this->prefix.$formatted;
        }

        if ($this->suffix) {
            $formatted = $formatted.$this->suffix;
        }

        return $formatted;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? '-';
    }

    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get CSS classes for text styling
     */
    public function getTextClasses(): string
    {
        $classes = [];

        if ($this->textSize) {
            $classes[] = match ($this->textSize) {
                'xs' => 'text-xs',
                'sm' => 'text-sm',
                'base', 'md' => 'text-base',
                'lg' => 'text-lg',
                'xl' => 'text-xl',
                '2xl' => 'text-2xl',
                default => "text-$this->textSize",
            };
        }

        if ($this->textWeight) {
            $classes[] = match ($this->textWeight) {
                'thin' => 'font-thin',
                'light' => 'font-light',
                'normal' => 'font-normal',
                'medium' => 'font-medium',
                'semibold' => 'font-semibold',
                'bold' => 'font-bold',
                'extrabold' => 'font-extrabold',
                'black' => 'font-black',
                default => "font-$this->textWeight",
            };
        }

        if ($this->textColor) {
            $classes[] = match ($this->textColor) {
                'primary' => 'text-primary-600 dark:text-primary-400',
                'secondary' => 'text-gray-500 dark:text-gray-400',
                'success' => 'text-emerald-600 dark:text-emerald-400',
                'danger' => 'text-red-600 dark:text-red-400',
                'warning' => 'text-amber-600 dark:text-amber-400',
                'info' => 'text-sky-600 dark:text-sky-400',
                'muted' => 'text-gray-400 dark:text-gray-500',
                default => $this->textColor,
            };
        }

        return implode(' ', $classes);
    }

    /**
     * Render an icon SVG
     */
    protected function renderIcon(string $icon): string
    {
        $color = $this->color ? $this->getColorClass($this->color) : 'text-gray-400';
        $path = $this->getIconPath($icon);

        return '<svg class="w-4 h-4 inline-block '.
            $color.
            '" fill="currentColor" viewBox="0 0 20 20">'.
            $path.
            '</svg>';
    }

    /**
     * Get CSS class for a color
     */
    protected function getColorClass(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'text-primary-600 dark:text-primary-400',
            'success', 'green' => 'text-emerald-600 dark:text-emerald-400',
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow' => 'text-amber-600 dark:text-amber-400',
            'info', 'cyan' => 'text-cyan-600 dark:text-cyan-400',
            'gray', 'secondary' => 'text-gray-500 dark:text-gray-400',
            default => $color,
        };
    }

    /**
     * Get icon SVG path
     */
    protected function getIconPath(string $icon): string
    {
        return match ($icon) {
            'pencil',
            'edit' => '<path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>',
            'trash',
            'delete' => '<path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>',
            'eye',
            'view' => '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>',
            'check' => '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>',
            'x',
            'close' => '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>',
            'check-circle' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            'x-circle' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
            'exclamation-circle' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
            'information-circle' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>',
            'mail',
            'email' => '<path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>',
            'phone' => '<path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>',
            'link' => '<path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/>',
            'external-link' => '<path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>',
            'clipboard',
            'copy' => '<path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/>',
            'star' => '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>',
            'user' => '<path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>',
            'calendar' => '<path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>',
            'clock' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>',
            'document' => '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>',
            'download' => '<path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>',
            'upload' => '<path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>',
            default => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>',
        };
    }

    public function getUrl(Model $record): ?string
    {
        if ($this->urlCallback) {
            return call_user_func($this->urlCallback, $record);
        }

        return null;
    }

    /**
     * Render mobile-specific content.
     */
    public function renderMobileCell(Model $record): string
    {
        if ($this->mobileDisplayUsing) {
            $state = $this->getState($record);
            $content = call_user_func($this->mobileDisplayUsing, $state, $record, $this);

            return $this->html ? (string) $content : e((string) $content);
        }

        return $this->renderCell($record);
    }

    /**
     * Render desktop-specific content.
     */
    public function renderDesktopCell(Model $record): string
    {
        if ($this->desktopDisplayUsing) {
            $state = $this->getState($record);
            $content = call_user_func($this->desktopDisplayUsing, $state, $record, $this);

            return $this->html ? (string) $content : e((string) $content);
        }

        return $this->renderCell($record);
    }

    /**
     * Set whether the column can be toggled in the UI.
     */
    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    /**
     * Check if the column is toggleable.
     */
    public function isToggleable(): bool
    {
        return $this->toggleable;
    }

    /**
     * Set the width of the column.
     */
    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get the width of the column.
     */
    public function getWidth(): ?string
    {
        return $this->width;
    }

    /**
     * Align the column text to the left.
     */
    public function alignLeft(): static
    {
        return $this->alignment('left');
    }

    /**
     * Set the text alignment of the column.
     */
    public function alignment(string $alignment): static
    {
        $this->alignment = $alignment;

        return $this;
    }

    /**
     * Center-align the column text.
     */
    public function alignCenter(): static
    {
        return $this->alignment('center');
    }

    /**
     * Align the column text to the right.
     */
    public function alignRight(): static
    {
        return $this->alignment('right');
    }

    /**
     * Get the current text alignment of the column.
     */
    public function getAlignment(): string
    {
        return $this->alignment ?? 'left';
    }

    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    public function displayUsing(Closure $callback): static
    {
        $this->displayUsing = $callback;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function getTooltip(): ?string
    {
        return $this->tooltip;
    }

    public function copyable(bool $copyable = true, ?string $copyMessage = null): static
    {
        $this->copyable = $copyable;
        $this->copyMessage = $copyMessage ?? Trans::get('wire-table::messages.copied');

        return $this;
    }

    public function copyMessage(string $copyMessage): static
    {
        $this->copyMessage = $copyMessage;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    public function getCopyMessage(): ?string
    {
        return $this->copyMessage;
    }

    public function extraAttributes(string $attributes): static
    {
        $this->extraAttributes = $attributes;

        return $this;
    }

    public function getExtraAttributes(): ?string
    {
        return $this->extraAttributes;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function extraHeaderAttributes(array $attributes): static
    {
        $this->extraHeaderAttributes = $attributes;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getExtraHeaderAttributes(): array
    {
        return $this->extraHeaderAttributes;
    }

    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function shouldWrap(): bool
    {
        return $this->wrap;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function actionUrl(Closure $callback, bool $openInNewTab = false): static
    {
        $this->urlCallback = $callback;
        $this->openUrlInNewTab = $openInNewTab;

        return $this;
    }

    public function shouldOpenUrlInNewTab(): bool
    {
        return $this->openUrlInNewTab;
    }

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function icon(?string $icon, ?string $position = 'before'): static
    {
        $this->icon = $icon;
        $this->iconPosition = $position;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getIconPosition(): ?string
    {
        return $this->iconPosition;
    }

    public function html(bool $html = true): static
    {
        $this->html = $html;

        return $this;
    }

    // Inline editing methods

    public function isHtml(): bool
    {
        return $this->html;
    }

    public function visible(Closure $callback): static
    {
        $this->visibleCallback = $callback;

        return $this;
    }

    public function isVisible(): bool
    {
        if ($this->visibleCallback) {
            return call_user_func($this->visibleCallback);
        }

        return ! $this->hidden;
    }

    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function editable(bool $editable = true, string $type = 'text', array $options = []): static
    {
        $this->editable = $editable;
        $this->editableType = $type;
        $this->editableOptions = $options;

        // Bridge to capability system
        $this->capabilities = $this->editable
            ? $this->capabilities->add(Capability::Editable)
            : $this->capabilities->remove(Capability::Editable);

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function getEditableType(): string
    {
        return $this->editableType;
    }

    // Text styling methods

    /**
     * @return array<string, string>
     */
    public function getEditableOptions(): array
    {
        return $this->editableOptions;
    }

    public function editableRules(Closure $callback): static
    {
        $this->editableRules = $callback;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getEditableRules(?Model $record): array
    {
        if ($this->editableRules) {
            return call_user_func($this->editableRules, $record);
        }

        return [];
    }

    public function editableUsing(Closure $callback): static
    {
        $this->editableCallback = $callback;

        return $this;
    }

    public function getEditableCallback(): ?Closure
    {
        return $this->editableCallback;
    }

    public function size(string $size): static
    {
        $this->textSize = $size;

        return $this;
    }

    public function getTextSize(): ?string
    {
        return $this->textSize;
    }

    public function weight(string $weight): static
    {
        $this->textWeight = $weight;

        return $this;
    }

    public function getTextWeight(): ?string
    {
        return $this->textWeight;
    }

    // Column filtering methods

    public function textColor(string $color): static
    {
        $this->textColor = $color;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    /**
     * Add description text below or above the main content
     */
    public function description(string|Closure $description, string $position = 'below'): static
    {
        $this->description = $description;
        $this->descriptionPosition = $position;

        return $this;
    }

    public function getDescription(): string|Closure|null
    {
        return $this->description;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function filterable(bool $filterable = true, string $type = 'text', array $options = []): static
    {
        $this->filterable = $filterable;
        $this->filterType = $type;
        $this->filterOptions = $options;

        // Bridge to capability system
        $this->capabilities = $this->filterable
            ? $this->capabilities->add(Capability::Filterable)
            : $this->capabilities->remove(Capability::Filterable);

        return $this;
    }

    /**
     * Configure as a select column filter.
     *
     * @param  array<string, string>  $options
     */
    public function filterAsSelect(array $options, ?string $placeholder = null): static
    {
        $this->filterable = true;
        $this->filterType = 'select';
        $this->filterOptions = $options;
        if ($placeholder) {
            $this->filterPlaceholder = $placeholder;
        }

        return $this;
    }

    /**
     * Configure as a date column filter.
     */
    public function filterAsDate(?string $minDate = null, ?string $maxDate = null): static
    {
        $this->filterable = true;
        $this->filterType = 'date';
        $this->filterMinDate = $minDate;
        $this->filterMaxDate = $maxDate;

        return $this;
    }

    /**
     * Configure as a date range column filter (from/to).
     */
    public function filterAsDateRange(?string $minDate = null, ?string $maxDate = null): static
    {
        $this->filterable = true;
        $this->filterType = 'date_range';
        $this->filterMinDate = $minDate;
        $this->filterMaxDate = $maxDate;

        return $this;
    }

    /**
     * Configure as a number range column filter (min/max).
     */
    public function filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null): static
    {
        $this->filterable = true;
        $this->filterType = 'number_range';
        $this->filterMinValue = $min;
        $this->filterMaxValue = $max;
        $this->filterStep = $step;

        return $this;
    }

    /**
     * Configure as a boolean (yes/no/all) column filter.
     */
    public function filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null): static
    {
        $this->filterable = true;
        $this->filterType = 'boolean';
        $this->filterTrueLabel = $trueLabel;
        $this->filterFalseLabel = $falseLabel;

        return $this;
    }

    /**
     * Set the filter SQL operator.
     * Supported: 'like', 'equals', 'starts_with', 'ends_with', '>', '>=', '<', '<=', '!='
     */
    public function filterOperator(string $operator): static
    {
        $this->filterOperator = $operator;

        return $this;
    }

    public function getFilterOperator(): string
    {
        return $this->filterOperator;
    }

    /**
     * Set debounce for text/number column filter input (in ms).
     */
    public function filterDebounce(int $ms): static
    {
        $this->filterDebounce = $ms;

        return $this;
    }

    public function getFilterDebounce(): int
    {
        return $this->filterDebounce;
    }

    public function getFilterMinDate(): ?string
    {
        return $this->filterMinDate;
    }

    public function getFilterMaxDate(): ?string
    {
        return $this->filterMaxDate;
    }

    public function getFilterMinValue(): ?float
    {
        return $this->filterMinValue;
    }

    public function getFilterMaxValue(): ?float
    {
        return $this->filterMaxValue;
    }

    public function getFilterStep(): ?float
    {
        return $this->filterStep;
    }

    public function getFilterTrueLabel(): string
    {
        return $this->filterTrueLabel ?? Trans::get('wire-table::messages.filter_yes');
    }

    public function getFilterFalseLabel(): string
    {
        return $this->filterFalseLabel ?? Trans::get('wire-table::messages.filter_no');
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function getFilterType(): string
    {
        return $this->filterType;
    }

    /**
     * @return array<string, string>
     */
    public function getFilterOptions(): array
    {
        return $this->filterOptions;
    }

    public function filterPlaceholder(?string $placeholder): static
    {
        $this->filterPlaceholder = $placeholder;

        return $this;
    }

    public function getFilterPlaceholder(): ?string
    {
        return $this->filterPlaceholder;
    }

    public function filterUsing(Closure $callback): static
    {
        $this->filterQueryCallback = $callback;

        return $this;
    }

    public function getFilterQueryCallback(): ?Closure
    {
        return $this->filterQueryCallback;
    }

    public function applyFilter(mixed $query, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        // 1. Custom filter callback takes priority
        if ($this->filterQueryCallback) {
            return call_user_func($this->filterQueryCallback, $query, $value);
        }

        $column = $this->name;

        // 2. Handle relation columns
        if ($this->hasRelation()) {
            $relation = $this->getRelation();
            $attribute = $this->getRelationshipAttribute();

            return $query->whereHas($relation, function ($q) use ($attribute, $value) {
                $this->applyFilterCondition($q, $attribute, $value);
            });
        }

        // 3. Standard column
        return $this->applyFilterCondition($query, $column, $value);
    }

    /**
     * Apply the actual filter condition based on filterType and filterOperator.
     */
    public function applyFilterCondition(mixed $query, string $column, mixed $value): mixed
    {
        return match ($this->filterType) {
            'select' => is_array($value)
                ? $query->whereIn($column, $value)
                : $query->where($column, $value),

            'boolean' => $this->applyBooleanFilter($query, $column, $value),

            'date' => $query->whereDate($column, $value),

            'date_range' => $this->applyDateRangeFilter($query, $column, $value),

            'number_range' => $this->applyNumberRangeFilter($query, $column, $value),

            default => $this->applyTextFilter($query, $column, $value),
        };
    }

    /**
     * Apply text filter with configurable operator.
     */
    protected function applyTextFilter(mixed $query, string $column, mixed $value): mixed
    {
        return match ($this->filterOperator) {
            'equals', '=' => $query->where($column, $value),
            'starts_with' => $query->where($column, 'like', "$value%"),
            'ends_with' => $query->where($column, 'like', "%$value"),
            '>', '>=', '<', '<=', '!=' => $query->where($column, $this->filterOperator, $value),
            default => $query->where($column, 'like', "%$value%"),
        };
    }

    /**
     * Apply boolean filter (true/false/null).
     */
    protected function applyBooleanFilter(mixed $query, string $column, mixed $value): mixed
    {
        if ($value === 'true' || $value === '1' || $value === true) {
            return $query->where($column, true);
        }

        if ($value === 'false' || $value === '0' || $value === false) {
            return $query->where(function ($q) use ($column) {
                $q->where($column, false)->orWhereNull($column);
            });
        }

        return $query;
    }

    /**
     * Apply date range filter (from/to).
     */
    protected function applyDateRangeFilter(mixed $query, string $column, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $query->whereDate($column, $value);
        }

        if (! empty($value['from'])) {
            $query->whereDate($column, '>=', $value['from']);
        }
        if (! empty($value['to'])) {
            $query->whereDate($column, '<=', $value['to']);
        }

        return $query;
    }

    /**
     * Apply number range filter (min/max).
     */
    protected function applyNumberRangeFilter(mixed $query, string $column, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $query->where($column, $value);
        }

        if (isset($value['min']) && $value['min'] !== '') {
            $query->where($column, '>=', (float) $value['min']);
        }
        if (isset($value['max']) && $value['max'] !== '') {
            $query->where($column, '<=', (float) $value['max']);
        }

        return $query;
    }

    /**
     * Get the relationship attribute of the column.
     * Delegates to DataComponent's column name resolution.
     */
    public function getRelationshipAttribute(): ?string
    {
        if (! $this->hasRelation()) {
            return null;
        }

        return $this->getColumnName();
    }

    /**
     * @throws Throwable
     */
    public function renderFilter(mixed $value = null): string
    {
        if (! $this->filterable) {
            return '';
        }

        $viewName = match ($this->filterType) {
            'select' => 'tables.columns.partials.filter-select',
            'date' => 'tables.columns.partials.filter-date',
            'date_range' => 'tables.columns.partials.filter-date-range',
            'number_range' => 'tables.columns.partials.filter-number-range',
            'boolean' => 'tables.columns.partials.filter-boolean',
            default => 'tables.columns.partials.filter-text',
        };

        $namespacedView = "wire-table::$viewName";
        $resolvedView = view()->exists($namespacedView) ? $namespacedView : $viewName;

        return view($resolvedView, [
            'column' => $this,
            'value' => $value,
        ])->render();
    }

    public function toHtml(): string
    {
        return $this->getLabel();
    }

    /**
     * Get the label of the column.
     * Overrides DataComponent to use Str::headline for prettier labels.
     */
    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->evaluate($this->label);
        }

        $name = $this->relationPath !== null
            ? $this->relationPath->getColumnName()
            : $this->name;

        return Str::headline($name);
    }
}
