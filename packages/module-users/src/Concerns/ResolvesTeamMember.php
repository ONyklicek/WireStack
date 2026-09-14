<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Teams;

/**
 * A user page that finds its record among the people the viewer may see.
 *
 * The list is scoped to the current team, and a URL is not a list: without this,
 * `users/42/edit` opened an account from another team for anybody who could
 * edit accounts in their own. The record is looked up through
 * {@see Teams::scopeMembers()} — the same scope the table uses — and one outside
 * it is a 404 rather than a 403, so the answer does not say that it exists.
 */
trait ResolvesTeamMember
{
    protected function resolveRecord(): Model|RecordContract|null
    {
        $model = UserResource::modelClass();

        if ($model === null || $this->record === null || $this->record instanceof RecordContract) {
            return parent::resolveRecord();
        }

        $key = $this->record instanceof Model ? $this->record->getKey() : $this->record;

        return Teams::scopeMembers($model::query())->find($key) ?? abort(404);
    }
}
