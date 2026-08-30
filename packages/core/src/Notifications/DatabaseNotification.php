<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A notification that outlived the request that raised it.
 *
 * The transient drivers hand a message to the page being rendered; this one
 * writes it down, which is what a queued export finishing twenty minutes later
 * needs — by then the request that started it is long gone and there is no
 * component to dispatch to.
 *
 * Deliberately Laravel's `notifications` shape (uuid / type / notifiable / data
 * / read_at). An application that already has that table points
 * `wire-core.notifications.database.table` at it and reads both through its own
 * `Notifiable::notifications()`; a shape of our own would have made the two
 * mutually exclusive for no gain.
 *
 * @property string $id
 * @property string $type
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 */
class DatabaseNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->table ?? (string) config('wire-core.notifications.database.table', 'wire_notifications');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * This recipient's notifications, newest first.
     *
     * Static query helpers rather than Eloquent scopes, and that is the whole
     * decision: a scope is resolved by magic that static analysis cannot follow,
     * so every internal call site reads as an undefined method. Two vocabularies
     * for one query — scopes for consumers, something else for us — would be the
     * second wheel this codebase keeps deleting, so there is one.
     *
     * @return Builder<static>
     */
    public static function forNotifiable(Model $notifiable): Builder
    {
        $query = static::query();

        // Built statement by statement rather than chained: where()/orderBy()
        // narrow to the query builder, and returning that loses the model type
        // every caller then relies on.
        $query->where('notifiable_type', $notifiable->getMorphClass());
        $query->where('notifiable_id', (string) $notifiable->getKey());
        // Both, and in this order: created_at is what a reader means by newest,
        // and the ULID id breaks the same-second ties a bulk job produces.
        $query->orderByDesc('created_at');
        $query->orderByDesc('id');

        return $query;
    }

    /**
     * @return Builder<static>
     */
    public static function unreadFor(Model $notifiable): Builder
    {
        $query = static::forNotifiable($notifiable);
        $query->whereNull('read_at');

        return $query;
    }

    /** Idempotent: marking an already-read notification does not move its timestamp. */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    public function markAsUnread(): void
    {
        if ($this->read_at !== null) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Back into the value object it was raised as, so a stored notification
     * renders through exactly the same view as a live one.
     */
    public function toNotification(): Notification
    {
        $data = $this->data;

        $notification = Notification::make(
            (string) ($data['type'] ?? 'info'),
            (string) ($data['message'] ?? ''),
        );

        // Reassigned, not called for effect: Notification is immutable and every
        // modifier hands back a new instance.
        foreach (['title', 'icon', 'duration', 'position'] as $key) {
            if (isset($data[$key])) {
                $notification = $notification->{$key}($data[$key]);
            }
        }

        return $notification;
    }
}
