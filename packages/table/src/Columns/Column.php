<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Components\DataComponent;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasDefault;
use NyonCode\WireCore\Foundation\Concerns\HasFontWeight;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Enums\FontWeight;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Concerns\CanBeFiltered;
use NyonCode\WireTable\Concerns\CanBeSummarized;
use NyonCode\WireTable\Concerns\HasResponsive;
use NyonCode\WireTable\Concerns\HasView;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Support\FilterControl;
use NyonCode\WireTable\Support\MobileSlot;

/** @phpstan-consistent-constructor */
class Column extends DataComponent implements Htmlable
{
    // HasVisibility composes HasAuthorization — an unauthorized column is not a
    // visible one — so it is not listed separately, matching core's Component.
    // A column is never *disabled*, so CanBeDisabled is deliberately not here.
    use CanBeFiltered;
    use CanBeSummarized;
    use HasColor;
    use HasDefault;
    use HasFontWeight;
    use HasIcon;
    use HasPlaceholder;
    use HasResponsive;
    use HasSize;
    use HasTooltip;
    use HasView;
    use HasVisibility;

    // Note: $sortable and $searchable booleans removed in v2.
    // Use capabilities as single source of truth via isSortable()/isSearchable().

    /** @var array<int, string> Explicit DB columns to search (Filament-style: searchable(['first_name', 'last_name'])) */
    protected array $searchColumns = [];

    /** @var Closure|null Custom search query callback: fn(Builder, string) => */
    protected ?Closure $searchCallback = null;

    /** @var Closure|null Custom sort query callback: fn(Builder, string) => */
    protected ?Closure $sortCallback = null;

    // Responsive visibility ($visibleFrom/$hiddenFrom, visibleFrom()/hiddenFrom(),
    // getResponsiveClasses()) is owned by the HasResponsive trait.

    /** @var Closure|null Custom display for mobile view */
    protected ?Closure $mobileDisplayUsing = null;

    /** @var Closure|null Custom display for desktop view */
    protected ?Closure $desktopDisplayUsing = null;

    /** @var string Mobile breakpoint for display switching */
    protected string $mobileBreakpoint = 'md';

    /** Explicit stacked-card slot; null lets MobileCard derive one. */
    protected ?MobileSlot $mobileSlot = null;

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

    // $default comes from Foundation\Concerns\HasDefault.
    // $tooltip comes from Foundation\Concerns\HasTooltip.

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

    /** @var Closure|null Per-record cell visibility (redact a single cell by row) */
    protected ?Closure $visibleForRecordCallback = null;

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

    /** @var bool Whether a fill handle drag may write this column (editable columns only) */
    protected bool $fillable = true;

    // $filter and the filterable()/filterAs*() API come from CanBeFiltered.
    // Note: the $filterable boolean was removed in v2 — use capabilities.

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

    /** @var array<int, string> */
    protected array $eagerLoadRelations = [];

    /**
     * Eager-load relations this column touches ONLY inside a closure
     * (`displayUsing`/`url`/`color`), which have no column path for the query planner
     * to discover — without this hint they lazy-load once per row (an N+1 the
     * framework cannot introspect out of a closure).
     *
     *   TextColumn::make('company')
     *       ->displayUsing(fn ($state, $record) => $record->company->name)
     *       ->loadRelations('company');
     *
     * @param  string|array<int, string>  $relations
     */
    public function loadRelations(string|array $relations): static
    {
        $this->eagerLoadRelations = array_values(array_unique(
            [...$this->eagerLoadRelations, ...(array) $relations]
        ));

        return $this;
    }

    /** @return array<int, string> */
    public function getEagerLoadRelations(): array
    {
        return $this->eagerLoadRelations;
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
    /**
     * Check if the column is hidden.
     */
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
     * Place this column in a named slot of the stacked mobile card, instead of
     * letting {@see MobileCard} derive one from column order and alignment.
     */
    public function mobileSlot(MobileSlot|string $slot): static
    {
        $this->mobileSlot = MobileSlot::resolve($slot);

        return $this;
    }

    /**
     * The identifier the card is recognised by.
     */
    public function mobileTitle(): static
    {
        return $this->mobileSlot(MobileSlot::Title);
    }

    /**
     * The supporting line under the title.
     */
    public function mobileSubtitle(): static
    {
        return $this->mobileSlot(MobileSlot::Subtitle);
    }

    /**
     * The figure the list is read for — set right on the title line.
     */
    public function mobileMetric(): static
    {
        return $this->mobileSlot(MobileSlot::Metric);
    }

    /**
     * A status or qualifier, shown beside the title block rather than as a
     * label/value pair.
     */
    public function mobileMeta(): static
    {
        return $this->mobileSlot(MobileSlot::Meta);
    }

    /**
     * Keep this column in the label/value grid, whatever derivation would pick.
     */
    public function mobileDetail(): static
    {
        return $this->mobileSlot(MobileSlot::Detail);
    }

    public function getMobileSlot(): ?MobileSlot
    {
        return $this->mobileSlot;
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
     * @param  string|Breakpoint  $breakpoint  sm, md, lg, xl, 2xl
     */
    public function mobileBreakpoint(string|Breakpoint $breakpoint): static
    {
        $this->mobileBreakpoint = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

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

        $bp = Breakpoint::resolve($this->mobileBreakpoint);
        $mobileContent = $this->renderMobileCell($record);
        $desktopContent = $this->renderDesktopCell($record);

        // If content is the same, no need for wrappers
        if ($mobileContent === $desktopContent) {
            return $mobileContent;
        }

        return trim($this->renderView('tables.columns.responsive', [
            'mobileClass' => $bp->hiddenAtClass(),
            'desktopClass' => $bp->inlineFromClass(),
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
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
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
            'iconHtml' => $this->iconHtmlFor($record),
            'iconPosition' => $this->iconPosition ?? 'before',
            'url' => $this->getUrl($record),
            'openInNewTab' => $this->openUrlInNewTab,
            'copyable' => $this->copyable,
            'copyValue' => EnumResolver::scalar($state),
            // Only a copyable cell uses this; resolving the translated default for
            // every non-copyable cell (every row) was wasted work. When copyable,
            // copyable() has already resolved $copyMessage, so the fallback is a guard.
            'copyMessage' => $this->copyable ? ($this->copyMessage ?? Trans::get('wire-table::messages.copied')) : null,
            'tooltip' => $this->tooltip,
            'description' => $description,
            'descriptionPosition' => $this->descriptionPosition,
        ]));
    }

    /**
     * §7 proof-of-concept: an Htmlable cell skeleton.
     *
     * For a plain display column the text partial's per-record variation is *only*
     * the content string — classes, icon, static tooltip/description are column-static.
     * So the partial is rendered ONCE into a skeleton with a content placeholder, and
     * every row splices its escaped state in — a string op, not a `view()->render()`.
     * Falls back to {@see renderCell()} when a per-record structural bit is present
     * (url / copy / description-closure), which a single skeleton cannot splice.
     */
    private const CELL_TOKEN = 'ᐊWIRE_CELL_a3f9e1ᐊ';

    private ?string $cellSkeleton = null;

    public function renderCellFast(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        // Subclasses that override renderCell render a different view than the text
        // skeleton, and non-skeletonable columns vary structurally per row — both
        // fall back to the full, byte-identical render.
        if (! $this->supportsCellSkeleton() || ! $this->isCellSkeletonable()) {
            return $this->renderCell($record);
        }

        $state = $this->getState($record);
        $content = $this->displayUsing
            ? (string) ($this->displayUsing)($state, $record)
            : $this->formatValue($state, $record);

        return trim(str_replace(
            self::CELL_TOKEN,
            $this->html ? $content : e($content),
            $this->cellSkeleton(),
        ));
    }

    /** @var array<class-string, bool> */
    private static array $skeletonSupport = [];

    /**
     * The text skeleton is only correct when this column renders through the base
     * `tables.columns.text` path — i.e. it has NOT overridden renderCell (Badge/Icon/…
     * render their own view). Resolved once per class via reflection, then cached.
     */
    private function supportsCellSkeleton(): bool
    {
        return self::$skeletonSupport[static::class] ??=
            (new \ReflectionMethod($this, 'renderCell'))->getDeclaringClass()->getName() === self::class;
    }

    /**
     * Skeletonable = the only per-record value is the content. A per-record url,
     * copy affordance, or description-closure changes structure row to row.
     */
    private function isCellSkeletonable(): bool
    {
        return $this->urlCallback === null
            && ! $this->copyable
            && ! ($this->description instanceof Closure)
            // A closure icon is per-record, so the cell is not fully static.
            && ! ($this->icon instanceof Closure);
    }

    private function cellSkeleton(): string
    {
        return $this->cellSkeleton ??= trim($this->renderView('tables.columns.text', [
            'content' => self::CELL_TOKEN,
            'textClasses' => $this->getTextClasses(),
            // Build raw so the token is not escaped; the per-row splice escapes state.
            'isHtml' => true,
            // A closure icon is per-record and excluded from the skeleton
            // (isCellSkeletonable), so here $this->icon is only ever a literal.
            'iconHtml' => $this->iconHtmlFor(null),
            'iconPosition' => $this->iconPosition ?? 'before',
            'url' => null,
            'openInNewTab' => $this->openUrlInNewTab,
            'copyable' => false,
            'copyValue' => null,
            'copyMessage' => null,
            'tooltip' => $this->tooltip,
            'description' => is_string($this->description) ? $this->description : null,
            'descriptionPosition' => $this->descriptionPosition,
        ]));
    }

    public function canView(): bool
    {
        return $this->isAuthorized();
    }

    /**
     * Show or hide this column's cell per record — e.g. redact salary/margin on
     * some rows. Distinct from {@see canView()}: that is the column's *structural*
     * presence (evaluated once, without a record, and consulted by the header,
     * column toggle, export, …), whereas this runs at cell render with the row's
     * record. The callback receives the record (and this column).
     *
     *   ->visibleForRecord(fn ($record) => auth()->user()->can('viewSalary', $record))
     */
    public function visibleForRecord(Closure $callback): static
    {
        $this->visibleForRecordCallback = $callback;

        return $this;
    }

    /**
     * Whether this column's cell is visible for the given record. Structural
     * visibility ({@see canView()}) is checked separately by renderCell.
     */
    public function isVisibleForRecord(Model $record): bool
    {
        if ($this->visibleForRecordCallback === null) {
            return true;
        }

        return (bool) ($this->visibleForRecordCallback)($record, $this);
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

    /**
     * The column's underlying value, before any display formatting.
     *
     * The raw twin of {@see getState()}: where getState() applies
     * formatStateUsing + the default and reads through Eloquent accessors, this
     * reads the stored attribute straight — a rollup column its computed
     * withCount/withSum attribute (falling back to the column name), a dotted
     * name walks the relation, everything else is a direct attribute. It is the
     * value an export writes, so exporters delegate here instead of reaching into
     * the column's aggregate internals themselves; enum/JSON display-normalisation
     * stays a format concern of the caller.
     */
    public function getRawState(Model $record): mixed
    {
        $name = $this->getName();

        if ($this->isAggregate()) {
            $attribute = $this->getAggregateAttribute() ?? $name;

            return $record->getAttribute($attribute) ?? $record->getAttribute($name);
        }

        if (Str::contains($name, '.')) {
            return data_get($record, $name);
        }

        return $record->getAttribute($name);
    }

    /**
     * What an empty cell shows.
     *
     * Distinct from `placeholder()`, which is the hint an *input* shows while it
     * is empty. The two only looked like one concept because `getPlaceholder()`
     * used to hard-code a `-` fallback, so it could never answer null — which is
     * how `TextInputColumn` came to offer `-` to its input as a hint.
     */
    public function getEmptyCellText(): string
    {
        return $this->getPlaceholder() ?? '-';
    }

    public function formatValue(mixed $value, Model $record): string
    {
        // Enum- and array/JSON-cast attributes arrive as raw instances; normalise to a
        // display-safe value before stringifying so a plain Column over them never fatals.
        $value = EnumResolver::display($value);

        if ($value === null || $value === '') {
            return $this->getEmptyCellText();
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

    /** Truncate the displayed text to at most N characters (adds an ellipsis); null removes the limit. */
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
            $classes[] = HasFontWeight::getFontWeightClasses($this->textWeight);
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
     * Resolve the column icon to its rendered SVG for a given record.
     *
     * The icon may be a per-record Closure ({@see HasIcon::icon()}); it is
     * resolved with the record (evaluated closures may also return an Icon enum),
     * so a closure icon can never reach renderIcon(string) raw. Passing a null
     * record (the shared skeleton path) resolves only a literal icon — closure
     * icons are excluded from the skeleton by isCellSkeletonable().
     */
    private function iconHtmlFor(?Model $record): string
    {
        $icon = $this->icon instanceof Closure
            ? ($record !== null ? $this->evaluate($this->icon, ['record' => $record]) : null)
            : $this->icon;

        $icon = $icon instanceof Icon ? $icon->value() : $icon;

        return is_string($icon) && $icon !== '' ? $this->renderIcon($icon) : '';
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
    public function alignment(string|Alignment $alignment): static
    {
        $this->alignment = $alignment instanceof Alignment ? $alignment->value : $alignment;

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

    /**
     * Canonical literal Tailwind text-alignment class for this column, so the
     * view consumes a scannable utility instead of interpolating `text-{$align}`.
     */
    public function getAlignmentClass(): string
    {
        return Alignment::resolve($this->alignment ?? 'left')->textClass();
    }

    /** Transform the raw cell value before display; the Closure receives the state and returns the formatted value. */
    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    /** Replace the rendered cell entirely; the Closure receives `$state, $record` and returns the display value (string or Htmlable). */
    public function displayUsing(Closure $callback): static
    {
        $this->displayUsing = $callback;

        return $this;
    }

    /** Make the cell click-to-copy, with an optional confirmation message. */
    public function copyable(bool $copyable = true, ?string $copyMessage = null): static
    {
        $this->copyable = $copyable;
        $this->copyMessage = $copyMessage ?? Trans::get('wire-table::messages.copied');

        return $this;
    }

    /** Set the click-to-copy confirmation message. */
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

    /** Add extra HTML attributes to the cell (a raw attribute string). */
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
     * Set extra HTML attributes merged onto the column's header cell.
     *
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

    /** Let the cell text wrap onto multiple lines instead of truncating to one. */
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

    /** Prepend static text before the cell value. */
    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /** Append static text after the cell value. */
    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    /** Turn the cell into a link; the Closure receives `$record` and returns the URL (optionally opening in a new tab). */
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

    /** Set one fixed cell color for every row (a palette name or `Color` enum). For per-row color use a `BadgeColumn` with `colorUsing()`. */
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

    /** Render the cell value as raw HTML instead of escaped text (trusted content only). */
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
     * Make the column inline-editable, choosing the editor type and its options.
     *
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

    /**
     * Whether a fill may write this column, when the table offers a fill handle.
     *
     * Editable columns are fillable by default — a cell you can type into is one
     * you can drag down. Turn it off for a column where repeating one value is
     * meaningless or dangerous (a unique code, an invoice number).
     *
     * Example:
     *   TextInputColumn::make('invoice_number')->fillable(false);
     */
    public function fillable(bool $condition = true): static
    {
        $this->fillable = $condition;

        return $this;
    }

    public function isFillable(): bool
    {
        return $this->isEditable() && $this->fillable;
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

    /** Validation rules for the inline-editable cell; the Closure receives `$record` and returns a rules array. */
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

    /** Persist an inline edit with a custom callback (`$record, $value`) instead of the default attribute write. */
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

    /** Set the cell text size on the Tailwind text scale (distinct from `size()`, which is the structural size). */
    public function textSize(string $size): static
    {
        $this->textSize = $size;

        return $this;
    }

    public function getTextSize(): ?string
    {
        return $this->textSize;
    }

    /** Set the cell font weight (a `FontWeight` enum or keyword like `semibold`). */
    public function weight(string|FontWeight $weight): static
    {
        $this->textWeight = $weight instanceof FontWeight ? $weight->value : $weight;

        return $this;
    }

    public function getTextWeight(): ?string
    {
        return $this->textWeight;
    }

    // Column filtering methods

    /** Set the cell text color (a palette name or `Color` enum). */
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
     * Render this column's compact inline filter control.
     *
     * @param  string|null  $statePath  Where the control binds in the component
     *                                  state. Defaults to the main-table column
     *                                  filter slot; the sub-row filter bar passes
     *                                  `tableState.rows.subRowFilters.<name>` so
     *                                  its inputs write there instead of silently
     *                                  filtering the parent table.
     */
    public function renderFilter(mixed $value = null, ?string $statePath = null): string
    {
        $filter = $this->resolveFilter();
        if ($filter === null || ! $filter->canView()) {
            return '';
        }

        // The Filter owns its compact inline (header-cell) view; the shared
        // control style resolves the chevron variant for select-like filters.
        $viewName = $filter->inlineView();
        $namespacedView = "wire-table::$viewName";
        $resolvedView = view()->exists($namespacedView) ? $namespacedView : $viewName;

        return view($resolvedView, [
            'column' => $this,
            'filter' => $filter,
            'value' => $value,
            'statePath' => $statePath ?? 'tableState.columnFilters.'.$this->getName(),
            'controlClasses' => FilterControl::classes(),
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
