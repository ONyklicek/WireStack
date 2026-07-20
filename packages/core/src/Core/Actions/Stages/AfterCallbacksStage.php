<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Runs after callbacks following action execution.
 *
 * Executes each callback with the context and result from the next stage.
 */
final class AfterCallbacksStage implements ActionStage
{
    /**
     * {@inheritDoc}
     */
    public function handle(ActionContext $context, Closure $next): ActionResult
    {
        $result = $next($context);

        // A halted action (or before hook) must not fire after callbacks: their
        // side effects would run before the confirmation/halt is resolved, and
        // then a second time when the action re-runs after confirm. This mirrors
        // BeforeCallbacksStage, which short-circuits without reaching this stage.
        if ($result->shouldHalt()) {
            return $result;
        }

        /** @var array<int, callable(ActionContext, ActionResult): void> $callbacks */
        $callbacks = $context->get('afterCallbacks', []);

        foreach ($callbacks as $callback) {
            $callback($context, $result);
        }

        return $result;
    }
}
