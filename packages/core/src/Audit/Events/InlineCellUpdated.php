<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatched when an inline cell edit is saved in a table.
 */
final readonly class InlineCellUpdated implements AuditableEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $modelType,
        public mixed $recordId,
        public string $column,
        public mixed $oldValue,
        public mixed $newValue,
        public array $metadata = [],
    ) {}

    public function getAuditEventType(): string
    {
        return 'cell_updated';
    }

    public function getAuditableType(): string
    {
        return $this->modelType;
    }

    public function getAuditableId(): mixed
    {
        return $this->recordId;
    }

    public function getOldValues(): array
    {
        return [$this->column => $this->oldValue];
    }

    public function getNewValues(): array
    {
        return [$this->column => $this->newValue];
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
