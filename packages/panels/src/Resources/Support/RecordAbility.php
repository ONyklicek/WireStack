<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Whether the signed-in user may do something to one record a page shows.
 *
 * The model's policy decides when it has one, because that is where an
 * application already wrote down who may delete an invoice. Without one the
 * answer falls back to the page the caller names — a record you may not edit is
 * not one you may delete — and a fallback of `null` means there is no such page,
 * which is a refusal rather than an open door: a read-only resource must not
 * grow a *Delete* button because nobody wrote a policy for it.
 */
final class RecordAbility
{
    /**
     * @param  string  $ability  The policy method — `delete`, `restore`, `forceDelete`.
     * @param  bool  $fallback  The answer when the model has no policy.
     */
    public function allows(string $ability, Model $record, bool $fallback): bool
    {
        if (Gate::getPolicyFor($record) === null) {
            return $fallback;
        }

        return Gate::allows($ability, $record);
    }
}
