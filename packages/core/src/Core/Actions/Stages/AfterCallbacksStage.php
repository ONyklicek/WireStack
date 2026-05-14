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

        /** @var array<int, callable(ActionContext, ActionResult): void> $callbacks */
        $callbacks = $context->get('afterCallbacks', []);

        foreach ($callbacks as $callback) {
            $callback($context, $result);
        }

        return $result;
    }
}
