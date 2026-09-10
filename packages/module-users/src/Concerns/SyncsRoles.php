<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Support\Permissions;
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
 * is what the select stores.
 *
 * **This used to trust the select.** The docblock said "Nothing here decides who
 * may edit roles; that is the page's policy, through `Gate`" — and the page had
 * no policy, so nothing decided it at all. The names arrive as raw component
 * state from the browser, `Roles::options()` offers every role there is, and the
 * write went through unread: anybody who reached the edit form could name the
 * super-admin role on their own account and become one.
 *
 * The route is guarded now ({@see Permissions}), and this is the second lock
 * rather than the same one twice. A pivot write that grants authority is worth
 * checking where it happens, because the ways to reach a form are many and they
 * are not all routes — a bulk action, a wizard step, an application's own page
 * composing this trait. Defence in depth, at the one line that hands out power.
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
            $selected = is_array($selected) ? $selected : [];

            // Refuse the whole write rather than silently syncing the part that
            // was allowed: a form that says "these are the roles" and saves a
            // different set is worse than one that saves nothing, because the
            // screen afterwards looks like it worked.
            if (! $this->mayAssignRoles($record, $selected)) {
                return;
            }

            $record->syncRoles($selected);
        });
    }

    /**
     * Whether the person doing this may hand out exactly this set.
     *
     * Asked of the *change*, not of the list: a save that leaves the roles as
     * they were is not an escalation and must not need the ability, or opening a
     * user, fixing a typo in their name and pressing save would strip their
     * roles for anyone who cannot grant roles.
     *
     * Gate answers it, like every other check in this stack, so a wildcard, a
     * policy and the super-admin bypass in `laravel-permission-extended` all
     * work without this method knowing they exist.
     *
     * @param  array<int, mixed>  $selected
     */
    protected function mayAssignRoles(Model $record, array $selected): bool
    {
        $current = method_exists($record, 'getRoleNames')
            ? $record->getRoleNames()->all()
            : [];

        $before = array_values(array_map('strval', $current));
        $after = array_values(array_map('strval', $selected));

        sort($before);
        sort($after);

        if ($before === $after) {
            return true;
        }

        $ability = Permissions::for('users', 'update');

        // Nothing named means the installation deliberately opened these screens
        // (the config says how), and this is not the place to overrule that.
        return $ability === null || Gate::allows($ability, $record);
    }
}
