<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Runs before callbacks prior to action execution.
 *
 * Halts the pipeline if any callback returns false.
 */
final class BeforeCallbacksStage implements ActionStage
{
    /**
     * {@inheritDoc}
     */
    public function handle(ActionContext $context, Closure $next): ActionResult
    {
        /** @var array<int, callable(ActionContext): mixed> $callbacks */
        $callbacks = $context->get('beforeCallbacks', []);

        foreach ($callbacks as $callback) {
            $result = $callback($context);

            if ($result === false) {
                return ActionResult::halt();
            }
        }

        return $next($context);
    }
}
