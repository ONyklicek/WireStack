<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Livewire\Resources\EditProject;
use Workbench\App\Livewire\Resources\ListProjects;
use Workbench\App\Models\Project;

/**
 * Projects, routed only in the `tenants` zone: every row belongs to a company,
 * and the company is the one in the URL (ADR 0040).
 */
final class ProjectResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceForm, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Project::class;
    }

    public static function pages(): array
    {
        return ['index' => ListProjects::class, 'edit' => EditProject::class];
    }

    public static function navigation(): NavigationItem
    {
        // Only inside a company: outside one there are no projects to list, and
        // the other zones' menus stay as they were.
        return NavigationItem::make()
            ->icon('outline:briefcase')
            ->sort(10)
            ->visible(fn (): bool => app(CurrentTenant::class)->has());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                BadgeColumn::make('status'),
            ])
            // Built on every render, round trips included — which is what
            // verify-tenants checks stays inside the company in the URL.
            ->recordUrl(fn (Project $record): ?string => self::url('edit', $record))
            ->defaultSort('name');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
        ]);
    }
}
