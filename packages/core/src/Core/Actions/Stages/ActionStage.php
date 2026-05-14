<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions\Stages;

use Closure;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Interface for action pipeline stages.
 */
interface ActionStage
{
    /**
     * Handle the action context and pass to the next stage.
     */
    public function handle(ActionContext $context, Closure $next): ActionResult;
}
