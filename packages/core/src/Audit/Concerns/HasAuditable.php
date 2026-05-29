<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Audit\Events\RecordCreated;
use NyonCode\WireCore\Audit\Events\RecordDeleted;
use NyonCode\WireCore\Audit\Events\RecordUpdated;

/**
 * Trait HasAuditable
 *
 * @phpstan-require-extends Model
 *
 * Add to any Eloquent model to enable automatic audit logging
 * via Eloquent model events (created, updated, deleted).
 *
 * Usage:
 *   class Order extends Model
 *   {
 *       use HasAuditable;
 *
 *       protected function getAuditExclude(): array
 *       {
 *           return ['cached_total'];
 *       }
 *   }
 */
trait HasAuditable
{
    /**
     * Boot the trait — register model event listeners.
     */
    public static function bootHasAuditable(): void
    {
        static::created(function (self $model): void {
            $attributes = $model->filterAuditAttributes($model->getAttributes());

            event(new RecordCreated($model, $attributes));
        });

        static::updated(function (self $model): void {
            $dirty = $model->getDirty();

            if (empty($dirty)) {
                return;
            }

            $oldValues = $model->filterAuditAttributes(
                array_intersect_key($model->getOriginal(), $dirty)
            );
            $newValues = $model->filterAuditAttributes($dirty);

            if (empty($newValues)) {
                return;
            }

            event(new RecordUpdated($model, $oldValues, $newValues));
        });

        static::deleted(function (self $model): void {
            $attributes = $model->filterAuditAttributes($model->getOriginal());

            event(new RecordDeleted($model, $attributes));
        });
    }

    /**
     * Get audit log entries for this model.
     *
     * @return MorphMany<AuditEntry, self>
     */
    public function audits(): MorphMany
    {
        $modelClass = config('wire-core.audit.model', AuditEntry::class);

        return $this->morphMany($modelClass, 'auditable')
            ->latest('created_at');
    }

    /**
     * Columns to exclude from audit logging.
     *
     * Override in your model to customize.
     *
     * @return array<int, string>
     */
    protected function getAuditExclude(): array
    {
        return [];
    }

    /**
     * Columns to include in audit logging (whitelist mode).
     *
     * If non-empty, only these columns will be audited.
     * Override in your model to customize.
     *
     * @return array<int, string>
     */
    protected function getAuditInclude(): array
    {
        return [];
    }

    /**
     * Filter attributes based on include/exclude configuration.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filterAuditAttributes(array $attributes): array
    {
        $include = $this->getAuditInclude();

        if (! empty($include)) {
            $attributes = array_intersect_key($attributes, array_flip($include));
        }

        $exclude = $this->getAuditExclude();

        if (! empty($exclude)) {
            $attributes = array_diff_key($attributes, array_flip($exclude));
        }

        return $attributes;
    }
}
