<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Concerns\HasRecordTriggers;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

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
 * Trigger vocabulary (`onClick`/`onDoubleClick`/`onContextMenu`/`onKey`/`on`)
 * and the behaviour-only vs. also-in-toolbar choice come from
 * {@see HasRecordTriggers}. Any other fluent call — `->label()`, `->icon()`,
 * `->action()`, `->visible()`, `->authorize()`, … — is forwarded to the wrapped
 * action by `__call`, so a record action chains with the full action API while
 * the wrapper keeps ownership of the row-interaction concepts.
 *
 * The same reach the other way — a fluent `Action::make()->onDoubleClick()` — is
 * provided by macros registered on `Action` in `WireTableServiceProvider`, which
 * promote the action into a `RecordAction`. No trigger state ever lives on the
 * shared `Action`; the macro only constructs this wrapper.
 */
final class RecordAction
{
    use HasRecordTriggers;

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
     * Forward any other fluent call to the wrapped action. Setters on the action
     * return the action itself; we translate that back into `$this` so the chain
     * stays on the wrapper. Getters pass their value straight through.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($this->action === null) {
            throw TableConfigurationException::cannotConfigureReferencedRecordAction($method, $this->getName());
        }

        $result = $this->action->{$method}(...$arguments);

        return $result === $this->action ? $this : $result;
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
