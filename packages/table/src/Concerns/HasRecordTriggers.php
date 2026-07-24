<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Support\RecordTrigger;

/**
 * The fluent trigger vocabulary of a {@see RecordAction}.
 *
 * Mirrors the shape of `wire-core`'s `HasKeyboardShortcut`: a thin capability
 * mixin of configuration setters, no execution. It lives in `wire-table` on the
 * record-action binding so the shared `Action` class stays free of table-row
 * interaction concepts.
 *
 * `onKey()` is sugar, not a second keyboard system: when the binding wraps an
 * action it stamps the canonical `keyboardShortcut()` on it; the key is also
 * kept on the trigger so the resolver (F2) can apply it to an action referenced
 * only by name.
 *
 * A record action is behaviour-only by default — it is registered through
 * `recordActions()`, not `actions()`, so it renders no toolbar button unless
 * {@see alsoInRowActions()} opts in.
 */
trait HasRecordTriggers
{
    /** @var array<int, RecordTrigger> */
    protected array $triggers = [];

    /** The wrapped action the host binding exposes (null for a name reference). */
    abstract public function getAction(): ?Action;

    /** Whether this binding also renders as a button in the actions column. */
    protected bool $renderInRowActions = false;

    public function onClick(): static
    {
        return $this->addTrigger(new RecordTrigger(RecordTrigger::CLICK));
    }

    public function onDoubleClick(): static
    {
        return $this->addTrigger(new RecordTrigger(RecordTrigger::DOUBLE_CLICK));
    }

    public function onContextMenu(): static
    {
        return $this->addTrigger(new RecordTrigger(RecordTrigger::CONTEXT_MENU));
    }

    /**
     * Bind an arbitrary (future/custom) gesture by name — the extensibility seam
     * for triple-click, long-press, swipe, etc. without changing the API.
     */
    public function on(string $type): static
    {
        return $this->addTrigger(new RecordTrigger($type));
    }

    /**
     * Fire on a key. Sugar over the canonical `keyboardShortcut()` — it stamps
     * the wrapped action's shortcut (Mac detection, label all come for free) and
     * keeps the key on the trigger for name-referenced bindings.
     */
    public function onKey(string $key): static
    {
        $this->getAction()?->keyboardShortcut($key);

        return $this->addTrigger(new RecordTrigger(RecordTrigger::KEY, $key));
    }

    /**
     * @return array<int, RecordTrigger>
     */
    public function getTriggers(): array
    {
        return $this->triggers;
    }

    public function hasTrigger(string $type): bool
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Also render this action as a button in the actions column (shared between
     * the toolbar and the row gesture).
     */
    public function alsoInRowActions(): static
    {
        $this->renderInRowActions = true;

        return $this;
    }

    /**
     * Never render a button — the action exists only as row behaviour. The
     * default; call it to state the intent explicitly.
     */
    public function behaviorOnly(): static
    {
        $this->renderInRowActions = false;

        return $this;
    }

    public function rendersInRowActions(): bool
    {
        return $this->renderInRowActions;
    }

    public function isBehaviorOnly(): bool
    {
        return ! $this->renderInRowActions;
    }

    protected function addTrigger(RecordTrigger $trigger): static
    {
        $this->triggers[] = $trigger;

        return $this;
    }
}
