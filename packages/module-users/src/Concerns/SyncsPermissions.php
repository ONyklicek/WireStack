<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Support\RoleGrants;

/**
 * A role's permissions, written after the role exists.
 *
 * The same shape as {@see SyncsRoles} and for the same reason: a pivot cannot be
 * written for a record that has no id yet.
 */
trait SyncsPermissions
{
    protected function syncPermissionsAfterSave(Form $form): Form
    {
        return $form->afterSave(function (mixed $record): void {
            if (! $record instanceof Model || ! method_exists($record, 'syncPermissions')) {
                return;
            }

            $selected = $this->data['permissions'] ?? [];

            // Only the change this person may make (RoleGrants): a permission
            // they do not hold is neither added nor taken away, so a team's
            // manager cannot write into a role what nobody gave them.
            $record->syncPermissions(RoleGrants::clampPermissions(
                method_exists($record, 'permissions') ? $record->permissions()->pluck('name')->all() : [],
                is_array($selected) ? $selected : [],
            ));
        });
    }
}
