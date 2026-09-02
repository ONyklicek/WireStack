<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\CheckboxColumn;
use NyonCode\WireTable\Columns\TagsColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Models\Document;

/**
 * The third resource: the second entry in the "Operations" group.
 *
 * Its whole job in V2.6 §0b step 1 is to be sorted *before*
 * {@see TaskResource} while being registered *after* it, so a sidebar that
 * ignored `NavigationItem::sort()` would render visibly wrong rather than
 * identically.
 *
 * It also names its own menu label, which the other two deliberately do not:
 * one entry that overrides and two that fall back to the resource's plural is
 * what keeps both halves of that rule rendered on one screen.
 */
final class DocumentResource implements DescribesResource, ProvidesNavigation, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Document::class;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Files')
            ->icon('outline:document-duplicate')
            ->group('operations')
            ->sort(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TagsColumn::make('tags'),
                CheckboxColumn::make('is_published')->label('Published'),
            ])
            ->defaultSort('title');
    }
}
