<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Enums\TableLayout;
use NyonCode\WireTable\Support\CardRenderer;
use NyonCode\WireTable\Support\MobileCard;
use NyonCode\WireTable\Support\MobileCardConfig;
use NyonCode\WireTable\Table;

/**
 * The table as a list of cards, below the stacking breakpoint.
 *
 * `stackedOnMobile()` does not replace the table — it puts a **second full
 * rendering of every record** in the same document and lets CSS choose. That is
 * the fact the rest of this trait follows from:
 *
 * - The two halves are swapped by literal Tailwind classes resolved in PHP
 *   ({@see getStackedTableHiddenClass()} / {@see getStackedCardsVisibleClass()}),
 *   never interpolated, so the JIT scanner sees them. With stacking off the cards
 *   are hidden outright rather than at a breakpoint.
 * - A card costs what a row costs, so it is compiled once and filled per record
 *   ({@see getMobileCardSkeleton()}, spliced by {@see CardRenderer}) rather than
 *   rendered per record.
 * - The card's own shape is derived from the visible columns, so hiding a column
 *   changes it. The compiled shapes are therefore keyed by
 *   {@see MobileCard::shapeSignature()} rather than memoised flat — one entry per
 *   shape, not one per table.
 *
 * The slot vocabulary itself — which column is the title, the metric, the
 * subtitle, the meta badges, the detail grid — belongs to {@see MobileCard} and
 * {@see MobileCardConfig}; this trait owns only the table's side of it: the
 * override hook, the per-column-set memo, and the two compiled artefacts.
 *
 * The phone's other two concerns are separate on purpose and stay that way:
 * `HasSheetOnMobile` (from core) decides whether a dropdown becomes a bottom
 * sheet, and {@see CollapsesActionsOnMobile} decides whether actions fold. Both
 * work without stacking, and stacking works without either.
 *
 * @phpstan-require-extends Table
 */
trait StacksOnMobile
{
    /** Below the stacked breakpoint the table renders as a list of cards. */
    protected bool $stackedOnMobile = false;

    /** How the records are drawn. See {@see layout()}. */
    protected TableLayout $layout = TableLayout::Table;

    /** The list's day dividers. See {@see listHeading()}. */
    protected ?Closure $listHeading = null;

    protected string $stackedBreakpoint = 'md';

    /** Explicit stacked-card slot assignment; null derives from the columns. */
    protected ?Closure $mobileCardCallback = null;

    private ?MobileCard $resolvedMobileCard = null;

    private ?string $resolvedMobileCardSignature = null;

    /**
     * The stacked card's compiled markup, one shape per slot signature.
     *
     * @var array<string, Skeleton>
     */
    protected array $mobileCardSkeletons = [];

    /**
     * Shape the stacked mobile card: which column is the title, which is the
     * supporting line, which is the figure set right, and what sits beside them
     * as status.
     *
     *   ->mobileCard(fn (MobileCardConfig $card) => $card
     *       ->title('number')->subtitle('customer')->metric('total')->meta('status'))
     *
     * Slots left unnamed are derived from the columns, so this is an override,
     * never a requirement.
     */
    public function mobileCard(Closure $callback): static
    {
        $this->mobileCardCallback = $callback;
        $this->resolvedMobileCard = null;

        return $this;
    }

    /**
     * The card resolved for a set of visible columns, memoized per column set —
     * the stacked view would otherwise resolve it once per record.
     *
     * @param  array<int, Column>  $visibleColumns
     */
    public function getMobileCard(array $visibleColumns): MobileCard
    {
        $signature = implode('|', array_map(fn (Column $c): string => $c->getName(), $visibleColumns));

        if ($this->resolvedMobileCard === null || $this->resolvedMobileCardSignature !== $signature) {
            $this->resolvedMobileCard = MobileCard::resolve($visibleColumns, $this->mobileCardCallback);
            $this->resolvedMobileCardSignature = $signature;
        }

        return $this->resolvedMobileCard;
    }

    /**
     * Enable stacked/card layout on mobile devices
     *
     * @param  bool  $stacked  Whether to use stacked layout
     * @param  string|Breakpoint  $breakpoint  Breakpoint below which to use stacked layout (sm, md, lg)
     */
    public function stackedOnMobile(bool $stacked = true, string|Breakpoint $breakpoint = Breakpoint::Md): static
    {
        $this->stackedOnMobile = $stacked;
        $this->stackedBreakpoint = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    public function isStackedOnMobile(): bool
    {
        return $this->stackedOnMobile;
    }

    /**
     * Draw the records as rows, or as a list of cards at every width.
     *
     * `->layout('list')` renders the card half **only** — the `<table>` is not
     * in the document at all, which is the difference from `stackedOnMobile()`
     * and the whole point: stacking puts two renderings of every record on the
     * page and lets CSS choose, because a table that has to survive a phone
     * needs both. A surface that is never a table needs one.
     *
     * Everything around the records is unchanged, and that is why this is a
     * layout rather than a different page: search, filters, pagination, the
     * selection that survives paging, the bulk actions over it and the exports
     * all still apply.
     */
    public function layout(TableLayout|string $layout): static
    {
        $this->layout = TableLayout::resolve($layout);

        return $this;
    }

    public function getLayout(): TableLayout
    {
        return $this->layout;
    }

    /**
     * The heading a run of cards sits under — the list's day dividers.
     *
     * A grid says *when* in a column; a list has no column, so a list that wants
     * to be read as a timeline says it with headings instead. The closure is
     * handed a record and returns the heading it belongs under, or null for a
     * record that sits under none.
     *
     *     ->listHeading(fn ($record) => match (true) {
     *         $record->created_at->isToday() => __('Today'),
     *         $record->created_at->isYesterday() => __('Yesterday'),
     *         default => __('Earlier'),
     *     })
     *
     * Consecutive records answering the same heading share one, so this assumes
     * the list is already ordered the way the headings run — which for the case
     * it exists for (newest first, by date) it is. It deliberately does **not**
     * reorder anything: {@see HasGrouping} is the feature that does, and it needs
     * a real column to order by. This one only draws.
     */
    public function listHeading(?Closure $heading): static
    {
        $this->listHeading = $heading;

        return $this;
    }

    public function getListHeading(): ?Closure
    {
        return $this->listHeading;
    }

    /**
     * The records grouped under their headings, in the order they arrived.
     *
     * Resolved here rather than in the card loop, and that is a render-cost
     * decision as much as a Rule-1 one: an `@if` inside that loop is a pair of
     * Livewire morph markers on **every card**, and the payload fuse budgets
     * those per row. Grouping in PHP moves the branch out of the loop — the
     * inner loop stays exactly the flat `@forelse` it has always been.
     *
     * @param  iterable<int, Model>  $records
     * @return list<array{heading: string|null, records: list<Model>}>
     */
    public function groupCards(iterable $records): array
    {
        $groups = [];
        $last = null;

        foreach ($records as $record) {
            $label = $this->listHeading === null ? null : $this->listHeading->__invoke($record);
            $label = is_string($label) && $label !== '' ? $label : null;

            // A new run starts on the first record and whenever the heading
            // changes; equal neighbours share the one above them.
            if ($groups === [] || $last !== $label) {
                $groups[] = ['heading' => $label, 'records' => []];
                $last = $label;
            }

            $groups[array_key_last($groups)]['records'][] = $record;
        }

        return $groups;
    }

    /** Whether a `<table>` is rendered at all. False under the list layout. */
    public function rendersTable(): bool
    {
        return $this->layout->rendersTable();
    }

    /**
     * Whether the card rendering is in the document.
     *
     * Under the list layout always; otherwise only when the table was asked to
     * stack on a phone, and then it is the second of two renderings.
     */
    public function rendersCards(): bool
    {
        return ! $this->rendersTable() || $this->stackedOnMobile;
    }

    public function getStackedBreakpoint(): string
    {
        return $this->stackedBreakpoint;
    }

    /**
     * Responsive class that hides the full table below the stacked breakpoint.
     *
     * Owns the breakpoint → Tailwind class mapping in PHP (literal class names so
     * the JIT scanner sees them); the view only consumes the result. Returns no
     * hiding class when mobile stacking is disabled.
     */
    public function getStackedTableHiddenClass(): string
    {
        // Nothing to hide under the list layout — the table is not rendered.
        if (! $this->stackedOnMobile || ! $this->rendersTable()) {
            return '';
        }

        return Breakpoint::resolve($this->stackedBreakpoint)->blockFromClass();
    }

    /**
     * Responsive class that shows the mobile cards only below the stacked
     * breakpoint. Companion to {@see getStackedTableHiddenClass()}; defaults to a
     * fully hidden cards layout when mobile stacking is disabled.
     */
    public function getStackedCardsVisibleClass(): string
    {
        // The list layout's cards are the only rendering there is, so they are
        // visible at every width rather than below a breakpoint.
        if (! $this->rendersTable()) {
            return '';
        }

        if (! $this->stackedOnMobile) {
            return 'hidden';
        }

        return Breakpoint::resolve($this->stackedBreakpoint)->hiddenAtClass();
    }

    /**
     * Companion of {@see getRowClasses()} for the mobile stacked-card view: the
     * row tint (or the default white card background) plus the card border and
     * any custom row class, so a colored row reads the same on phone and desktop.
     */
    public function getRowCardClasses(?Model $record): string
    {
        $tint = $record === null ? null : $this->getRowColor($record);
        $background = $tint !== null
            ? HasColor::getRowTintClasses($tint)
            : 'bg-white dark:bg-gray-800';

        return trim("{$background} border-b border-gray-200 dark:border-gray-700 ".((string) $this->getRowClass($record)));
    }

    /**
     * The stacked-layout card, compiled once for the table and filled per record.
     *
     * Keyed by {@see MobileCard::shapeSignature()} rather than memoised flat:
     * which slots a card has is derived from the visible columns, so hiding one
     * changes the shape and must not reuse the shape before it. The card answers
     * that question because it is the card's own, and so a slot added there
     * cannot be forgotten here.
     *
     * Everything else the shell branches on — selectability, whether there are
     * mobile actions, whether they collapse, whether the table renders row
     * partials — is a property of the table, constant for the instance, and so
     * needs no key: Livewire rebuilds the `Table` on every request.
     *
     * @see CardRenderer
     */
    public function getMobileCardSkeleton(MobileCard $card): Skeleton
    {
        return $this->mobileCardSkeletons[$card->shapeSignature()] ??= Skeleton::compile(
            view('wire-table::tables.partials.mobile-card', [
                'isSelectable' => $this->isSelectable(),
                'cardTitle' => $card->title() !== null,
                'cardMetric' => $card->metric() !== null,
                'cardSubtitle' => $card->subtitle() !== null,
                'hasMeta' => $card->meta() !== [],
                'hasDetails' => $card->details() !== [],
                'hasMobileActions' => $this->getMobileRowActionsForDisplay() !== [],
                'collapseMobileActions' => $this->shouldCollapseActionsOnMobile(),
                'detailsClass' => 'px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2'
                    .($this->isSelectable() ? ' pl-12' : ''),
                'actionsClass' => 'flex flex-wrap items-center gap-2 px-4 pb-3'
                    .($this->isSelectable() ? ' pl-12' : ''),
                'partialAnchor' => $this->usesRowPartials()
                    ? ' wire:partial="card-'.Skeleton::slot('key').'"'
                    : '',
                'cardClasses' => Skeleton::slot('cardClasses'),
                'key' => Skeleton::slot('key'),
                'keyJs' => Skeleton::slot('keyJs'),
                'title' => Skeleton::slot('title'),
                'metric' => Skeleton::slot('metric'),
                'subtitle' => Skeleton::slot('subtitle'),
                'meta' => Skeleton::slot('meta'),
                'groupActions' => Skeleton::slot('groupActions'),
                'details' => Skeleton::slot('details'),
                'actions' => Skeleton::slot('actions'),
                'subRows' => Skeleton::slot('subRows'),
            ])->render(),
            'cardClasses', 'key', 'keyJs', 'title', 'metric', 'subtitle',
            'meta', 'groupActions', 'details', 'actions', 'subRows',
        );
    }
}
