<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Contracts;

/**
 * Contract for events that should be captured by the audit log.
 *
 * All audit events carry user, model, and change data for tracking
 * who did what, when, and to which record.
 */
interface AuditableEvent
{
    /**
     * Get the audit event type identifier.
     *
     * Example: 'created', 'updated', 'deleted', 'bulk_action', 'cell_updated'
     */
    public function getAuditEventType(): string;

    /**
     * Get the auditable model class name (morphable type).
     */
    public function getAuditableType(): ?string;

    /**
     * Get the auditable model ID (morphable ID).
     */
    public function getAuditableId(): mixed;

    /**
     * Get the old values (before change).
     *
     * @return array<string, mixed>|null
     */
    public function getOldValues(): ?array;

    /**
     * Get the new values (after change).
     *
     * @return array<string, mixed>|null
     */
    public function getNewValues(): ?array;

    /**
     * Get additional metadata (IP, user agent, context, etc.).
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
}
