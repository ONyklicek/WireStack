<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Concerns\SyncsRoles;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

/** A new user, with a password that is required exactly once. */
class CreateUser extends CreatePage
{
    use SyncsRoles;

    protected static ?string $resource = UserResource::class;

    public function form(Form $form): Form
    {
        // One afterSave, because a form holds one: the account joins the team
        // of whoever made it before its roles are written, since with teams a
        // role is written into the current team.
        return parent::form($form)->afterSave(function (mixed $record): void {
            if ($record instanceof Model) {
                Teams::admitToCurrentTeam($record);
            }

            $this->syncSelectedRoles($record);
        });
    }
}
