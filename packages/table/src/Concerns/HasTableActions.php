<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Actions\EmptyStateActionClickResolver;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;

/**
 * Which actions a table carries, and how the actions column presents them.
 *
 * Four surfaces declare an action here — the row's actions column, the bulk
 * bar, the toolbar's header actions and the empty state — and one place decides
 * what they look like: the column's position, alignment, label and width, the
 * `solid`/`quiet` style, and the compiled cell the row splices per record.
 *
 * The composition rule is the part worth having in one file. Row actions are
 * not just `$this->actions`: {@see composeRowActions()} merges in the record
 * actions promoted with `alsoInRowActions()`, drops a promoted button whose
 * name a configured action already uses, and applies the table's style. Every
 * surface that shows a row's actions goes through it, which is why the desktop
 * cell and the phone card can never disagree about what a row offers.
 *
 * Twenty-seven methods that ran from line 501 to line 2278 of `Table.php`,
 * interleaved with filters, pagination, styling and row partials. Grouping them
 * is the pattern `Table` already follows for its data source, grouping,
 * polling, sub-rows, gestures and record actions.
 *
 * Nothing moved but location. Every method keeps its name, signature and
 * visibility, because they are `Table`'s public API and a consumer calls them
 * on the table exactly as before. The phone's half of the same feature — the
 * collapse into a dropdown — lives in {@see CollapsesActionsOnMobile}.
 *
 * @phpstan-require-extends Table
 */
trait HasTableActions
{
    /** @var array<int, Action|ActionGroup> */
    protected array $actions = [];

    /** @var array<int, Action> */
    protected array $bulkActions = [];

    /** @var array<int, Action> */
    protected array $headerActions = [];

    /** @var array<int, Action|HeaderAction> */
    protected array $emptyStateActions = [];

    /** The row action cell's compiled markup — {@see getActionCellSkeleton()}. */
    protected ?Skeleton $actionCellSkeleton = null;

    /** Which side of the row the actions column sits on: 'start' or 'end'. */
    protected string $actionsPosition = 'end';

    /** Horizontal alignment inside the actions column: 'left', 'center' or 'right'. */
    protected string $actionsAlignment = 'right';

    protected ?string $actionsColumnLabel = null;

    protected ?string $actionsColumnWidth = null;

    /** Row-action presentation: 'solid' (default, filled buttons) or 'quiet' (neutral at rest, color on hover/focus). */
    protected string $actionsStyle = 'solid';

    /**
     * @param  array<int, Action|ActionGroup|RecordAction>  $actions  A RecordAction
     *                                                                is rejected — `Action::make()->onDoubleClick()` returns one, and it
     *                                                                belongs in `recordActions()`, not here; it is accepted in the type
     *                                                                only so the mistake is caught with a clear message rather than a
     *                                                                fatal further down.
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            // A RecordAction is a row-interaction binding, not a toolbar action.
            // `Action::make()->onDoubleClick()` returns one; catch the mistake of
            // dropping it into the actions column with a clear message.
            if ($action instanceof RecordAction) {
                throw TableConfigurationException::recordActionInRowActions();
            }
        }

        $this->actions = $actions;

        return $this;
    }

    /**
     * Check if table has any actions (including ActionGroups), counting record
     * actions promoted into the column via `alsoInRowActions()`.
     */
    public function hasActions(): bool
    {
        return ! empty($this->actions) || $this->recordActionResolver()->rowActionButtons() !== [];
    }

    /**
     * Get flat list of all actions (expanding ActionGroups)
     *
     * @return array<int, Action>
     */
    public function getAllActions(): array
    {
        $allActions = [];

        foreach ($this->actions as $action) {
            if ($action instanceof ActionGroup) {
                // A group can also hold record-less actions (the toolbar folds
                // its header actions into one); only row actions belong here.
                $allActions = array_merge($allActions, array_filter(
                    $action->getActions(),
                    fn (BaseAction|ActionGroup $inner): bool => $inner instanceof Action,
                ));
            } else {
                $allActions[] = $action;
            }
        }

        return $allActions;
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param  array<int, Action>  $bulkActions
     */
    public function bulkActions(array $bulkActions): static
    {
        $this->bulkActions = $bulkActions;

        return $this;
    }

    /**
     * @return array<int, Action>
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * @param  array<int, Action>  $headerActions
     */
    public function headerActions(array $headerActions): static
    {
        $this->headerActions = $headerActions;

        return $this;
    }

    /**
     * @return array<int, Action>
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }

    /**
     * Actions offered when the table has no records — typically "create the first one".
     *
     * The empty state is a record-less surface, so these run through the same
     * host methods as header actions (modal, form and confirmation included) and
     * only a static `->url()` resolves. They are not shown when the table is
     * empty because of a filter: there the offer is to clear the filter, not to
     * create a record that already exists behind it.
     *
     * @param  array<int, Action|HeaderAction>  $actions
     */
    public function emptyStateActions(array $actions): static
    {
        $this->emptyStateActions = $actions;

        return $this;
    }

    /**
     * @return array<int, Action|HeaderAction>
     */
    public function getEmptyStateActions(): array
    {
        return $this->emptyStateActions;
    }

    /**
     * Render the empty-state actions to HTML for the canonical empty-state partial.
     *
     * Resolved here rather than in Blade so the view only echoes strings, and so
     * both action kinds converge on the record-less host methods: a HeaderAction
     * already renders that way, a row Action is given
     * {@see EmptyStateActionClickResolver} instead of the row resolver. An action
     * the viewer may not run renders as an empty string and is dropped.
     *
     * @return array<int, string>
     */
    public function getEmptyStateActionsHtml(): array
    {
        return $this->renderEmptyStateActions($this->emptyStateActions);
    }

    /**
     * @param  array<int, Action|HeaderAction>  $actions
     * @return array<int, string>
     */
    private function renderEmptyStateActions(array $actions): array
    {
        $click = new EmptyStateActionClickResolver;

        $html = [];

        foreach ($actions as $action) {
            $rendered = $action instanceof HeaderAction
                ? $action->render()
                : $action->render(null, $click);

            if ($rendered !== '') {
                $html[] = $rendered;
            }
        }

        return $html;
    }

    /**
     * Set actions position ('start' or 'end')
     */
    public function actionsPosition(string $position): static
    {
        $this->actionsPosition = $position;

        return $this;
    }

    public function getActionsPosition(): string
    {
        return $this->actionsPosition;
    }

    /**
     * Set actions alignment ('left', 'center', 'right')
     */
    public function actionsAlignment(string|Alignment $alignment): static
    {
        $this->actionsAlignment = $alignment instanceof Alignment ? $alignment->value : $alignment;

        return $this;
    }

    public function getActionsAlignment(): string
    {
        return $this->actionsAlignment;
    }

    /**
     * Canonical literal `text-*` class for the actions column header alignment.
     */
    public function getActionsAlignmentClass(): string
    {
        return Alignment::resolve($this->actionsAlignment)->textClass();
    }

    /**
     * Canonical literal `justify-*` class for the actions row (flex main axis).
     */
    public function getActionsJustifyClass(): string
    {
        return Alignment::resolve($this->actionsAlignment)->justifyClass();
    }

    /**
     * Set the actions column label
     */
    public function actionsColumnLabel(?string $label): static
    {
        $this->actionsColumnLabel = $label;

        return $this;
    }

    public function getActionsColumnLabel(): ?string
    {
        return $this->actionsColumnLabel;
    }

    /**
     * Set the actions column width
     */
    public function actionsColumnWidth(?string $width): static
    {
        $this->actionsColumnWidth = $width;

        return $this;
    }

    public function getActionsColumnWidth(): ?string
    {
        return $this->actionsColumnWidth;
    }

    /**
     * Set the row-action presentation style.
     *
     * - 'solid' (default): filled, always-colored buttons — the current look.
     * - 'quiet': neutral text at rest, semantic color on hover/focus, so a row
     *   of actions stops competing with the data. Destructive actions stay
     *   legible (red at rest); mark one action ->solid() to keep it prominent.
     */
    public function actionsStyle(string $style): static
    {
        $this->actionsStyle = $style;

        return $this;
    }

    public function getActionsStyle(): string
    {
        return $this->actionsStyle;
    }

    /**
     * Canonical owner of row-action presentation: returns the configured actions
     * with the current style applied, so both actions-cell positions render
     * identically. Applying quiet is idempotent (the same Action instance already
     * renders for every row).
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getRowActionsForDisplay(): array
    {
        return $this->composeRowActions($this->recordActionResolver()->rowActionButtons());
    }

    /**
     * Merge the configured row actions with the record-action buttons a surface
     * asks for, dropping any whose name is already there (a record action
     * referencing an existing row action must not double it), and apply the
     * table's action style.
     *
     * @param  array<int, Action>  $recordActionButtons
     * @return array<int, Action|ActionGroup>
     */
    private function composeRowActions(array $recordActionButtons): array
    {
        $actions = array_values($this->actions);

        $seen = [];
        foreach ($actions as $action) {
            if ($action instanceof Action) {
                $seen[$action->getName()] = true;
            }
        }

        foreach ($recordActionButtons as $button) {
            if (! isset($seen[$button->getName()])) {
                $actions[] = $button;
                $seen[$button->getName()] = true;
            }
        }

        if ($this->actionsStyle === 'quiet') {
            foreach ($actions as $action) {
                if ($action instanceof Action && ! $action->isDivider()) {
                    $action->quiet();
                }
            }
        }

        return $actions;
    }

    public function getActionCellSkeleton(): Skeleton
    {
        return $this->actionCellSkeleton ??= Skeleton::compile(
            view('wire-table::tables.partials.action-cell', [
                'cellPadding' => $this->getCellPadding(),
                'borderClass' => $this->isBordered() ? 'border border-gray-200 dark:border-gray-700' : '',
                'justifyClass' => $this->getActionsJustifyClass(),
                'actions' => Skeleton::slot('actions'),
            ])->render(),
            'actions',
        );
    }
}
