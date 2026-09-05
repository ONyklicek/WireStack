<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Livewire\Resources\ListTasks;
use Workbench\App\Models\Task;

/**
 * The workbench's second resource — the one that makes a *menu* mean something.
 *
 * V2.6 §0b: `Workspace` groups and orders entries, but with a single registered
 * resource there is exactly one entry in exactly one group, so neither the
 * grouping nor the ordering has ever been visible anywhere. This one sits in a
 * different group from {@see InvoiceResource}, and shares its group with
 * {@see DocumentResource}, which is what gives the sidebar two headings and one
 * heading with two entries under it.
 *
 * Deliberately smaller than the invoice resource: a table and a menu entry, no
 * form, no infolist, no relation manager. A resource declaring only the surfaces
 * it has is the ordinary case, and the list page must not care.
 */
final class TaskResource implements DescribesResource, GloballySearchable, ProvidesNavigation, ProvidesPages, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Task::class;
    }

    public static function globallySearchableAttributes(): array
    {
        return ['title', 'owner_name'];
    }

    /**
     * A list and nothing else — which is the point of declaring pages at all.
     *
     * The URL a search result and a menu entry carry is built from this: neither
     * names a path, `ResolvesPageUrls` answers from the route this registers, and
     * a resource that declares nothing here (see {@see DocumentResource}) simply
     * has no link anywhere. That is the whole of ADR 0026 on one resource.
     */
    public static function pages(): array
    {
        return ['index' => ListTasks::class];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: self::key(),
            recordKey: $record->getKey(),
            title: $record->title,
            subtitle: $record->owner_name.' · '.$record->status,
            icon: 'outline:check-circle',
        );
    }

    /**
     * Sorted *after* Documents while being registered *before* it, so the menu
     * order and the declaration order disagree. That disagreement is the only
     * thing that can tell a rendered sidebar apart from an unsorted one.
     */
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()
            ->icon('outline:check-circle')
            ->group('operations')
            ->sort(20)
            ->badge(fn (): int => Task::query()->where('completed', false)->count());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                BadgeColumn::make('status'),
                TextColumn::make('owner_name')->label('Owner')->searchable(),
                TextColumn::make('due_at')->dateTime('d.m.Y'),
            ])
            ->defaultSort('sort_order');
    }
}
