<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Enums\FilterOperator;

/** @phpstan-consistent-constructor */
class Filter implements Htmlable
{
    use HasAuthorization;

    /**
     * Macroable, for the reason `Table` and `BaseAction` already are: an
     * application or a package adds vocabulary to a class it does not own,
     * applied where the component is built. ADR 0030 named this as the missing
     * half of the extension story — the second-best path was absent everywhere
     * the first one was.
     */
    use Macroable;

    public string $name;

    public ?string $label = null;

    public ?string $column = null;

    public ?Closure $queryCallback = null;

    public mixed $default = null;

    public bool $hidden = false;

    public ?Closure $hiddenCallback = null;

    /**
     * True when {@see $hiddenCallback} came from {@see visible()} and its result
     * must be inverted. Tracked separately instead of coercing the closure so a
     * `visible(Closure)` condition is actually honoured (see {@see visible()}).
     */
    protected bool $hiddenCallbackInverts = false;

    public ?string $placeholder = null;

    public bool $multiple = false;

    /** @var string|Closure|null Custom indicator chip label (string or fn ($value, Filter)) */
    protected string|Closure|null $indicator = null;

    /** @var string|null Relationship name for related model attributes */
    protected ?string $relation = null;

    /** Whether this filter targets the table's sub-row relation instead of parent columns */
    protected bool $appliesToSubRows = false;

    /**
     * Base wire:model state path the render layer binds the filter's field(s)
     * under. The filter panel binds to `tableState.filters.{name}`; a column
     * header filter binds to `tableState.columnFilters.{name}` instead (see
     * Column::resolveFilter()). The `.{name}` (and `.{fieldName}` for
     * multi-field filters) is appended by the views.
     */
    protected string $statePathPrefix = 'tableState.filters';

    /**
     * Whether this filter renders in the compact inline (table header cell)
     * variant instead of the full filter-panel row.
     */
    protected bool $inline = false;

    /**
     * The comparisons the user may pick between, or null for none — the filter
     * then compares the one way it always has. See {@see operators()}.
     *
     * @var list<FilterOperator>|null
     */
    protected ?array $operators = null;

    /** The comparison a fresh filter starts on; null asks {@see defaultOperator()} of the subclass. */
    protected ?FilterOperator $initialOperator = null;

    /** The comparison read from the current state by {@see forState()}; null outside a query. */
    protected ?FilterOperator $activeOperator = null;

    /** Whether the control binds without `.live` — the panel applies on Apply (Table::deferFilters()). */
    protected bool $deferred = false;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->parseRelation($name);
    }

    private function parseRelation(string $name): void
    {
        if (Str::contains($name, '.')) {
            $parts = explode('.', $name);
            $this->relation = implode('.', array_slice($parts, 0, -1));
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /** Set the filter's display label (defaults to a humanised name). */
    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** Filter a different DB column than the filter name (defaults to the name). */
    public function column(?string $column): static
    {
        $this->column = $column;

        return $this;
    }

    /** Set a custom filter query; the Closure receives the Builder + value and must return the Builder. */
    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Let the user choose how this filter compares: `true` offers every operator
     * the filter type supports, a list offers those (enum cases or their values).
     */
    public function operators(array|bool $operators = true): static
    {
        if ($operators === false) {
            $this->operators = null;

            return $this;
        }

        $supported = $this->supportedOperators();
        $wanted = $operators === true
            ? $supported
            : array_values(array_filter(array_map(FilterOperator::resolve(...), $operators)));

        // Only what this filter type can actually do survives: a select filter
        // asked for "starts with" has nothing to start with.
        $this->operators = array_values(array_filter(
            $wanted,
            static fn (FilterOperator $operator): bool => in_array($operator, $supported, true),
        ));

        return $this;
    }

    /** The comparison the filter starts on before the user picks one. */
    public function defaultOperator(FilterOperator|string $operator): static
    {
        $this->initialOperator = FilterOperator::resolve($operator);

        return $this;
    }

    /**
     * Every operator this filter type can apply. The base filter compares for
     * equality only; the concrete filters widen it.
     *
     * @return list<FilterOperator>
     */
    public function supportedOperators(): array
    {
        return [];
    }

    /**
     * The operators the user is offered — empty when the filter has no picker.
     *
     * @return list<FilterOperator>
     */
    public function getOperators(): array
    {
        // Read against what the filter supports NOW, not when operators() was
        // called: `DateFilter::make()->operators()->range()` must not keep a
        // picker a range has no use for.
        $supported = $this->supportedOperators();

        return array_values(array_filter(
            $this->operators ?? [],
            static fn (FilterOperator $operator): bool => in_array($operator, $supported, true),
        ));
    }

    /** Whether the panel gives this filter a whole row of its grid. */
    public function spansFullWidth(): bool
    {
        return false;
    }

    /**
     * The value control a query-builder condition about this filter draws:
     * 'text', 'select', 'date' or 'boolean'.
     */
    public function builderInput(): string
    {
        return 'text';
    }

    /** How this filter words an operator — a date filter says "is before", not "is less than". */
    public function operatorLabel(FilterOperator $operator): string
    {
        return $operator->label();
    }

    /** Whether the filter draws an operator picker: there is more than one way to compare. */
    public function hasOperatorPicker(): bool
    {
        return count($this->getOperators()) > 1;
    }

    /** The comparison used when the state names none (or one this filter does not offer). */
    public function getDefaultOperator(): FilterOperator
    {
        $operator = $this->initialOperator ?? $this->baseOperator();
        $offered = $this->getOperators();

        return $offered === [] || in_array($operator, $offered, true) ? $operator : $offered[0];
    }

    /** The comparison a filter of this type makes when nobody chose one. */
    protected function baseOperator(): FilterOperator
    {
        return $this->multiple ? FilterOperator::In : FilterOperator::Equals;
    }

    /**
     * The operator the state asks for, if the filter offers it. A crafted URL or
     * a stale saved view naming another one gets the default, never a comparison
     * the developer did not offer.
     */
    public function operatorFromState(mixed $raw): FilterOperator
    {
        $requested = is_array($raw) ? FilterOperator::resolve(is_string($raw['operator'] ?? null) ? $raw['operator'] : null) : null;

        return $requested !== null && in_array($requested, $this->getOperators(), true)
            ? $requested
            : $this->getDefaultOperator();
    }

    /**
     * This filter as the given state configures it — a copy carrying the chosen
     * operator, so the stored filter (shared by render and planning) is never
     * changed by one request's state.
     */
    public function forState(mixed $raw): static
    {
        if (! $this->hasOperatorPicker()) {
            return $this;
        }

        $copy = clone $this;
        $copy->activeOperator = $this->operatorFromState($raw);

        return $copy;
    }

    /** The comparison in force: the one {@see forState()} read, else the default. */
    public function getActiveOperator(): FilterOperator
    {
        return $this->activeOperator ?? $this->getDefaultOperator();
    }

    /** Bind the control without `.live`; set by the table from Table::deferFilters(). */
    public function deferred(bool $deferred = true): static
    {
        $this->deferred = $deferred;

        return $this;
    }

    public function isDeferred(): bool
    {
        return $this->deferred;
    }

    /**
     * The `wire:model` attribute name the control binds with: live (debounced
     * for typing) unless the panel is deferred, when the value waits for Apply.
     */
    public function wireModelDirective(int $debounce = 0): string
    {
        if ($this->deferred) {
            return 'wire:model';
        }

        return $debounce > 0 ? 'wire:model.live.debounce.'.$debounce.'ms' : 'wire:model.live';
    }

    /**
     * Get the relationship name.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    /** Set the filter's initial value (applied until the user changes it). */
    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /** Show the filter only when the condition is true (a bool or a Closure). */
    public function visible(bool|Closure $visible = true): static
    {
        if ($visible instanceof Closure) {
            // Store and invert on read — `! $visible` would coerce the Closure to
            // a bool (always false) and silently discard the condition.
            $this->hiddenCallback = $visible;
            $this->hiddenCallbackInverts = true;

            return $this;
        }

        return $this->hidden(! $visible);
    }

    /** Hide the filter when the condition is true — the inverse of `visible()`. */
    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
            $this->hiddenCallbackInverts = false;
        } else {
            // A literal state supersedes any previously registered closure so the
            // last call wins predictably.
            $this->hidden = $hidden;
            $this->hiddenCallback = null;
            $this->hiddenCallbackInverts = false;
        }

        return $this;
    }

    /** Set the placeholder / "all" option label for the filter control. */
    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /** Let the filter accept several values at once (renders a multi-select → `whereIn`). */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Customize the indicator chip label shown while this filter is active.
     *
     * Accepts a fixed string or a closure receiving the unwrapped filter
     * value and the filter instance. Returning null/'' from the closure
     * hides the chip.
     */
    public function indicator(string|Closure|null $indicator): static
    {
        $this->indicator = $indicator;

        return $this;
    }

    /**
     * Indicator chip label for the given raw filter state, or null when
     * the filter is inactive (no chip).
     */
    public function getIndicator(mixed $raw): ?string
    {
        $value = $this->extractValue($raw);
        $operator = $this->hasOperatorPicker() ? $this->operatorFromState($raw) : null;
        $valueLabel = $operator !== null && ! $operator->requiresValue()
            ? ''
            : $this->getIndicatorValueLabel($value);

        if ($valueLabel === null) {
            return null;
        }

        if ($this->indicator instanceof Closure) {
            $label = ($this->indicator)($value, $this);

            return ($label === null || $label === '') ? null : (string) $label;
        }

        if (is_string($this->indicator)) {
            return $this->indicator;
        }

        // With a picker the chip says how it compares — "Email: does not contain
        // gmail", "Phone: is empty" — since "Email: gmail" would read as a match.
        if ($operator !== null) {
            return trim($this->getLabel().': '.$this->operatorLabel($operator).' '.$valueLabel);
        }

        return $this->getLabel().': '.$valueLabel;
    }

    /**
     * Human-readable form of the active filter value, or null when the
     * value means "inactive". Concrete filters override this to map raw
     * values to option labels, ranges, formatted dates, etc.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $values = array_filter($value, fn ($v) => $v !== null && $v !== '');

            return $values === [] ? null : implode(', ', array_map(
                static fn ($v): string => (string) EnumResolver::label($v),
                $values,
            ));
        }

        return (string) EnumResolver::label($value);
    }

    /**
     * Map of state sub-paths to URL parameter suffixes for query-string
     * persistence (Table::queryString()). The base {value: x} shape maps
     * to a single unsuffixed parameter; multi-field filters override this
     * (e.g. NumberRangeFilter → ['min' => '_min', 'max' => '_max']).
     *
     * @return array<string, string>
     */
    public function getQueryStringFields(): array
    {
        return $this->hasOperatorPicker()
            ? ['value' => '', 'operator' => '_op']
            : ['value' => ''];
    }

    /**
     * Scope this filter to the table's sub-row relation (Table::subRows()).
     *
     * The filter then constrains child records instead of parent columns:
     * parents are reduced to those having at least one matching child
     * (whereHas), displayed sub-rows are limited to matching children, and
     * rollup aggregates (->sums(), ->counts(), …) only count matching children
     * — so footer grand totals reflect the filtered sub-rows.
     *
     * A ->query() callback on a sub-row scoped filter receives the CHILD
     * query builder, not the parent one.
     *
     * Ignored (treated as a regular parent filter) when the table has no
     * sub-row relation configured.
     */
    public function subRows(bool $subRows = true): static
    {
        $this->appliesToSubRows = $subRows;

        return $this;
    }

    public function appliesToSubRows(): bool
    {
        return $this->appliesToSubRows;
    }

    /**
     * Whether this filter must always be applied via apply() instead of the
     * QueryPlanner. Concrete filters whose constraint cannot be expressed as a
     * simple column/operator/value definition (e.g. DateFilter month mode)
     * override this.
     */
    public function bypassesPlanner(): bool
    {
        return false;
    }

    /**
     * Set the base wire:model state path the render layer binds under.
     * Defaults to `tableState.filters`; column header filters set
     * `tableState.columnFilters` so header state stays under its own key.
     */
    public function statePathPrefix(string $prefix): static
    {
        $this->statePathPrefix = $prefix;

        return $this;
    }

    public function getStatePathPrefix(): string
    {
        return $this->statePathPrefix;
    }

    /**
     * The full base wire:model path for this filter's field group, e.g.
     * `tableState.columnFilters.status`. Views append `.{fieldName}` for
     * multi-field filters, or bind directly for single-field ones.
     */
    public function getStatePath(): string
    {
        return $this->statePathPrefix.'.'.$this->name;
    }

    /**
     * Render this filter in the compact inline (table header cell) variant.
     */
    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;

        return $this;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    /**
     * The compact inline (table header cell) Blade partial for this filter.
     * Concrete filters override to select their control; the base is a text
     * input. Distinct header controls stay separate reusable surfaces (per the
     * design-system guidance) while all sharing the canonical Filter data.
     */
    public function inlineView(): string
    {
        return 'tables.columns.partials.filter-text';
    }

    /**
     * Whether the inline control is a dropdown (reserves room for the shared
     * chevron overlay). Select / boolean filters override to true.
     */
    public function isSelectLike(): bool
    {
        return false;
    }

    /**
     * Convert an active filter value into planner FilterDefinition(s), so the
     * constraint is expressed through the canonical QueryPlanner alongside
     * column/relation metadata (joins, qualification) instead of a bespoke
     * post-planner pass.
     *
     * Returns [] when the filter cannot be expressed as a simple
     * column/operator/value clause — a custom ->query() callback, a
     * planner-incompatible constraint (bypassesPlanner()), or a multi-field
     * array on a non-multiple filter (ranges). Those keep flowing through
     * apply(). Concrete filters override to emit LIKE / IN / BETWEEN clauses.
     *
     * @return array<int, FilterDefinition>
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        if ($this->queryCallback !== null || $this->bypassesPlanner()) {
            return [];
        }

        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        // A keyed array on a non-multiple filter is a range/compound shape the
        // planner can't express as one clause — let apply() handle it.
        if (is_array($value) && ! $this->multiple) {
            return [];
        }

        $operator = ($this->multiple && is_array($value)) ? 'in' : '=';

        return [FilterDefinition::make(
            column: $this->getColumn(),
            operator: $operator,
            value: $value,
        )];
    }

    /**
     * Canonical owner of relation wrapping for filter constraints: apply the
     * given column-level condition directly, or wrapped in a `whereHas` against
     * the parsed relation (matching parents with at least one qualifying child).
     * Concrete filters express only the per-column constraint and delegate the
     * relation dimension here, so relation handling lives in one place instead
     * of being re-encoded in every subclass.
     *
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>, string): mixed  $condition  fn ($q, $column)
     * @return Builder<Model>
     */
    protected function applyToColumn(Builder $query, Closure $condition): Builder
    {
        if ($this->relation !== null) {
            $attribute = (string) $this->getRelationshipAttribute();

            return $query->whereHas(
                $this->relation,
                static fn (Builder $q) => $condition($q, $attribute),
            );
        }

        $condition($query, $this->getColumn());

        return $query;
    }

    /**
     * Constrain the query by one comparison on this filter's column — the one
     * place an operator meets a column, a relation or neither. Across a relation
     * a negative operator means "no related record matches": `whereDoesntHave`
     * with the positive comparison, never `whereHas` with the negative one,
     * which keeps a parent that has one matching child and one other.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyOperator(Builder $query, FilterOperator $operator, mixed $value, bool $blankIsEmpty = false): Builder
    {
        if ($this->relation !== null) {
            $attribute = (string) $this->getRelationshipAttribute();

            if ($operator === FilterOperator::IsEmpty) {
                return $query->whereDoesntHave($this->relation, static fn (Builder $q) => FilterOperator::IsNotEmpty->constrain($q, $attribute, null, $blankIsEmpty));
            }

            if ($operator->isNegative()) {
                return $query->whereDoesntHave($this->relation, static fn (Builder $q) => $operator->positive()->constrain($q, $attribute, $value, $blankIsEmpty));
            }

            return $query->whereHas($this->relation, static fn (Builder $q) => $operator->constrain($q, $attribute, $value, $blankIsEmpty));
        }

        $operator->constrain($query, $this->getColumn(), $value, $blankIsEmpty);

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $this->normalizeValue($value), $value);
        }

        // If filter has a relation, it should be handled by WithTable::applyFilters
        // This is kept for backwards compatibility when used directly
        if ($this->relation) {
            $relation = $this->relation;
            $attribute = $this->relatedAttribute();

            return $query->whereHas($relation, function ($q) use ($attribute, $value) {
                if (is_array($value)) {
                    $q->whereIn($attribute, $value);
                } else {
                    $q->where($attribute, $value);
                }
            });
        }

        $column = $this->getColumn();

        if ($this->multiple && is_array($value)) {
            /** @var Builder<Model> */
            return $query->whereIn($column, $value);
        }

        /** @var Builder<Model> */
        return $query->where($column, $value);
    }

    /**
     * Get the relationship attribute of the column.
     */
    public function getRelationshipAttribute(): ?string
    {
        return $this->relation ? $this->relatedAttribute() : null;
    }

    /** The last segment of a dotted name: the column on the related model. */
    protected function relatedAttribute(): string
    {
        return Str::afterLast($this->name, '.');
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * The Blade view that renders this filter's control(s). Subclasses whose
     * only difference is the template override this instead of {@see render()}
     * (parallels how the concrete filters differ only by view name).
     */
    protected function filterView(): string
    {
        return 'tables.filters.form-field';
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView($this->filterView()), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }

    /**
     * Extract the value to compare against from raw filter state.
     *
     * Single-field filters store state as {value: 'x'} and unwrap to 'x'.
     * Multi-field filters (NumberRange {min, max}, DateFilter range {from, to})
     * keep the full array. Plain scalar inputs are returned as-is so programmatic
     * callers (and tests) can pass raw values without wrapping.
     */
    public function extractValue(mixed $raw): mixed
    {
        // "is empty" / "is not empty" are complete without a value: the filter
        // is on, and `true` is what says so to every emptiness check downstream.
        if ($this->hasOperatorPicker() && is_array($raw) && array_key_exists('operator', $raw)
            && ! $this->operatorFromState($raw)->requiresValue()) {
            return true;
        }

        if (is_array($raw) && array_key_exists('value', $raw)) {
            return $raw['value'];
        }

        return $raw;
    }

    /**
     * Coerce an extracted value into the canonical form the query layer — and a
     * ->query() callback — should receive.
     *
     * Identity by default: most filters submit the value they mean. Concrete
     * filters override when their UI state is a transport stand-in for another
     * type (TernaryFilter's 'true'/'false' option keys for a boolean), so every
     * consumer sees one canonical value instead of re-decoding the transport
     * form — which is how a callback ended up branching on the truthy string
     * 'false'.
     */
    public function normalizeValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Canonical invocation of the ->query() callback.
     *
     * The callback receives the normalized value; the raw submitted state is
     * passed as a third argument for callbacks that need the transport form
     * (a closure that does not declare it simply ignores it).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyQueryCallback(Builder $query, mixed $value, mixed $raw): Builder
    {
        if ($this->queryCallback === null) {
            return $query;
        }

        /** @var Builder<Model> */
        return ($this->queryCallback)($query, $value, $raw);
    }

    /**
     * The header-filter state that keeps — or, with `$exclude`, drops — the
     * records holding exactly `$value`: what "Only this value" / "Exclude this
     * value" on a cell write. Null when this filter cannot say it (exclusion
     * needs a negative operator on offer).
     */
    public function stateForCellValue(mixed $value, bool $exclude = false): mixed
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        $value = $value instanceof \BackedEnum ? $value->value : $value;

        if (! is_scalar($value)) {
            return null;
        }

        $stored = $this->multiple ? [(string) $value] : (string) $value;

        if ($this->hasOperatorPicker()) {
            $wanted = $this->cellValueOperator($exclude);

            return $wanted === null ? null : ['value' => $stored, 'operator' => $wanted->value];
        }

        // Without a picker a filter can only keep a value, never drop it.
        return $exclude ? null : $stored;
    }

    /** Whether "Exclude this value" can be offered: a negative operator is on the menu. */
    public function canExcludeCellValue(): bool
    {
        return $this->cellValueOperator(exclude: true) !== null;
    }

    /**
     * Whether "Only this value" can be offered: without a picker always; with
     * one, only when it offers an exact match ("is" or "is any of") — a text
     * filter limited to "contains" cannot say "exactly this".
     */
    public function canFilterByCellValue(): bool
    {
        return ! $this->hasOperatorPicker() || $this->cellValueOperator(exclude: false) !== null;
    }

    /**
     * The offered operator that keeps (or drops) exactly one value: "is" / "is
     * not", or "is any of" / "is none of" — a single-choice select offers only
     * the latter pair and must still answer.
     */
    protected function cellValueOperator(bool $exclude): ?FilterOperator
    {
        $candidates = $exclude
            ? [FilterOperator::NotEquals, FilterOperator::NotIn]
            : [FilterOperator::Equals, FilterOperator::In];

        if ($this->multiple) {
            $candidates = array_reverse($candidates);
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $this->getOperators(), true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The state a header filter starts from and is cleared back to: `[]` for a
     * multiple choice, null for one value. A filter whose control binds several
     * keys (a range's from/to) answers with those keys present, because a
     * binding into a key of null throws in the browser.
     */
    public function emptyState(): mixed
    {
        return $this->multiple ? [] : null;
    }

    /**
     * Inverse of extractValue(): wrap a default/programmatic value into the
     * state shape used by the form-field wire:model paths. Multi-field filters
     * override this since their state is already a keyed array.
     */
    public function wrapValue(mixed $value): mixed
    {
        $wrapped = is_array($value) && array_key_exists('value', $value) ? $value : ['value' => $value];

        // A filter with a picker binds the operator beside the value, and the
        // slot has to exist before the first render for the binding to hold.
        if ($this->hasOperatorPicker() && ! array_key_exists('operator', $wrapped)) {
            $wrapped['operator'] = $this->getDefaultOperator()->value;
        }

        return $wrapped;
    }

    /**
     * Resolve filter view name with package namespace support.
     */
    protected function resolveFilterView(string $defaultView): string
    {
        $namespacedView = "wire-table::{$defaultView}";

        if (view()->exists($namespacedView)) {
            return $namespacedView;
        }

        return $defaultView;
    }

    public function canView(): bool
    {
        if ($this->isHidden()) {
            return false;
        }

        return $this->isAuthorized();
    }

    public function isHidden(): bool
    {
        // Filters are table-level controls with no per-record context, so their
        // visibility Closure is `fn (): bool`. A closure that (mistakenly) requires
        // an argument degrades to the static default instead of fataling — there is
        // nothing to pass it. Mirrors the guarded resolution in the action layer.
        if ($this->hiddenCallback !== null
            && (new \ReflectionFunction($this->hiddenCallback))->getNumberOfRequiredParameters() === 0) {
            $result = (bool) ($this->hiddenCallback)();

            return $this->hiddenCallbackInverts ? ! $result : $result;
        }

        return $this->hidden;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? Trans::get('wire-table::messages.select_placeholder');
    }

    /**
     * Return the form field component(s) used to render this filter.
     *
     * Wire:model binding paths are relative to tableState.filters.{name}.
     * The base implementation returns a single text input named "value",
     * matching the {value: 'x'} state shape used by extractValue().
     *
     * @return array<int, mixed>
     */
    public function getFormFields(): array
    {
        return [
            TextInput::make('value')
                ->placeholder($this->placeholder ?? ''),
        ];
    }
}
