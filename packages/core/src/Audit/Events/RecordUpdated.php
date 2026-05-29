<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatched when an existing record is updated.
 */
final readonly class RecordUpdated implements AuditableEvent
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Model $record,
        public array $oldValues = [],
        public array $newValues = [],
        public array $metadata = [],
    ) {}

    public function getAuditEventType(): string
    {
        return 'updated';
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
        return $this->oldValues;
    }

    public function getNewValues(): array
    {
        return $this->newValues;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
