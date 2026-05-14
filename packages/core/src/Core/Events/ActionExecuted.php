<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched after an action executes.
 */
final readonly class ActionExecuted
{
    public function __construct(
        public string $tableId,
        public string $actionName,
        /** @var array<int, mixed> */
        public array $recordIds = [],
        public bool $success = true,
    ) {}
}
