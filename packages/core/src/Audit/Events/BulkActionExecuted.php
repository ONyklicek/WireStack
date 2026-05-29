<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatched when a bulk action is executed on multiple records.
 */
final readonly class BulkActionExecuted implements AuditableEvent
{
    /**
     * @param  array<int, mixed>  $recordIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $actionName,
        public string $modelType,
        public array $recordIds,
        public bool $success = true,
        public array $metadata = [],
    ) {}

    public function getAuditEventType(): string
    {
        return 'bulk_action';
    }

    public function getAuditableType(): string
    {
        return $this->modelType;
    }

    public function getAuditableId(): mixed
    {
        return null;
    }

    public function getOldValues(): ?array
    {
        return null;
    }

    public function getNewValues(): array
    {
        return [
            'action' => $this->actionName,
            'record_ids' => $this->recordIds,
            'success' => $this->success,
        ];
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
