<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Events;

use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Dispatch for a bulk action that should be audited.
 *
 * Deliberately **not** dispatched by the framework from the shared action
 * pipeline (`InteractsWithActions::executeActionPipeline()`, next to
 * `ActionExecuted`), even though that is the one seam every bulk path funnels
 * through. `wire-core.audit.enabled` defaults to true while the `audit_logs`
 * migration is publish-on-demand, so an unconditional dispatch would make every
 * bulk action fatal with a missing-table `QueryException` on any app that never
 * published it — a table-wide crash to gain an audit row nobody asked for.
 *
 * Worth dispatching from an action's own callback where the app does audit: it
 * carries the *action name*, which no model event does, so it is additive rather
 * than a duplicate of the per-record `RecordUpdated`/`RecordDeleted` entries.
 * See `docs/core/audit.md`.
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
