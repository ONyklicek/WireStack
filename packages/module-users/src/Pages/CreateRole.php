<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsPermissions;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

class CreateRole extends CreatePage
{
    use SyncsPermissions;

    protected static ?string $resource = RoleResource::class;

    public function form(Form $form): Form
    {
        // A team's manager makes a role of their team; somebody who works across
        // every team makes a global one. Replaces the resource's mutation with
        // one that includes it, because a form holds one.
        return $this->syncPermissionsAfterSave(parent::form($form))
            ->mutateDataBeforeSave(static fn (array $data): array => Teams::placeNewRole(RoleResource::prepareForSave($data)));
    }
}
