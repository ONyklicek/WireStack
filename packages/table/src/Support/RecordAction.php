<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Actions\Action;

/**
 * A row-level action binding: *how* a record is acted on (click, double-click,
 * right-click, key) mapped to *what* runs (an {@see Action}, or a reference to
 * one already declared in `->actions()`).
 *
 * This is deliberately **not** a new kind of action. It carries no execution
 * pipeline of its own — a record action resolves to an action name and runs
 * through the table's existing `executeTableAction()` / `openActionModal()`
 * endpoints. Keeping the trigger vocabulary here, in a table-owned value object,
 * is what lets the shared `wire-core` {@see Action} stay free of table-row
 * interaction concepts.
 *
 * Phase 0 is the seam only: it wraps an action (or names one) so `Table` can
 * accept, store and reject it in the right places. Triggers, `__call` delegation
 * to the wrapped action, `behaviorOnly()`, and the `Action` macros that promote
 * a fluent `Action::make()->onDoubleClick()` into a `RecordAction` arrive in a
 * later phase and layer onto this skeleton without reshaping it.
 */
final class RecordAction
{
    /** The wrapped action, or null when this binding only references one by name. */
    protected ?Action $action = null;

    /** The referenced action name, set only when constructed from a string. */
    protected ?string $reference = null;

    protected function __construct(string|Action $action)
    {
        if ($action instanceof Action) {
            $this->action = $action;
        } else {
            $this->reference = $action;
        }
    }

    /**
     * Wrap an action, or reference one already declared in `->actions()` by name.
     */
    public static function make(string|Action $action): self
    {
        return new self($action);
    }

    /**
     * The action's name — the wrapped action's own name, or the referenced name.
     */
    public function getName(): string
    {
        return $this->action?->getName() ?? (string) $this->reference;
    }

    /**
     * The wrapped action instance, or null when this is a name reference that the
     * resolver will look up against the table's registered actions.
     */
    public function getAction(): ?Action
    {
        return $this->action;
    }

    /**
     * Whether this binding only references an action by name (no wrapped instance).
     */
    public function isReference(): bool
    {
        return $this->action === null;
    }
}
