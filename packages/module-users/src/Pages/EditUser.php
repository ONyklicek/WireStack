<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\ResolvesScopedRecord;
use NyonCode\WireModuleUsers\Concerns\SyncsRoles;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\AccountGuard;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
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
    use ResolvesScopedRecord {
        resolveRecord as resolveScopedRecord;
    }
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

    protected function scopeRecordQuery(Builder $query): Builder
    {
        return Teams::scopeMembers($query);
    }

    /**
     * The account, if this person may see it — and a 403 if it is a super-admin they are not.
     *
     * On every request the page makes, the save included, for the reason the
     * role page gives: the form cannot be built without the record, so an
     * account opened by replaying a request is refused the same way.
     */
    protected function resolveRecord(): Model|RecordContract|null
    {
        $record = $this->resolveScopedRecord();

        if ($record instanceof Model && ! AccountGuard::mayEdit($record)) {
            abort(403);
        }

        return $record;
    }
}
