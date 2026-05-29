<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatched when a new record is created.
 */
final readonly class RecordCreated implements AuditableEvent
{
    /**
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Model $record,
        public array $newValues = [],
        public array $metadata = [],
    ) {}

    public function getAuditEventType(): string
    {
        return 'created';
    }

    public function getAuditableType(): string
    {
        return $this->record->getMorphClass();
    }

    public function getAuditableId(): mixed
    {
        return $this->record->getKey();
    }

    public function getOldValues(): ?array
    {
        return null;
    }

    public function getNewValues(): array
    {
        return $this->newValues ?: $this->record->getAttributes();
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
