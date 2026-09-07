<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsRoles;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WirePanels\Resources\Pages\EditPage;

/**
 * One user, edited.
 *
 * Two things this page does that the base page cannot know about: it keeps the
 * password hash out of the form state, and it seeds the roles select from a
 * relation rather than from a column.
 */
class EditUser extends EditPage
{
    use SyncsRoles;

    protected static ?string $resource = UserResource::class;

    public function form(Form $form): Form
    {
        return $this->syncRolesAfterSave(parent::form($form));
    }

    /**
     * What the form is seeded with.
     *
     * **The password is removed rather than trusted to be hidden.** Laravel's
     * own `User` marks it `$hidden`, so `attributesToArray()` drops it — and an
     * application's model that does not is the one where a bcrypt hash would
     * ride into the Livewire snapshot, be sent to the browser, and be written
     * back re-hashed on the next save. One `unset` is cheaper than depending on
     * someone else's `$hidden`.
     *
     * @return array<string, mixed>
     */
    protected function recordData(): array
    {
        $data = parent::recordData();

        unset($data[UserResource::field('password')]);

        if (Roles::enabled()) {
            $record = $this->resolveRecord();

            $data['roles'] = $record instanceof Model && method_exists($record, 'roles')
                ? $record->roles->pluck('name')->all()
                : [];
        }

        return $data;
    }
}
