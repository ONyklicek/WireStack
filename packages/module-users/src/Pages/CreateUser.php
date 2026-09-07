<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsRoles;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

/** A new user, with a password that is required exactly once. */
class CreateUser extends CreatePage
{
    use SyncsRoles;

    protected static ?string $resource = UserResource::class;

    public function form(Form $form): Form
    {
        return $this->syncRolesAfterSave(parent::form($form));
    }
}
