<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;

/**
 * An exact optimistic-lock version for a model: an integer column the database
 * raises by one on every Eloquent update.
 *
 * Without it {@see RecordVersion} versions a record by `updated_at`, which is
 * stored to the second. Two writes inside one second therefore leave the same
 * stamp, and a client holding the stamp from the first is not told about the
 * second — the lock has a one-second hole by construction. On a grid where
 * several people fill in the same order that hole is exactly where the work
 * happens.
 *
 * The column is raised by the database, never computed in PHP:
 * `SET lock_version = lock_version + 1`. Two processes saving the same row
 * without a row lock would both compute `N + 1` in PHP and leave one version
 * for two different states; the expression gives them `N + 1` and `N + 2`. The
 * value the write produced is read back in the same breath (one primary-key
 * select), so the model — and the stamp the client is handed — holds the real
 * number, not the expression.
 *
 * What it does not see is what never fires a model event: `Model::query()->update()`,
 * `increment()`, `insert()`, `upsert()`. Those still move `updated_at`, and
 * {@see RecordVersion} keeps comparing that as well, so such a write is still
 * caught — at the old one-second resolution.
 *
 * The column: `$table->unsignedBigInteger('lock_version')->default(0)`. A
 * different name is `protected string $lockVersionColumn = '…'` on the model.
 *
 * @mixin Model
 */
trait HasLockVersion
{
    public static function bootHasLockVersion(): void
    {
        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getLockVersionColumn();

            if ($model->getAttribute($column) === null) {
                $model->setAttribute($column, 1);
            }
        });

        static::updating(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getLockVersionColumn();

            $model->setAttribute(
                $column,
                $model->getConnection()->raw($model->getConnection()->getQueryGrammar()->wrap($column).' + 1'),
            );
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getLockVersionColumn();

            if (! (($model->getAttributes()[$column] ?? null) instanceof Expression)) {
                return;
            }

            $written = $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->value($column);

            $model->setRawAttributes(array_merge(
                $model->getAttributes(),
                [$column => $written === null ? null : (int) $written],
            ));

            // `getChanges()` was taken before this listener ran and still holds
            // the expression; an audit trail or an `updated` observer after us
            // would record an object instead of the number. The original is still
            // the pre-write state here (save() syncs it after the event), so
            // recomputing the changes gives the right pair.
            $model->syncChanges();
        });
    }

    public function getLockVersionColumn(): string
    {
        return property_exists($this, 'lockVersionColumn') && is_string($this->lockVersionColumn)
            ? $this->lockVersionColumn
            : 'lock_version';
    }
}
