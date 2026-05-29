<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatched when a record is deleted.
 */
final readonly class RecordDeleted implements AuditableEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Model $record,
        public array $oldValues = [],
        public array $metadata = [],
    ) {}

    public function getAuditEventType(): string
    {
        return 'deleted';
    }

    public function getAuditableType(): string
    {
        return $this->record->getMorphClass();
    }

    public function getAuditableId(): mixed
    {
        return $this->record->getKey();
    }

    public function getOldValues(): array
    {
        return $this->oldValues ?: $this->record->getOriginal();
    }

    public function getNewValues(): ?array
    {
        return null;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
