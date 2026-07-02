<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;
use Throwable;

/**
 * Central audit logger — listens to AuditableEvent instances and persists AuditEntry records.
 *
 * Respects configuration for enabled state, excluded columns, and custom model override.
 */
class AuditLogger
{
    private static bool $disabled = false;

    /**
     * Log an auditable event.
     */
    public function log(AuditableEvent $event): ?AuditEntry
    {
        if (self::$disabled) {
            return null;
        }

        if (! $this->isEnabled()) {
            return null;
        }

        if (! $this->shouldLogEvent($event->getAuditEventType())) {
            return null;
        }

        $oldValues = $this->filterExcludedColumns($event->getOldValues());
        $newValues = $this->filterExcludedColumns($event->getNewValues());

        // Skip if update event has no actual changes after filtering
        if ($event->getAuditEventType() === 'updated' && $oldValues === $newValues) {
            return null;
        }

        $modelClass = $this->getAuditEntryModel();

        /** @var AuditEntry $entry */
        $entry = new $modelClass;
        $entry->event = $event->getAuditEventType();
        $entry->auditable_type = $event->getAuditableType() ?? '';
        $entry->auditable_id = $event->getAuditableId();
        $entry->user_id = $this->resolveUserId();
        $entry->old_values = $oldValues;
        $entry->new_values = $newValues;
        $entry->metadata = array_merge($this->resolveRequestMetadata(), $event->getMetadata());
        $entry->save();

        return $entry;
    }

    /**
     * Disable auditing for the duration of a callback.
     *
     * Useful for seeders, imports, and data migrations.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withoutAuditing(Closure $callback): mixed
    {
        $previous = self::$disabled;
        self::$disabled = true;

        try {
            return $callback();
        } finally {
            self::$disabled = $previous;
        }
    }

    /**
     * Prune audit entries older than the retention period — the given number of
     * days, or the configured `wire-core.audit.retention_days` when omitted.
     * Returns the number of deleted entries (0 when no period is set).
     */
    public function prune(?int $days = null): int
    {
        $days ??= $this->getRetentionDays();

        if ($days === null) {
            return 0;
        }

        $modelClass = $this->getAuditEntryModel();

        return $modelClass::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Check if audit logging is globally enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('wire-core.audit.enabled', true);
    }

    /**
     * Check if a specific event type should be logged.
     */
    protected function shouldLogEvent(string $eventType): bool
    {
        /** @var array<int, string>|null $events */
        $events = config('wire-core.audit.events');

        if ($events === null) {
            return true;
        }

        return in_array($eventType, $events, true);
    }

    /**
     * Filter out excluded columns from values array.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function filterExcludedColumns(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        /** @var array<int, string> $excluded */
        $excluded = config('wire-core.audit.exclude_columns', [
            'password',
            'remember_token',
        ]);

        return array_diff_key($values, array_flip($excluded));
    }

    /**
     * Resolve the current authenticated user ID.
     */
    protected function resolveUserId(): ?int
    {
        try {
            /** @var Authenticatable|null $user */
            $user = auth()->guard()->user();

            return $user?->getAuthIdentifier();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve request metadata (IP, user agent).
     *
     * @return array<string, mixed>
     */
    protected function resolveRequestMetadata(): array
    {
        if (! App::bound('request')) {
            return [];
        }

        try {
            $request = request();

            return array_filter([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get the configured AuditEntry model class.
     *
     * @return class-string<AuditEntry>
     */
    protected function getAuditEntryModel(): string
    {
        /** @var class-string<AuditEntry> $model */
        $model = config('wire-core.audit.model', AuditEntry::class);

        return $model;
    }

    /**
     * Get the configured retention period in days.
     */
    protected function getRetentionDays(): ?int
    {
        /** @var int|null $days */
        $days = config('wire-core.audit.retention_days');

        return $days;
    }
}
