<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;

/**
 * A record page that finds its record among the ones the viewer may see.
 *
 * A list scoped to the current team is not enough, because a URL is not a list:
 * without this, `users/42/edit` opened an account of another team for anybody
 * who could edit accounts in their own. The record is looked up through the
 * same scope the page's list uses ({@see scopeRecordQuery()}), and one outside
 * it is a 404 rather than a 403, so the answer does not say that it exists.
 */
trait ResolvesScopedRecord
{
    protected function resolveRecord(): Model|RecordContract|null
    {
        $resource = static::$resource;
        $model = $resource !== null ? $resource::modelClass() : null;

        if ($model === null || $this->record === null || $this->record instanceof RecordContract) {
            return parent::resolveRecord();
        }

        $key = $this->record instanceof Model ? $this->record->getKey() : $this->record;

        return $this->scopeRecordQuery($model::query())->find($key) ?? abort(404);
    }

    /**
     * The scope the page's list draws, applied to the record lookup.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    abstract protected function scopeRecordQuery(Builder $query): Builder;
}
