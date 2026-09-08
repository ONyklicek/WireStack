<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
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
 */
final readonly class ComponentActionRunner implements RunsComponentActions
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
}
