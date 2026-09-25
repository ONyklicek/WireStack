<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Livewire\Resources\ManageTeams;
use Workbench\App\Models\Team;

/**
 * Teams as a simple resource: one field, so it is managed on one page with
 * create and edit in modals ({@see ManageTeams}).
 *
 * Deliberately not registered — the preview mounts the page directly, and the
 * users module already owns what the menu shows about teams.
 */
final class TeamResource implements DescribesResource, ProvidesResourceForm, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Team::class;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([TextColumn::make('name')->searchable()]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->required()]);
    }
}
