<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireTable\Table;

/**
 * What a phone does to the table's actions.
 *
 * A narrow viewport cannot hold a row of labelled buttons next to the data, so
 * three surfaces fold: the stacked card's row actions, the toolbar's header
 * actions, and a sub-row's actions. All three fold the same way — into the one
 * {@see ActionGroup} dropdown {@see buildMobileActionGroup()} builds, inheriting
 * the table's bottom-sheet settings and breakpoint — and all three decide
 * whether to fold by counting the actions that would actually render:
 * {@see flattenMobileRowActions()} expands nested groups and drops dividers,
 * {@see executableHeaderActions()} drops what the viewer may not run. Counting
 * the declaration instead would collapse a toolbar down to a single button.
 *
 * Two rules here exist only because both halves sit in the document at every
 * width — CSS decides which is shown, not PHP:
 *
 * 1. The collapsed copy of an action drops its `keyboardShortcut()`. A rendered
 *    menu row binds the shortcut as a *window* listener, so two copies would
 *    answer one keypress twice. The table's own declaration is left untouched;
 *    the copy is a clone.
 * 2. {@see getInlineHeaderActionsClass()} and
 *    {@see getMobileHeaderActionsVisibleClass()} are literal Tailwind classes
 *    from {@see Breakpoint}, never interpolated, so the JIT compiler sees them.
 *
 * The sub-row group is the one surface that folds unconditionally, ignoring
 * {@see collapseActionsOnMobile()}: a child line is narrower than the card that
 * holds it, and there is no width at which two labelled buttons fit.
 *
 * Twenty methods that ran from line 1067 to line 1765 of `Table.php`. Nothing
 * moved but location — every method keeps its name, signature and visibility,
 * because they are `Table`'s public API. The desktop half of the same feature
 * lives in {@see HasTableActions}.
 *
 * @phpstan-require-extends Table
 */
trait CollapsesActionsOnMobile
{
    /** Collapse row actions into a single dropdown group in the mobile stacked-card view. */
    protected bool $collapseActionsOnMobile = false;

    /** Minimum number of row actions before the mobile card collapses them into a dropdown. */
    protected int $collapseActionsOnMobileThreshold = 3;

    /** Collapse the toolbar's header actions into a single dropdown group on a phone. */
    protected bool $collapseHeaderActionsOnMobile = false;

    /** Minimum number of header actions before the toolbar collapses them into a dropdown. */
    protected int $collapseHeaderActionsOnMobileThreshold = 2;

    /** Behaviour-only record actions also render as buttons in the mobile stacked cards. */
    protected bool $recordActionButtonsOnMobile = true;

    /**
     * The same list for the mobile stacked-card view, plus — unless
     * {@see recordActionButtonsOnMobile(false)} says otherwise — a button for
     * every behaviour-only record action.
     *
     * This is what lets one table be an application on a desktop and an ordinary
     * list on a phone: the row keeps its double-click, right-click and keys
     * there, and the card offers the very same actions as buttons here. Nothing
     * is declared twice — it is one action, reached two ways.
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getMobileRowActionsForDisplay(): array
    {
        $resolver = $this->recordActionResolver();

        return $this->composeRowActions(array_merge(
            $resolver->rowActionButtons(),
            $this->recordActionButtonsOnMobile ? $resolver->mobileFallbackButtons() : [],
        ));
    }

    /**
     * Whether the mobile card has any action to show — the row actions, plus the
     * record-action fallback. Its desktop counterpart is {@see hasActions()},
     * which governs the actions *column* and knows nothing of the fallback: a
     * table whose only actions are row gestures still has no column.
     */
    public function hasMobileActions(): bool
    {
        return $this->getMobileRowActionsForDisplay() !== [];
    }

    /**
     * Turn the mobile fallback off: behaviour-only record actions then stay
     * behaviour-only everywhere, and a phone reaches them only through whatever
     * else the table offers (`alsoInRowActions()`, a `recordUrl()`, …).
     */
    public function recordActionButtonsOnMobile(bool $enabled = true): static
    {
        $this->recordActionButtonsOnMobile = $enabled;

        return $this;
    }

    public function showsRecordActionButtonsOnMobile(): bool
    {
        return $this->recordActionButtonsOnMobile;
    }

    /**
     * The same actions for the stacked-card layout's empty state.
     *
     * Both layouts sit in the document at every width — CSS decides which is
     * shown — so the card copy drops the action's `keyboardShortcut()`: a
     * rendered button binds it as a *window* listener, and two of them would
     * answer one keypress twice. Same reason the mobile row actions clone.
     *
     * @return array<int, string>
     */
    public function getMobileEmptyStateActionsHtml(): array
    {
        return $this->renderEmptyStateActions(array_map(
            fn (Action|HeaderAction $action): Action|HeaderAction => (clone $action)->withoutKeyboardShortcut(),
            $this->emptyStateActions,
        ));
    }

    /**
     * Collapse the row actions into one dropdown group in the mobile stacked-card
     * view, so a card header shows a single "⋮" trigger instead of several inline
     * buttons. No effect on the desktop table, and only meaningful together with
     * {@see stackedOnMobile()}.
     *
     * The collapse only kicks in once a row has at least `$threshold` actions
     * (default 3); with fewer actions the card keeps them inline. Pass a lower
     * threshold to collapse sooner, or 1 to always collapse.
     */
    public function collapseActionsOnMobile(bool $collapse = true, int $threshold = 3): static
    {
        $this->collapseActionsOnMobile = $collapse;
        $this->collapseActionsOnMobileThreshold = max(1, $threshold);

        return $this;
    }

    public function getCollapseActionsOnMobileThreshold(): int
    {
        return $this->collapseActionsOnMobileThreshold;
    }

    /**
     * Whether the mobile card should collapse its row actions: the feature is
     * enabled and the row carries at least the configured threshold of actions.
     * The count flattens nested groups and ignores dividers, matching what the
     * dropdown would actually contain.
     */
    public function shouldCollapseActionsOnMobile(): bool
    {
        return $this->collapseActionsOnMobile
            && count($this->flattenMobileRowActions()) >= $this->collapseActionsOnMobileThreshold;
    }

    /**
     * Flatten the configured row actions into a single list, expanding nested
     * {@see ActionGroup}s and dropping dividers. Shared by the collapse threshold
     * check and {@see getMobileActionGroup()} so both count the same actions.
     *
     * @return array<int, Action>
     */
    protected function flattenMobileRowActions(): array
    {
        $flat = [];

        foreach ($this->getMobileRowActionsForDisplay() as $action) {
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $inner) {
                    // Dividers are chrome, and a group's record-less members
                    // belong to another surface than a row's actions.
                    if (! $inner instanceof Action || $inner->isDivider()) {
                        continue;
                    }

                    $flat[] = $inner;
                }

                continue;
            }

            if ($action->isDivider()) {
                continue;
            }

            $flat[] = $action;
        }

        return $flat;
    }

    /**
     * Canonical builder for the mobile card's collapsed action dropdown: wraps the
     * row actions in a single {@see ActionGroup}, flattening any existing groups so
     * everything lands under one trigger. The group inherits the table's mobile
     * bottom-sheet settings and collapses to a lone inline button when only one
     * action is visible (handled by ActionGroup itself).
     */
    public function getMobileActionGroup(): ActionGroup
    {
        return $this->buildMobileActionGroup($this->flattenMobileRowActions());
    }

    /**
     * The same collapsed dropdown for a sub-row's actions.
     *
     * Child actions collapse on a phone unconditionally, unlike row actions
     * (which honour {@see collapseActionsOnMobile()}): a child line is narrower
     * than the card that holds it, and two labelled buttons there crush the
     * product name to an ellipsis. There is no width at which they fit.
     */
    public function getMobileSubRowActionGroup(): ActionGroup
    {
        $flat = [];

        foreach ($this->getSubRowActions() as $action) {
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $inner) {
                    if ($inner instanceof Action && $inner->isDivider()) {
                        continue;
                    }

                    $flat[] = $inner;
                }

                continue;
            }

            if ($action instanceof Action && $action->isDivider()) {
                continue;
            }

            $flat[] = $action;
        }

        return $this->buildMobileActionGroup($flat);
    }

    /**
     * @param  array<int, BaseAction|ActionGroup>  $actions
     */
    private function buildMobileActionGroup(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->sheetOnMobile($this->usesSheetOnMobile())
            ->mobileBreakpoint($this->getMobileBreakpoint());
    }

    /**
     * Collapse the toolbar's header actions into one dropdown group on a phone,
     * so a narrow toolbar shows a single "⋮" trigger instead of several labelled
     * buttons competing with the search field, the filters and the view menu.
     *
     * Unlike {@see collapseActionsOnMobile()} this needs no `stackedOnMobile()`:
     * the toolbar is the same toolbar at every width, so the collapse is purely a
     * width switch. **Desktop is untouched** — from the mobile breakpoint up the
     * inline buttons render exactly as before; the breakpoint is the table's
     * {@see mobileBreakpoint()} (`sm` by default, i.e. below 640px).
     *
     * The collapse only kicks in once the toolbar carries at least `$threshold`
     * executable header actions (default 2 — one button alone is not a crowd, and
     * the toolbar folds sooner than a card's row actions because it also holds the
     * search field and the view menu). The threshold is clamped to at least 1.
     */
    public function collapseHeaderActionsOnMobile(bool $collapse = true, int $threshold = 2): static
    {
        $this->collapseHeaderActionsOnMobile = $collapse;
        $this->collapseHeaderActionsOnMobileThreshold = max(1, $threshold);

        return $this;
    }

    public function getCollapseHeaderActionsOnMobileThreshold(): int
    {
        return $this->collapseHeaderActionsOnMobileThreshold;
    }

    /**
     * Whether the toolbar should collapse its header actions on a phone: the
     * feature is enabled and at least the configured threshold of header actions
     * would actually render. The count only includes actions the viewer may run,
     * because those are the ones that reach the toolbar at all — a table whose
     * per-viewer guards leave one action keeps that action as a plain button.
     */
    public function shouldCollapseHeaderActionsOnMobile(): bool
    {
        return $this->collapseHeaderActionsOnMobile
            && count($this->executableHeaderActions()) >= $this->collapseHeaderActionsOnMobileThreshold;
    }

    /**
     * The header actions that reach the toolbar at all: the ones the viewer may
     * run. Shared by the collapse threshold and {@see getMobileHeaderActionGroup()}
     * so the count matches what the dropdown would really contain — the inline
     * buttons drop a guarded action the same way.
     *
     * @return array<int, BaseAction>
     */
    protected function executableHeaderActions(): array
    {
        return array_values(array_filter(
            $this->headerActions,
            fn (BaseAction $action): bool => $action->canExecute(),
        ));
    }

    /**
     * Canonical builder for the toolbar's collapsed header-action dropdown: the
     * same {@see ActionGroup} the row actions collapse into, so a phone gets one
     * dropdown vocabulary rather than two.
     *
     * Both halves sit in the document at every width — CSS decides which is shown
     * — so the collapsed copy drops each action's `keyboardShortcut()`: a rendered
     * menu row binds it as a *window* listener, and two of them would answer one
     * keypress twice. Same reason the mobile row actions and the mobile empty
     * state clone.
     */
    public function getMobileHeaderActionGroup(): ActionGroup
    {
        return $this->buildMobileActionGroup(array_map(
            fn (BaseAction $action): BaseAction => (clone $action)->withoutKeyboardShortcut(),
            $this->executableHeaderActions(),
        ));
    }

    /**
     * Responsive class for the toolbar's inline header actions: hidden below the
     * mobile breakpoint (the dropdown stands in for them), a plain flex row from
     * it up. Empty while the collapse is off, so the buttons render unwrapped.
     */
    public function getInlineHeaderActionsClass(): string
    {
        if (! $this->shouldCollapseHeaderActionsOnMobile()) {
            return '';
        }

        return Breakpoint::resolve($this->getMobileBreakpoint())->flexFromClass();
    }

    /**
     * Companion to {@see getInlineHeaderActionsClass()}: shows the collapsed
     * dropdown only below the mobile breakpoint.
     */
    public function getMobileHeaderActionsVisibleClass(): string
    {
        return Breakpoint::resolve($this->getMobileBreakpoint())->hiddenAtClass();
    }
}
