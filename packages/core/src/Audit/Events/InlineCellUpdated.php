<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatch for an inline cell edit that should be audited.
 *
 * Deliberately **not** dispatched by the framework, despite the table having an
 * obvious seam for it (`Services\CellEditPipeline`): a `HasAuditable` model
 * already records that same write as `RecordUpdated` through its Eloquent
 * `updated` event, so firing this from the cell pipeline would log every inline
 * edit twice — once as `updated`, once as `cell_updated`, with identical
 * old/new values.
 *
 * It exists for the writes that bypass model events and would otherwise go
 * unrecorded: a raw `Builder::update()`, a pivot column, a value persisted
 * through `editableUsing()`. Dispatch it yourself there. See
 * `docs/core/audit.md`.
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
