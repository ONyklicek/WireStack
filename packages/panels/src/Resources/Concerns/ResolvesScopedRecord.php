<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;

/**
 * A record page that finds its record among the ones the viewer may see.
 *
 * **A scoped list is not a scoped page, because a URL is not a list.** Without
 * this, `users/42/edit` opened an account of another team for anybody who could
 * edit accounts in their own, and `notifications/{id}` handed over — and marked
 * read — somebody else's. Both pages had the right scope; neither applied it
 * where the key arrives.
 *
 * The record is looked up through the same scope the page's list uses
 * ({@see scopeRecordQuery()}), and one outside it is a **404 rather than a
 * 403**, so the answer does not say that it exists.
 *
 * It lives here rather than beside its first caller because its second caller is
 * in another module, and a module may not require a module: any resource page
 * with a scoped list has this question, so `wire-panels` is the lowest layer
 * that can own it.
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
