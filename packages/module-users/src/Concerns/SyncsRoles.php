<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Support\Roles;

/**
 * The half of a user save that is not a column.
 *
 * Roles live in a pivot table, so they are stripped from the data on the way to
 * `save()` (the resource's form does that) and written afterwards, when the
 * record exists — which is the only order that works for a *new* user, who has
 * no id until then.
 *
 * `syncRoles()` comes from the permission package's trait and takes names, which
 * is what the select stores. Nothing here decides who may edit roles; that is
 * the page's policy, through `Gate`.
 */
trait SyncsRoles
{
    protected function syncRolesAfterSave(Form $form): Form
    {
        if (! Roles::enabled()) {
            return $form;
        }

        return $form->afterSave(function (mixed $record): void {
            if (! $record instanceof Model || ! method_exists($record, 'syncRoles')) {
                return;
            }

            $selected = $this->data['roles'] ?? [];

            $record->syncRoles(is_array($selected) ? $selected : []);
        });
    }
}
