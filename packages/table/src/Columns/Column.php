<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Components\DataComponent;
use NyonCode\WireCore\Core\Query\Contracts\HasSearchColumns;
use NyonCode\WireCore\Core\Query\Contracts\HasSearchValueType;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\CanBeCopyable;
use NyonCode\WireCore\Foundation\Concerns\FormatsStateUsing;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasDefault;
use NyonCode\WireCore\Foundation\Concerns\HasFontWeight;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Concerns\CanBeEdited;
use NyonCode\WireTable\Concerns\CanBeFiltered;
use NyonCode\WireTable\Concerns\CanBeSearchable;
use NyonCode\WireTable\Concerns\CanBeSorted;
use NyonCode\WireTable\Concerns\CanBeSummarized;
use NyonCode\WireTable\Concerns\CanBeTruncated;
use NyonCode\WireTable\Concerns\HasAggregate;
use NyonCode\WireTable\Concerns\HasAlignment;
use NyonCode\WireTable\Concerns\HasDescription;
use NyonCode\WireTable\Concerns\HasMobileSlot;
use NyonCode\WireTable\Concerns\HasResponsive;
use NyonCode\WireTable\Concerns\HasTextStyling;
use NyonCode\WireTable\Concerns\HasView;
use NyonCode\WireTable\Concerns\HasWidth;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Support\FilterControl;

/** @phpstan-consistent-constructor */
class Column extends DataComponent implements HasSearchColumns, HasSearchValueType, Htmlable
{
    // HasVisibility composes HasAuthorization — an unauthorized column is not a
    // visible one — so it is not listed separately, matching core's Component.
    // A column is never *disabled*, so CanBeDisabled is deliberately not here.
    use CanBeCopyable;
    use CanBeEdited;
    use CanBeFiltered;
    use CanBeSearchable;
    use CanBeSorted;
    use CanBeSummarized;
    use CanBeTruncated;
    use FormatsStateUsing;
    use HasAggregate;
    use HasAlignment;
    use HasColor;
    use HasDefault;
    use HasDescription;
    use HasFontWeight;
    use HasIcon;
    use HasMobileSlot;
    use HasPlaceholder;
    use HasResponsive;
    use HasSize;
    use HasTextStyling;
    use HasTooltip;
    use HasView;
    use HasVisibility;
    use HasWidth;

    // Note: $sortable and $searchable booleans removed in v2. Capabilities are the
    // single source of truth, read through CanBeSorted / CanBeSearchable.

    // Everything about this column across viewport widths — the breakpoint
    // visibility, the named shortcuts, and the per-width content closures the
    // render methods below read — is owned by the HasResponsive trait.

    /** @var bool Whether the column can be toggled in the UI */
    protected bool $toggleable = true;

    /** @var Closure|null Custom formatter for the cell value */

    /** @var Closure|null Custom display logic for the cell */
    protected ?Closure $displayUsing = null;

    // $default comes from Foundation\Concerns\HasDefault.
    // $tooltip comes from Foundation\Concerns\HasTooltip.

    // $copyable and copyable()/isCopyable() come from Foundation's CanBeCopyable;
    // only the confirmation message stays here, because its default names a
    // wire-table translation key.
    /** @var string|null Message shown when content is copied */
    protected ?string $copyMessage = null;

    /** @var string|null Additional HTML attributes for the cell */
    protected ?string $extraAttributes = null;

    /** @var array<string, string> Additional HTML attributes for the column header */
    protected array $extraHeaderAttributes = [];

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

    /** @var bool Whether this column is for a pivot table */
    protected bool $isPivot = false;

    // The aggregate triple ($aggregateFunction/$aggregateRelation/$aggregateColumn)
    // and the counts()/sums()/averages()/mins()/maxes() API come from HasAggregate.

    // Inline editing ($inlineEditAbility/$editableRules/$editableCallback/$fillable,
    // editable()/fillable()/authorizeInline() and their accessors) comes from CanBeEdited.

    // $filter and the filterable()/filterAs*() API come from CanBeFiltered.
    // Note: the $filterable boolean was removed in v2 — use capabilities.

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

    public function renderCell(Model $record): string
    {
        return $this->renderCellContent($this->displayUsing, $record);
    }

    /**
     * The cell, with the closure that supplies its content.
     *
     * There is one cell and one set of chrome — the link, the icon, the copy
     * button, the classes, the description — and a closure only ever replaces
     * what sits *inside* it. That is already how `displayUsing()` behaves, and
     * making the per-width closures share this method is what stopped
     * {@see renderMobileCell()} from returning bare escaped text: a column with
     * `mobileDisplayUsing()` kept its record link and its copy button on a
     * desktop and lost both on a phone, which is the width that needs them.
     *
     * @param  Closure|null  $contentUsing  fn ($state, $record, $column): mixed — null formats the state
     */
    private function renderCellContent(?Closure $contentUsing, Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        $content = $contentUsing
            ? (string) $contentUsing($state, $record, $this)
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
     * §7: the Htmlable cell skeleton.
     *
     * The text partial is rendered ONCE into a {@see Skeleton} and every row splices
     * its own values in — a string op, not a `view()->render()`. What varies per
     * record is only ever a *value*: the content, and — since the multi-slot move —
     * a per-record url, copy value, description-closure or icon-closure too. Those
     * four used to drop the column back onto the per-cell render, measured at 18–33×
     * the cost of a splice (and 3.3× on whole-table mount when every column carried
     * one), which is the entire reason this now has more than one hole in it.
     *
     * Structure, as opposed to value, is what a skeleton cannot splice: a url present
     * on one row and absent on the next are two shapes. So skeletons are cached per
     * shape rather than one per column — O(shapes) renders, and in practice one.
     *
     * @var array<string, Skeleton>
     */
    private array $cellSkeletons = [];

    private ?string $staticIconHtml = null;

    public function renderCellFast(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        // A subclass that overrides renderCell renders a different view than the text
        // skeleton, so it falls back to its own full, byte-identical render.
        if (! $this->supportsCellSkeleton()) {
            return $this->renderCell($record);
        }

        $state = $this->getState($record);
        $content = $this->displayUsing
            ? (string) ($this->displayUsing)($state, $record)
            : $this->formatValue($state, $record);

        // Resolved per record ONLY where the column's own config is per-record. A
        // plain text column pays three null checks here, not three resolutions —
        // which is what keeps the common path exactly as cheap as it was.
        $url = $this->urlCallback !== null ? $this->getUrl($record) : null;
        $description = $this->description instanceof Closure
            ? ($this->description)($record)
            : (is_string($this->description) ? $this->description : null);
        $iconHtml = $this->icon instanceof Closure
            ? $this->iconHtmlFor($record)
            : ($this->staticIconHtml ??= $this->iconHtmlFor(null));

        $shape = ($url !== null && $url !== '' ? 'u' : '')
            .($description !== null && $description !== '' ? 'd' : '')
            .($iconHtml !== '' ? 'i' : '');

        $skeleton = $this->cellSkeletons[$shape]
            ??= $this->buildCellSkeleton($url, $description, $iconHtml);

        // Each value arrives encoded exactly as the partial would have encoded it in
        // that position: content raw or escaped per ->html(), url/description/copy
        // value through e() because the partial escapes them, icon markup raw.
        //
        // Built branch by branch to match the shape above rather than as one literal:
        // a value for a slot this shape does not have is work every row pays for
        // nothing — which is how the §5 copyMessage regression happened, and
        // EnumResolver::scalar() on every cell of every non-copyable column would be
        // the same mistake again.
        $values = ['content' => $this->html ? $content : e($content)];

        if ($url !== null && $url !== '') {
            $values['url'] = e($url);
        }

        if ($this->copyable) {
            $values['copyValue'] = e((string) EnumResolver::scalar($state));
        }

        if ($description !== null && $description !== '') {
            $values['description'] = e($description);
        }

        if ($iconHtml !== '') {
            $values['icon'] = $iconHtml;
        }

        // The trim is the partial's own — a class-less, non-html cell is bare text,
        // so surrounding whitespace in the state would otherwise survive here and
        // not in renderCell().
        return trim($skeleton->fill($values));
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
     * Render the partial once for one cell *shape*, with a sentinel wherever a value
     * varies by record.
     *
     * The three arguments are the resolved values for THIS shape, and only their
     * presence is read: they decide whether the partial builds the `<a>`, the
     * description block and the icon at all. Their content arrives later, through
     * {@see Skeleton::fill()}.
     */
    private function buildCellSkeleton(?string $url, ?string $description, string $iconHtml): Skeleton
    {
        return Skeleton::compile($this->renderView('tables.columns.text', [
            'content' => Skeleton::slot('content'),
            'textClasses' => $this->getTextClasses(),
            // Built raw so no sentinel is escaped here; each value is encoded for its
            // own position when a row splices it in.
            'isHtml' => true,
            'iconHtml' => $iconHtml === '' ? '' : Skeleton::slot('icon'),
            'iconPosition' => $this->iconPosition ?? 'before',
            'url' => ($url === null || $url === '') ? null : Skeleton::slot('url'),
            'openInNewTab' => $this->openUrlInNewTab,
            'copyable' => $this->copyable,
            'copyValue' => $this->copyable ? Skeleton::slot('copyValue') : null,
            // Column-static, so it stays baked in rather than becoming a slot. Same
            // §5 guard as renderCell(): resolved only when the column is copyable.
            'copyMessage' => $this->copyable ? ($this->copyMessage ?? Trans::get('wire-table::messages.copied')) : null,
            'tooltip' => $this->tooltip,
            'description' => ($description === null || $description === '') ? null : Skeleton::slot('description'),
            'descriptionPosition' => $this->descriptionPosition,
        ]), 'content', 'icon', 'url', 'copyValue', 'description');
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
            $value = $this->applyStateFormatter(
                $attr !== null ? $record->getAttribute($attr) : null,
                $record,
            );

            return $value ?? $this->default;
        }

        // fallback: resolveValue + formatStateUsing
        $value = $this->applyStateFormatter($this->resolveValue($record), $record);

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

    /**
     * Resolve the column icon to its rendered SVG for a given record.
     *
     * The icon may be a per-record Closure ({@see HasIcon::icon()}); it is
     * resolved with the record (evaluated closures may also return an Icon enum),
     * so a closure icon can never reach renderIcon(string) raw. Passing a null
     * record resolves only a literal icon — that is the column-static case the
     * skeleton bakes in, where a closure icon is spliced per row through its slot.
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
        return $this->renderCellContent($this->mobileDisplayUsing ?? $this->displayUsing, $record);
    }

    /**
     * Render desktop-specific content.
     */
    public function renderDesktopCell(Model $record): string
    {
        return $this->renderCellContent($this->desktopDisplayUsing ?? $this->displayUsing, $record);
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

    /** Replace the rendered cell entirely; the Closure receives `$state, $record` and returns the display value (string or Htmlable). */
    public function displayUsing(Closure $callback): static
    {
        $this->displayUsing = $callback;

        return $this;
    }

    /**
     * Make the cell click-to-copy, with an optional confirmation message.
     *
     * Widens Foundation's `CanBeCopyable::copyable()` with the second argument,
     * which is why the concern's own docblock says a surface carrying a message
     * keeps its setter local: defaulting it means naming a `wire-table::`
     * translation key, and a shared Foundation trait must not know one. The
     * parameter keeps its own name — a class override is not signature-checked
     * against the trait it replaces, and renaming it would break
     * `copyable(copyable: false)`.
     */
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

    public function isHtml(): bool
    {
        return $this->html;
    }

    // size()/sm()/md()/lg()/getSize() come from Foundation\Concerns\HasSize and
    // now control the column's structural size. Text font-size moved to the
    // dedicated textSize() setter below (breaking change in v2).

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
