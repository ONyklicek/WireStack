<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Support\RecordTrigger;
use NyonCode\WireTable\Support\ResolvedRecordAction;
use NyonCode\WireTable\Table;

/**
 * Turns a table's record-action bindings into the shapes the rest of the system
 * consumes — a pointer map for the JS controller, the context-menu contribution,
 * and the toolbar-button contribution — without owning any execution.
 *
 * A record action is not a new kind of action: it resolves to an action *name*
 * and runs through the table's existing `executeTableAction()` /
 * `openActionModal()` endpoints. This resolver only decides *which* name a given
 * interaction maps to, applying the selection-aware default (a binding with no
 * explicit trigger fires on double-click when the table is selectable — so a
 * single click is left to row selection — and on single click otherwise).
 *
 * Name references resolve against the table's registered row actions
 * ({@see Table::findRegisteredAction()}), so a record action can reuse an action
 * already declared in `->actions()` without redefining it.
 */
final class RecordActionResolver
{
    /** @var array<int, ResolvedRecordAction>|null Memoized per instance. */
    private ?array $resolved = null;

    public function __construct(private readonly Table $table) {}

    /**
     * @return array<int, ResolvedRecordAction>
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $default = $this->table->isSelectable()
            ? RecordTrigger::DOUBLE_CLICK
            : RecordTrigger::CLICK;

        $out = [];

        foreach ($this->table->getRecordActions() as $entry) {
            $out[] = $this->normalize($entry, $default);
        }

        return $this->resolved = $out;
    }

    /**
     * Pointer triggers (click, double-click, custom gestures) mapped to the
     * action name for the JS controller. Context-menu and key triggers are
     * excluded — they route through the row context menu and the keyboard layer
     * respectively. When two bindings claim the same trigger, the later wins.
     *
     * @return array<string, string>
     */
    public function pointerMap(): array
    {
        $map = [];

        foreach ($this->resolve() as $resolved) {
            foreach ($resolved->triggerTypes as $type) {
                if ($type === RecordTrigger::CONTEXT_MENU || $type === RecordTrigger::KEY) {
                    continue;
                }

                $map[$type] = $resolved->name;
            }
        }

        return $map;
    }

    /**
     * Actions bound to the right-click trigger, resolved to instances so they can
     * merge into the row context menu.
     *
     * @return array<int, Action>
     */
    public function contextMenuActions(): array
    {
        return $this->instancesFor(fn (ResolvedRecordAction $r): bool => $r->hasTrigger(RecordTrigger::CONTEXT_MENU));
    }

    /**
     * Actions that also render as a button in the actions column
     * (`alsoInRowActions()`), resolved to instances.
     *
     * @return array<int, Action>
     */
    public function rowActionButtons(): array
    {
        return $this->instancesFor(fn (ResolvedRecordAction $r): bool => $r->rendersInRowActions);
    }

    /**
     * The action Enter fires on the active row: the double-click binding when
     * present (the recommended "open" gesture), else the single-click one.
     */
    public function primaryActionName(): ?string
    {
        $map = $this->pointerMap();

        return $map[RecordTrigger::DOUBLE_CLICK] ?? $map[RecordTrigger::CLICK] ?? null;
    }

    /**
     * The action Shift+Enter fires: the other pointer binding when both a click
     * and a double-click are bound, else none.
     */
    public function secondaryActionName(): ?string
    {
        $map = $this->pointerMap();
        $primary = $this->primaryActionName();

        foreach ([RecordTrigger::CLICK, RecordTrigger::DOUBLE_CLICK] as $type) {
            if (isset($map[$type]) && $map[$type] !== $primary) {
                return $map[$type];
            }
        }

        return null;
    }

    /**
     * Keyboard shortcut → action-name map, taken from each record action's
     * canonical `keyboardShortcut()` (so `onKey('Delete')` and a referenced
     * action's own shortcut both flow through here). Enter/Space are reserved for
     * navigation and never enter the map.
     *
     * @return array<string, string>
     */
    public function shortcuts(): array
    {
        $reserved = ['enter', 'return', 'space', ''];
        $out = [];

        foreach ($this->resolve() as $resolved) {
            $action = $resolved->action ?? $this->table->findRegisteredAction($resolved->name);
            $shortcut = $action?->getKeyboardShortcut();

            // The wrapped/registered action's stamped shortcut, plus any keys the
            // binding declared via onKey() — the latter is the only source for a
            // name reference whose registered action carries no shortcut of its own.
            $keys = $resolved->keyShortcuts;
            if ($shortcut !== null) {
                array_unshift($keys, $shortcut);
            }

            foreach ($keys as $key) {
                if (! in_array(strtolower($key), $reserved, true)) {
                    $out[$key] = $resolved->name;
                }
            }
        }

        return $out;
    }

    /**
     * @param  callable(ResolvedRecordAction): bool  $filter
     * @return array<int, Action>
     */
    private function instancesFor(callable $filter): array
    {
        $out = [];

        foreach ($this->resolve() as $resolved) {
            if (! $filter($resolved)) {
                continue;
            }

            $action = $resolved->action ?? $this->table->findRegisteredAction($resolved->name);

            if ($action !== null) {
                $out[] = $action;
            }
        }

        return $out;
    }

    private function normalize(string|Action|RecordAction $entry, string $default): ResolvedRecordAction
    {
        if ($entry instanceof RecordAction) {
            $types = [];
            $keyShortcuts = [];

            foreach ($entry->getTriggers() as $trigger) {
                $types[] = $trigger->type;

                if ($trigger->type === RecordTrigger::KEY && $trigger->key !== null) {
                    $keyShortcuts[] = $trigger->key;
                }
            }

            return new ResolvedRecordAction(
                $entry->getName(),
                $types === [] ? [$default] : $types,
                $entry->getAction(),
                $entry->rendersInRowActions(),
                $keyShortcuts,
            );
        }

        if ($entry instanceof Action) {
            return new ResolvedRecordAction($entry->getName(), [$default], $entry, false);
        }

        return new ResolvedRecordAction($entry, [$default], null, false);
    }
}
