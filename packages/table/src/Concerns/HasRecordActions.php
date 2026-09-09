<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Actions\RecordActionResolver;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;

/**
 * Whole-row interaction: what a double-click, a right-click or a keyboard
 * shortcut does to the record under the cursor.
 *
 * Fifteen methods that were spread from line 1967 to line 2547 of `Table.php`,
 * interleaved with mobile chrome and column configuration. They are one
 * feature — the one `docs/table/record-actions.md` describes and
 * `verify-record-actions` drives — and grouping them is the pattern `Table`
 * already follows for its data source, grouping, polling, sub-rows and
 * gestures.
 *
 * Nothing moved but location. Every method keeps its name, signature and
 * visibility, because they are `Table`'s public API and a consumer calls them
 * on the table exactly as before.
 *
 * @phpstan-require-extends Table
 */
trait HasRecordActions
{
    /** @var array<int, string|Action|RecordAction> Row-level record-action bindings (click/dblclick/etc.). */
    protected array $recordActions = [];

    /** Opt-in hover color for rows carrying a record action; null keeps the neutral default. */
    protected ?string $recordActionHover = null;

    /** Memoized resolver over the record-action bindings; cleared when they (or selection) change. */
    private ?RecordActionResolver $recordActionResolver = null;

    /**
     * Whether the table carries a whole-row pointer record action (click or
     * double-click) — the rows are clickable and should read as such.
     */
    public function hasRecordActionPointer(): bool
    {
        return $this->getRecordActionBindings() !== [];
    }

    /**
     * Whether the delegated `wireRecordActions` controller mounts on the
     * `<tbody>`: for pointer bindings, a context menu, and every grid —
     * including a selectable table with no record action, whose keyboard
     * selection (Space, Shift+arrow, mod+A) and active-row marker live in the
     * same controller.
     *
     * The mouse gestures are listed in their own right, not folded into the
     * grid: a table may keep the sweep or the Shift-ranges with the keyboard
     * layer switched off, and both of them live in this controller too.
     */
    public function mountsRecordActionController(): bool
    {
        return $this->hasRecordActionPointer()
            || $this->hasRowContextMenu()
            || $this->usesGridSemantics()
            || $this->usesDragSelect()
            || $this->usesRangeSelection();
    }

    /**
     * The client config the keyboard layer of `wireRecordActions` consumes:
     * the Enter/Shift+Enter targets, the shortcut map, whether Space toggles
     * selection and whether Shift+arrows extend it. The active-row marker is
     * shared with the pointer layer and lives in {@see getActiveRowConfig()};
     * the mouse gestures in {@see getGestureConfig()}.
     *
     * @return array<string, mixed>
     */
    public function getRecordActionKeyboardConfig(): array
    {
        $resolver = $this->recordActionResolver();

        return [
            'primary' => $resolver->primaryActionName(),
            'secondary' => $resolver->secondaryActionName(),
            'shortcuts' => $resolver->shortcuts(),
            'selectable' => $this->isSelectable(),
            'ranges' => $this->usesRangeSelection(),
        ];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getRowContextMenuActions(): array
    {
        return $this->rowContextMenuActions;
    }

    /**
     * The full context-menu action list: the dedicated `rowContextMenu()` actions
     * plus any record action bound with `onContextMenu()`. This is the single
     * owner of "what the right-click menu shows" — the record-action layer feeds
     * the existing menu rather than standing up a second one.
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getContextMenuActions(): array
    {
        return array_merge(
            array_values($this->rowContextMenuActions),
            $this->recordActionResolver()->contextMenuActions(),
        );
    }

    /**
     * Bind an action to a whole-row interaction — a click, double-click,
     * right-click or key over the empty part of the row runs it, desktop-app
     * style. Separate from `->actions()` (toolbar buttons), `->bulkActions()`
     * and `->headerActions()`.
     *
     * Accepts an {@see Action} (or a {@see RecordAction} with an explicit
     * trigger), or the *name* of an action already declared in `->actions()` to
     * reference it without redefining. Each call appends; call it more than once,
     * or pass a list to {@see recordActions()}.
     */
    public function recordAction(string|Action|RecordAction $action): static
    {
        $this->recordActions[] = $action;
        $this->recordActionResolver = null;

        return $this;
    }

    /**
     * Replace the record-action bindings with the given list.
     *
     * @param  array<int, string|Action|RecordAction>  $actions
     */
    public function recordActions(array $actions): static
    {
        $this->recordActions = array_values($actions);
        $this->recordActionResolver = null;

        return $this;
    }

    /**
     * @return array<int, string|Action|RecordAction>
     */
    public function getRecordActions(): array
    {
        return $this->recordActions;
    }

    public function hasRecordActions(): bool
    {
        return $this->recordActions !== [];
    }

    /**
     * The memoized resolver over the record-action bindings. Cleared by the
     * record-action and selection setters, since the default trigger is
     * selection-aware.
     */
    protected function recordActionResolver(): RecordActionResolver
    {
        return $this->recordActionResolver ??= new RecordActionResolver($this);
    }

    /**
     * Pointer-trigger → action-name map for the JS controller / Blade x-data
     * (click, double-click and custom gestures; not context-menu or key).
     *
     * @return array<string, string>
     */
    public function getRecordActionBindings(): array
    {
        return $this->recordActionResolver()->pointerMap();
    }

    /**
     * Find a registered row action by name (flattening action groups). The
     * canonical name lookup a record-action reference resolves against — a
     * `recordAction('edit')` reuses the very `Action` declared in `->actions()`.
     */
    public function findRegisteredAction(string $name): ?Action
    {
        foreach ($this->getAllActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * The wrapped action instances a record action carries in its own right
     * (not name references) — the fallback pool the execution endpoints search so
     * a behaviour-only record action with its own callback still runs.
     *
     * @return array<int, Action>
     */
    public function getRecordActionInstances(): array
    {
        $out = [];

        foreach ($this->recordActions as $entry) {
            if ($entry instanceof RecordAction && $entry->getAction() !== null) {
                $out[] = $entry->getAction();
            } elseif ($entry instanceof Action) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Tint a record-action row on hover with a semantic role or hue instead of
     * the neutral default (e.g. `->recordActionHover('primary')`). Null keeps the
     * existing neutral hover, so enabling record actions never silently restyles
     * an existing table.
     */
    public function recordActionHover(?string $color): static
    {
        $this->recordActionHover = $color === '' ? null : $color;

        return $this;
    }

    public function getRecordActionHover(): ?string
    {
        return $this->recordActionHover;
    }
}
