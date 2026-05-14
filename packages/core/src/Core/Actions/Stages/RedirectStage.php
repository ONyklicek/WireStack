<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Handles redirect logic.
 *
 * Marks the redirect URL in context for the UI layer to process.
 */
final class RedirectStage implements ActionStage
{
    /**
     * {@inheritDoc}
     */
    public function handle(ActionContext $context, Closure $next): ActionResult
    {
        $result = $next($context);

        if ($result->shouldRedirect()) {
            $context->set('redirect', $result->redirect);
        }

        return $result;
    }
}
