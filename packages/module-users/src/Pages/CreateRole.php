<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsPermissions;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

class CreateRole extends CreatePage
{
    use SyncsPermissions;

    protected static ?string $resource = RoleResource::class;

    public function form(Form $form): Form
    {
        return $this->syncPermissionsAfterSave(parent::form($form));
    }
}
