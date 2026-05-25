<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Actions\ActionContext;

/**
 * Typed payload for the 'action.executing' hook.
 *
 * Dispatched before the ActionPipeline executes.
 * Plugins can inspect or modify the context before execution.
 */
final class ActionExecutingPayload
{
    /**
     * @param  string  $actionName  The name of the action being executed
     * @param  ActionContext  $context  The action context (record, records, formData, etc.)
     * @param  string  $actionType  The action type: 'row', 'bulk', or 'header'
     * @param  object|null  $component  The Livewire component executing the action
     */
    public function __construct(
        public readonly string $actionName,
        public readonly ActionContext $context,
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
            'actionType' => $this->actionType,
            'component' => $this->component,
        ];
    }
}
