<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;

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

            $record->syncPermissions(is_array($selected) ? $selected : []);
        });
    }
}
