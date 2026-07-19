<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * Default click resolver for a standalone action host (WithActions).
 *
 * A click mounts the action by name: mountAction() opens the modal/slide-over for
 * modal actions and runs the callback directly for plain actions. This is the
 * fallback whenever a caller renders an action without supplying its own resolver.
 */
final class MountActionClickResolver implements ResolvesActionClick
{
    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        return "mountAction('{$action->getName()}')";
    }
}
