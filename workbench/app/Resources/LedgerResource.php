<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Livewire\Resources\ListLedger;
use Workbench\App\Models\GestureRow;

/**
 * A long list inside the real admin shell, for verify-admin-sticky-header.
 *
 * `stickyHeader()` pins the header to the page, and the page here is the admin
 * layout: a sticky top bar over the window, a sidebar beside the content, and
 * `--wire-sticky-top` telling the header where the bar ends. A preview page has
 * none of that, so it cannot say whether the header stops below the bar or
 * slides underneath it.
 *
 * No menu entry on purpose: the sidebar drivers count entries, and this
 * resource is reached by its URL alone.
 */
final class LedgerResource implements DescribesResource, ProvidesPages, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return GestureRow::class;
    }

    public static function pages(): array
    {
        return ['index' => ListLedger::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                BadgeColumn::make('status'),
                TextColumn::make('amount')->sortable(),
            ])
            ->paginated(false)
            ->stickyHeader()
            ->defaultSort('name');
    }
}
