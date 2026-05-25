<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionResult;

/**
 * Typed payload for the 'action.executed' hook.
 *
 * Dispatched after the ActionPipeline completes.
 * Plugins can observe the result (e.g. for audit logging).
 */
final class ActionExecutedPayload
{
    /**
     * @param  string  $actionName  The name of the action that was executed
     * @param  ActionContext  $context  The action context
     * @param  ActionResult  $result  The result of the action pipeline
     * @param  string  $actionType  The action type: 'row', 'bulk', or 'header'
     * @param  object|null  $component  The Livewire component that executed the action
     */
    public function __construct(
        public readonly string $actionName,
        public readonly ActionContext $context,
        public readonly ActionResult $result,
        public readonly string $actionType,
        public readonly ?object $component = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actionName' => $this->actionName,
            'context' => $this->context,
            'result' => $this->result,
            'actionType' => $this->actionType,
            'component' => $this->component,
        ];
    }
}
