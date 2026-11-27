<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Validation;

use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use ReflectionMethod;
use Throwable;

/**
 * Finds the actions a component exposes and builds the forms their modals open.
 *
 * Action forms are where most generated code ends up and where none of it was
 * ever looked at: a table's row action, a page's header action and a group's
 * items each carry a `->form([...])` that is only built when someone clicks.
 * This builds them the way the modal would — the same `getFormInstance()` and,
 * for a wizard, `getStepFormInstance()` per step — without a click and without a
 * Livewire request.
 *
 * Owns no vocabulary of its own: the fields it returns are checked by
 * {@see ComponentValidator} with the same icon and color rules as everything
 * else.
 */
class ActionInspector
{
    /**
     * Where the built forms bind. Only the shape matters here — nothing renders
     * or submits — so one neutral path stands in for every frame depth.
     */
    private const STATE_PATH = 'data';

    /**
     * Whether a component hosts actions of its own, outside any table: a page's
     * header actions, or `WithActions::actions()`.
     */
    public function hostsActions(string $componentClass): bool
    {
        return method_exists($componentClass, 'mountAction')
            && (method_exists($componentClass, 'getHeaderActions') || method_exists($componentClass, 'actions'));
    }

    /**
     * The component's own actions, by where they are declared.
     *
     * @return array{0: array<string, array<int, object>>, 1: array<int, Diagnostic>}
     */
    public function hostActions(object $component): array
    {
        $groups = [];
        $diagnostics = [];

        $sources = [
            // Public on a panel page; rendered in the page header.
            'pageHeaderActions' => 'getHeaderActions',
            // Protected on WithActions; a board's card buttons, a panel's own.
            'pageActions' => 'actions',
        ];

        foreach ($sources as $group => $method) {
            if (! method_exists($component, $method)) {
                continue;
            }

            try {
                $actions = $this->invoke($component, $method);
            } catch (Throwable $e) {
                $diagnostics[] = Diagnostic::error('build-failed', $method.'()', $e->getMessage());

                continue;
            }

            if (is_array($actions) && $actions !== []) {
                $groups[$group] = array_values(array_filter($actions, 'is_object'));
            }
        }

        return [$groups, $diagnostics];
    }

    /**
     * Actions with every group opened up, the group itself kept so its own icon
     * and color are still checked.
     *
     * @param  array<int, object>  $actions
     * @return array<int, object>
     */
    public function expand(array $actions): array
    {
        $flat = [];

        foreach ($actions as $action) {
            $flat[] = $action;

            if ($action instanceof ActionGroup) {
                $flat = array_merge($flat, $this->expand(array_values(array_filter($action->getActions(), 'is_object'))));
            }
        }

        return $flat;
    }

    /**
     * The forms an action's modal would show — one, or one per wizard step.
     *
     * Throws whatever the form's own construction throws; the caller reports it
     * against the action rather than failing the whole run.
     *
     * @return array<int, object>
     */
    public function forms(object $action, mixed $context): array
    {
        if (! $action instanceof BaseAction || ! $action->hasFormInstance()) {
            return [];
        }

        $steps = $action->getStepCount();

        if ($steps > 0) {
            $forms = [];

            for ($step = 0; $step < $steps; $step++) {
                $forms[] = $action->getStepFormInstance(null, $context, $step, self::STATE_PATH);
            }

            return array_values(array_filter($forms, 'is_object'));
        }

        $form = $action->getFormInstance(null, $context, self::STATE_PATH);

        return $form !== null ? [$form] : [];
    }

    /**
     * Whatever record the component is showing, for actions whose form depends
     * on it (`form(fn ($record) => …)`). The same two accessors a page answers
     * the palette with: a table host's `getRecord()`, a resource page's
     * `resolveRecord()`.
     */
    public function contextRecord(object $component): mixed
    {
        foreach (['getRecord', 'resolveRecord'] as $accessor) {
            if (! method_exists($component, $accessor)) {
                continue;
            }

            try {
                $record = $this->invoke($component, $accessor);
            } catch (Throwable) {
                continue;
            }

            if (is_object($record) && method_exists($record, 'unwrap')) {
                $record = $record->unwrap() ?? $record;
            }

            if ($record !== null) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Every action name the component and its builders expose, for suggesting
     * the one meant when a requested name matches none.
     *
     * @param  array<int, object>  $actions
     * @return array<int, string>
     */
    public function names(array $actions): array
    {
        $names = [];

        foreach ($actions as $action) {
            if (method_exists($action, 'getName') && is_string($name = $action->getName()) && $name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Call a method whatever its visibility: `actions()` and `resolveRecord()`
     * are protected, and reading internals is this package's job.
     */
    private function invoke(object $component, string $method): mixed
    {
        return (new ReflectionMethod($component, $method))->invoke($component);
    }
}
