<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Actions\Action;

/**
 * Holds a list of interactive {@see Action} buttons on a read-only display
 * component — infolist entries and schema section header actions.
 *
 * This is the canonical owner of the "a display component carries actions"
 * concept. It satisfies the HasFieldActions contract so infolist/section
 * actions share one resolution vocabulary with form-field affix actions: the
 * host re-resolves the component and calls getFieldAction() to locate the
 * triggered action by name.
 */
trait HasActions
{
    /** @var array<int, Action> */
    protected array $actions = [];

    /**
     * Replace the component's action list.
     *
     * @param  array<int, Action>  $actions
     */
    public function actions(array $actions): static
    {
        $this->actions = array_values($actions);

        return $this;
    }

    /**
     * Append a single action to the list.
     */
    public function action(Action $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    /**
     * The visible actions, in declaration order.
     *
     * @return array<int, Action>
     */
    public function getActions(): array
    {
        return array_values(array_filter(
            $this->actions,
            fn (Action $action): bool => ! $action->isHidden(),
        ));
    }

    /**
     * Whether any visible action renders.
     */
    public function hasActions(): bool
    {
        return $this->getActions() !== [];
    }

    /**
     * Resolve an action by name (used by the host's action dispatch). Searches
     * the full list — a hidden action is not clickable, so no visibility filter
     * is needed here.
     */
    public function getFieldAction(string $name): ?Action
    {
        foreach ($this->actions as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }
}
