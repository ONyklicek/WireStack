<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for audit log entries.
 *
 * Stores who did what, when, and to which record — with old/new value diffs.
 *
 * @property int $id
 * @property string $event
 * @property string $auditable_type
 * @property int|string|null $auditable_id
 * @property int|null $user_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $guarded = [];

    /**
     * Declared as a property, not the casts() method: the method form is only
     * consulted from Laravel 11 up, and this package supports 10.x — with the
     * method, the JSON columns were silently written as raw PHP arrays there.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The auditable (polymorphic) record.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who performed the action.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('wire-core.audit.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Scope to a specific auditable record.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRecord(Builder $query, Model $record): Builder
    {
        return $query
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey());
    }

    /**
     * Scope to a specific event type.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    /**
     * Scope to a specific user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to entries older than given days.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Get the changed attributes as a diff (keys present in both old and new).
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChanges(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changes = [];

        // Union of both key sets (array union keeps every key exactly once).
        foreach (array_keys($old + $new) as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal !== $newVal) {
                $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        return $changes;
    }
}
