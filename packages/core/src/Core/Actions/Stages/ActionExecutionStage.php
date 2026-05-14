<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Executes the main action closure.
 *
 * Wraps non-ActionResult return values in a success result.
 */
final class ActionExecutionStage implements ActionStage
{
    /**
     * @param  Closure  $action  The main action closure to execute
     */
    public function __construct(
        private Closure $action,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(ActionContext $context, Closure $next): ActionResult
    {
        $result = ($this->action)($context);

        if ($result instanceof ActionResult) {
            return $result;
        }

        return ActionResult::success();
    }
}
