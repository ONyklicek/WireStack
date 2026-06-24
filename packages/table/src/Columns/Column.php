<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Components\DataComponent;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Concerns\HasSummary;
use NyonCode\WireTable\Concerns\HasView;

/** @phpstan-consistent-constructor */
class Column extends DataComponent implements Htmlable
{
    use HasAuthorization;
    use HasColor;
    use HasIcon;
    use HasSize;
    use HasSummary;
    use HasView;

    // Note: $sortable and $searchable booleans removed in v2.
    // Use capabilities as single source of truth via isSortable()/isSearchable().

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

    // $icon and $iconPosition are provided by Foundation\Concerns\HasIcon
    // (string|Closure|null), giving columns the same closure-aware icon API as
    // forms fields and core actions.

    /** @var bool Whether the cell content contains raw HTML */
    protected bool $html = false;

    /** @var Closure|null Callback to determine if the column should be visible */
    protected ?Closure $visibleCallback = null;

    /** @var string|null Gate ability for inline editing */
    protected ?string $inlineEditAbility = null;

    /** @var bool Whether this column is for a pivot table */
    protected bool $isPivot = false;

    // ── Aggregate support ─────────────────────────────────────────
    /** @var string|null Aggregate function: 'count', 'sum', 'avg', 'min', 'max' */
    protected ?string $aggregateFunction = null;

    /** @var string|null Relation name for aggregate (e.g., 'orders') */
    protected ?string $aggregateRelation = null;

    /** @var string|null Column to aggregate on (e.g., 'total' for sum) */
    protected ?string $aggregateColumn = null;

    // Inline editing properties
    // Note: $editable boolean removed in v2. Use capabilities.

    /** @var string|null Type of input for inline editing (e.g., 'text', 'select', 'date') */
    protected ?string $editableType = 'text';

    /** @var array<string, string> Options for editable fields (e.g., select options) */
    protected array $editableOptions = [];

    /** @var Closure|null Validation rules for inline editing */
    protected ?Closure $editableRules = null;

    /** @var Closure|null Callback to handle the edit action */
    protected ?Closure $editableCallback = null;

    // Column filtering properties
    // Note: $filterable boolean removed in v2. Use capabilities.

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

    // ── Aggregate methods ─────────────────────────────────────────

    /**
     * Count related records.
     *
     * Usage: Column::make('orders_count')->counts('orders')
     */
    public function counts(string $relationship): static
    {
        $this->aggregateFunction = 'count';
        $this->aggregateRelation = $relationship;

        return $this;
    }

    /**
     * Sum a column on related records.
     *
     * Usage: Column::make('orders_total')->sums('orders', 'total')
     */
    public function sums(string $relationship, string $column): static
    {
        $this->aggregateFunction = 'sum';
        $this->aggregateRelation = $relationship;
        $this->aggregateColumn = $column;

        return $this;
    }

    /**
     * Average a column on related records.
     *
     * Usage: Column::make('avg_rating')->averages('reviews', 'rating')
     */
    public function averages(string $relationship, string $column): static
    {
        $this->aggregateFunction = 'avg';
        $this->aggregateRelation = $relationship;
        $this->aggregateColumn = $column;

        return $this;
    }

    /**
     * Min of a column on related records.
     */
    public function mins(string $relationship, string $column): static
    {
        $this->aggregateFunction = 'min';
        $this->aggregateRelation = $relationship;
        $this->aggregateColumn = $column;

        return $this;
    }

    /**
     * Max of a column on related records.
     */
    public function maxes(string $relationship, string $column): static
    {
        $this->aggregateFunction = 'max';
        $this->aggregateRelation = $relationship;
        $this->aggregateColumn = $column;

        return $this;
    }

    public function isAggregate(): bool
    {
        return $this->aggregateFunction !== null;
    }

    public function getAggregateFunction(): ?string
    {
        return $this->aggregateFunction;
    }

    public function getAggregateRelation(): ?string
    {
        return $this->aggregateRelation;
    }

    public function getAggregateColumn(): ?string
    {
        return $this->aggregateColumn;
    }

    /**
     * Get the attribute name that Eloquent uses for withCount/withSum.
     * E.g., withCount('orders') → 'orders_count', withSum('orders', 'total') → 'orders_sum_total'
     */
    public function getAggregateAttribute(): ?string
    {
        if ($this->aggregateFunction === null || $this->aggregateRelation === null) {
            return null;
        }

        if ($this->aggregateFunction === 'count') {
            return "{$this->aggregateRelation}_count";
        }

        return "{$this->aggregateRelation}_{$this->aggregateFunction}_{$this->aggregateColumn}";
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
        return $this->hasCapability(Capability::Sortable);
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
            $this->searchColumns = $searchable;
            $this->capabilities = $this->capabilities->add(Capability::Searchable);
        } else {
            $this->capabilities = $searchable
                ? $this->capabilities->add(Capability::Searchable)
                : $this->capabilities->remove(Capability::Searchable);
        }

        if ($query !== null) {
            $this->searchCallback = $query;
        }

        return $this;
    }

    /**
     * Check if the column is searchable.
     */
    public function isSearchable(): bool
    {
        return $this->hasCapability(Capability::Searchable);
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
        $this->capabilities = $this->capabilities->add(Capability::Searchable);
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
        $this->capabilities = $sortable
            ? $this->capabilities->add(Capability::Sortable)
            : $this->capabilities->remove(Capability::Sortable);

        if ($query !== null) {
            $this->sortCallback = $query;
        }

        return $this;
    }

    /**
     * Set a custom sort query callback.
     *
     * @param  Closure  $callback  fn(Builder $query, string $direction): Builder
     */
    public function sortUsing(Closure $callback): static
    {
        $this->capabilities = $this->capabilities->add(Capability::Sortable);
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

        return trim($this->renderView('tables.columns.responsive', [
            'breakpoint' => $bp,
            'mobileContent' => $mobileContent,
            'desktopContent' => $desktopContent,
        ]));
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

        $content = $this->displayUsing
            ? (string) ($this->displayUsing)($state, $record)
            : $this->formatValue($state, $record);

        $description = $this->description !== null
            ? (is_callable($this->description) ? ($this->description)($record) : $this->description)
            : null;

        // Column owns state/config; the text partial owns all cell markup.
        return trim($this->renderView('tables.columns.text', [
            'content' => $content,
            'textClasses' => $this->getTextClasses(),
            'isHtml' => $this->html,
            'iconHtml' => $this->icon ? $this->renderIcon($this->icon) : '',
            'iconPosition' => $this->iconPosition ?? 'before',
            'url' => $this->getUrl($record),
            'openInNewTab' => $this->openUrlInNewTab,
            'copyable' => $this->copyable,
            'copyValue' => EnumResolver::scalar($state),
            'copyMessage' => $this->copyMessage ?? Trans::get('wire-table::messages.copied'),
            'tooltip' => $this->tooltip,
            'description' => $description,
            'descriptionPosition' => $this->descriptionPosition,
        ]));
    }

    public function canView(): bool
    {
        return $this->isAuthorized();
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

        // Aggregate columns: read from withCount/withSum attribute
        if ($this->isAggregate()) {
            $attr = $this->getAggregateAttribute();
            $value = $attr !== null ? $record->getAttribute($attr) : null;

            if ($this->formatStateUsing) {
                $value = ($this->formatStateUsing)($value, $record);
            }

            return $value ?? $this->default;
        }

        // fallback: resolveValue + formatStateUsing
        $value = $this->resolveValue($record);

        if ($this->formatStateUsing) {
            $value = ($this->formatStateUsing)($value, $record);
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
        // Enum- and array/JSON-cast attributes arrive as raw instances; normalise to a
        // display-safe value before stringifying so a plain Column over them never fatals.
        $value = EnumResolver::display($value);

        if ($value === null || $value === '') {
            // getPlaceholder() always resolves to a string (defaults to '-'); the
            // cast keeps the string return type without an unreachable ?? branch.
            return (string) $this->getPlaceholder();
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
            // 'muted' is a text treatment (lighter gray), not a palette color, so
            // it keeps its dedicated shade; every real color goes through the
            // canonical Foundation palette so hues stay consistent everywhere.
            $classes[] = $this->textColor === 'muted'
                ? 'text-gray-400 dark:text-gray-500'
                : self::getTextColorClasses($this->textColor);
        }

        return implode(' ', $classes);
    }

    /**
     * Render an icon SVG
     */
    protected function renderIcon(string $icon): string
    {
        $color = $this->color ? $this->getColorClass($this->color) : 'text-gray-400';

        return app(IconManager::class)->render($icon, 'w-4 h-4 inline-block', $color);
    }

    /**
     * Get the text color class for a palette color.
     *
     * Delegates to the canonical Foundation palette ({@see HasColor::getTextColorClasses()})
     * so columns, badges and the rest of the framework share one set of hues.
     */
    protected function getColorClass(string $color): string
    {
        return self::getTextColorClasses($color);
    }

    public function getUrl(Model $record): ?string
    {
        if ($this->urlCallback) {
            return ($this->urlCallback)($record);
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
            $content = ($this->mobileDisplayUsing)($state, $record, $this);

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
            $content = ($this->desktopDisplayUsing)($state, $record, $this);

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

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    // icon(), getIcon() and getIconPosition() come from Foundation\Concerns\HasIcon.

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
            return ($this->visibleCallback)();
        }

        return ! $this->hidden;
    }

    /**
     * Set a Gate ability required for inline editing of this column.
     */
    public function authorizeInline(?string $ability): static
    {
        $this->inlineEditAbility = $ability;

        return $this;
    }

    public function getInlineEditAbility(): ?string
    {
        return $this->inlineEditAbility;
    }

    /**
     * Check if the current user can inline-edit this column.
     */
    public function canInlineEdit(): bool
    {
        if (! $this->inlineEditAbility) {
            return true;
        }

        return Gate::allows($this->inlineEditAbility);
    }

    /**
     * @param  array<string, string>|class-string  $options
     */
    public function editable(bool $editable = true, string $type = 'text', array|string $options = []): static
    {
        $this->capabilities = $editable
            ? $this->capabilities->add(Capability::Editable)
            : $this->capabilities->remove(Capability::Editable);

        $this->editableType = $type;
        $this->editableOptions = EnumResolver::normalizeOptions($options);

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->hasCapability(Capability::Editable);
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
            return ($this->editableRules)($record);
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

    // size()/sm()/md()/lg()/getSize() come from Foundation\Concerns\HasSize and
    // now control the column's structural size. Text font-size moved to the
    // dedicated textSize() setter below (breaking change in v2).

    public function textSize(string $size): static
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

    public function textColor(string|Color $color): static
    {
        $this->textColor = $color instanceof Color ? $color->value : $color;

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
     * @param  array<string, string>|class-string  $options
     */
    public function filterable(bool $filterable = true, string $type = 'text', array|string $options = []): static
    {
        $this->capabilities = $filterable
            ? $this->capabilities->add(Capability::Filterable)
            : $this->capabilities->remove(Capability::Filterable);

        $this->filterType = $type;
        $this->filterOptions = EnumResolver::normalizeOptions($options);

        return $this;
    }

    /**
     * Configure as a select column filter.
     *
     * @param  array<string, string>|class-string  $options
     */
    public function filterAsSelect(array|string $options, ?string $placeholder = null): static
    {
        $this->capabilities = $this->capabilities->add(Capability::Filterable);
        $this->filterType = 'select';
        $this->filterOptions = EnumResolver::normalizeOptions($options);
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
        $this->capabilities = $this->capabilities->add(Capability::Filterable);
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
        $this->capabilities = $this->capabilities->add(Capability::Filterable);
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
        $this->capabilities = $this->capabilities->add(Capability::Filterable);
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
        $this->capabilities = $this->capabilities->add(Capability::Filterable);
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
        return $this->hasCapability(Capability::Filterable);
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
            return ($this->filterQueryCallback)($query, $value);
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
        // Crafted/stale state can deliver an array here; guard against
        // "Array to string conversion" in the LIKE/comparison branches.
        if (! is_scalar($value)) {
            return $query;
        }

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

    public function renderFilter(mixed $value = null): string
    {
        if (! $this->isFilterable()) {
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
