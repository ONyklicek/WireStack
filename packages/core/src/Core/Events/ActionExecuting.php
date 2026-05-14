<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched before an action executes.
 */
final readonly class ActionExecuting
{
    public function __construct(
        public string $tableId,
        public string $actionName,
        /** @var array<int, mixed> */
        public array $recordIds = [],
    ) {}
}
