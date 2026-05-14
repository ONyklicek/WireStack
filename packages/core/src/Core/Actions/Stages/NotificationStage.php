<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Handles notification dispatching.
 *
 * Stores notification data in context for later dispatch by the UI layer.
 */
final class NotificationStage implements ActionStage
{
    /**
     * {@inheritDoc}
     */
    public function handle(ActionContext $context, Closure $next): ActionResult
    {
        $result = $next($context);

        if ($result->notification !== null) {
            $context->set('notification', [
                'message' => $result->notification,
                'type' => $result->notificationType ?? 'success',
            ]);
        }

        return $result;
    }
}
