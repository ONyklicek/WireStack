<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\ResolvesScopedRecord;
use NyonCode\WireModuleUsers\Concerns\SyncsPermissions;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WirePanels\Resources\Pages\EditPage;

class EditRole extends EditPage
{
    use ResolvesScopedRecord {
        resolveRecord as resolveScopedRecord;
    }
    use SyncsPermissions;

    protected static ?string $resource = RoleResource::class;

    public function form(Form $form): Form
    {
        return $this->syncPermissionsAfterSave(parent::form($form));
    }

    /** @return array<string, mixed> */
    protected function recordData(): array
    {
        $data = parent::recordData();
        $record = $this->resolveRecord();

        $data['permissions'] = $record instanceof Model && method_exists($record, 'permissions')
            ? $record->permissions->pluck('name')->all()
            : [];

        return $data;
    }

    /**
     * The role, if this person may see it — and a 403 if they may see it and not change it.
     *
     * Asked on every request the page makes, the save included, because the
     * form cannot be built without the record: a role opened read-only cannot
     * be written by replaying the save.
     */
    protected function resolveRecord(): Model|RecordContract|null
    {
        $record = $this->resolveScopedRecord();

        if ($record instanceof Model && ! Roles::mayChange($record)) {
            abort(403);
        }

        return $record;
    }

    protected function scopeRecordQuery(Builder $query): Builder
    {
        return Teams::scopeRoles($query);
    }
}
