<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\ClassifiesComponentActions;
use NyonCode\WireCore\Foundation\Contracts\RunsComponentActions;

/**
 * The Actions module's answer to a surface that carries actions but may not
 * import this module.
 *
 * A widget's header button is the case it was written for: `Widgets` and
 * `Actions` are sibling L2 modules, so the widget host resolves
 * {@see RunsComponentActions} from the container, hands over the action it found
 * by name, and never learns what an `Action` is. Everything module-specific —
 * that an action has a callback, and how that callback is called — is on this
 * side of the contract.
 *
 * ## Why an action with no callback is silence rather than an error
 *
 * Because it is a legitimate declaration. `Action::make('docs')->url(…)` renders
 * as a link and is answered by the browser, and an action can be hidden behind
 * `visible()` on one request and clicked on another. Neither is a fault worth
 * throwing over. What *would* be worth throwing over — a name the surface could
 * not resolve at all — is decided by the surface, before it ever reaches here.
 * ## Why it also classifies
 *
 * {@see ClassifiesComponentActions} answers two questions about an action that
 * only this side of the boundary can answer, and they are the same two a caller
 * has to ask *before* calling `runComponentAction()` — so the alternative was a
 * second class making the identical `instanceof BaseAction` narrow, resolved from
 * the same container, for the same caller. One object, two contracts.
 */
final readonly class ComponentActionRunner implements ClassifiesComponentActions, RunsComponentActions
{
    public function __construct(private ActionCallbackInvoker $invoker) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function runComponentAction(ActionContract $action, array $context = []): void
    {
        if (! $action instanceof BaseAction) {
            return;
        }

        $callback = $action->getActionCallback();

        if ($callback === null) {
            return;
        }

        $this->invoker->invoke($callback, $context);
    }

    /**
     * A foreign implementation of the contract asks for nothing.
     *
     * Same narrow, same silence as the runner: `ActionContract` promises a name
     * and a visibility flag, and an action that is neither of ours nor carries a
     * modal cannot have attached one.
     */
    public function needsPrompt(ActionContract $action): bool
    {
        return $action instanceof BaseAction && $action->hasModal();
    }

    /**
     * `canExecute()`, not `isHidden()`.
     *
     * The two differ exactly where it matters to a surface that offers a list to
     * a user: `isHidden()` is what the author wrote about rendering, while
     * `canExecute()` folds in `permission()`, `authorize()` and
     * `authorizeUsing()`. Offering the first as though it were the second is how
     * a palette lists something the user may not run.
     *
     * A foreign implementation falls back to the one thing the contract does
     * promise, so it is filtered on visibility rather than waved through.
     */
    public function isRunnable(ActionContract $action, mixed $context = null): bool
    {
        return $action instanceof BaseAction
            ? $action->canExecute($context)
            : ! $action->isHidden();
    }
}
