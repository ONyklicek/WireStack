<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
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
     * Columns never written to the trail, whatever the configuration says.
     *
     * **A floor, not a default.** `exclude_columns` is a published config file,
     * so a list of defaults protects only applications that never published one
     * — and the ones that did, years ago, keep whatever was current then. The
     * trail is long-lived by design and the audit screen renders these pairs to
     * anyone who can open it, so a secret that reaches it is a secret on a page.
     *
     * Patterns rather than names because the names are not knowable: an
     * application's `api_token`, `stripe_secret` and `two_factor_recovery_codes`
     * are all columns this package has never heard of. Matched with `Str::is()`,
     * so `exclude_columns` may use `*` too.
     *
     * Removal rather than a `[redacted]` marker, to match what `password` has
     * always done here — and because a trail that records *that* a token
     * rotated, without the value, is what the remaining columns already say.
     *
     * @var array<int, string>
     */
    protected const NEVER_LOGGED = [
        'password*',
        'secret',
        '*_secret',
        '*_secrets',
        'token',
        '*_token',
        '*_tokens',
        'two_factor_*',
        '*recovery_codes',
        'api_key',
        '*_api_key',
    ];

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

        /** @var array<int, string> $configured */
        $configured = config('wire-core.audit.exclude_columns', [
            'password',
            'remember_token',
        ]);

        $patterns = [...self::NEVER_LOGGED, ...$configured];

        return array_filter(
            $values,
            static fn (string $column): bool => ! Str::is($patterns, $column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Resolve the current authenticated user ID.
     *
     * Returns int|string: the actor key may be a UUID/ULID, so a ?int return
     * would drop a string identifier (a caught TypeError under strict_types).
     */
    protected function resolveUserId(): int|string|null
    {
        try {
            /** @var Authenticatable|null $user */
            $user = auth()->guard()->user();

            return $user?->getAuthIdentifier();
        } catch (Throwable) {
            // "No user" is a legitimate answer, not a failure: seeders, queued
            // jobs and console commands audit changes with no auth context at
            // all. An audit entry without an actor is still worth writing —
            // losing the entry to protect its user column would be the bug.
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
            // As above: outside an HTTP request there is no IP or agent to
            // record, which is missing context rather than a failed audit.
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
