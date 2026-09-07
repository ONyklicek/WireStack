<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsPermissions;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WirePanels\Resources\Pages\EditPage;

class EditRole extends EditPage
{
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
}
