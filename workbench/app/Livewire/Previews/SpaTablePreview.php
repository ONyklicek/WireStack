<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;
use Workbench\App\Models\GestureRow;
use Workbench\App\Models\User;

/**
 * Page B of the SPA-navigation fixture: everything that needs a client-side
 * controller, on one table.
 *
 * `selectable()` pulls in wireRecordSelection, the record actions pull in
 * wireRecordActions, `fillHandle()` pulls in wireFillHandle (core bundle),
 * `columnReorderable()` pulls in wireSortable, and the context menu pulls in
 * wireContextMenu — four separate bundles, all of which register their
 * Alpine.data() factories from inside an `alpine:init` listener.
 *
 * Reached cold (a direct load) that is fine: the bundles are in the document
 * before Alpine starts. Reached through `wire:navigate` from {@see SpaPlainPreview}
 * they arrive into a document where Alpine started long ago, and `alpine:init`
 * is an event that fires exactly once per document.
 *
 * The gesture layer is opt-in, so this fixture asks for it explicitly —
 * see architecture/table.md § Gesture layer.
 */
class SpaTablePreview extends Component
{
    use WithSortable;
    use WithTable;

    public string $variant = 'table';

    public function mount(string $variant = 'table'): void
    {
        $this->variant = $variant;

        // Column order is persisted per user; without an authenticated id
        // reorderColumns() returns early and the next render undoes the drag.
        if (! auth()->check()) {
            $user = User::query()->first();

            if ($user !== null) {
                auth()->login($user);
            }
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(GestureRow::class)
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                // Editable, so the fill handle has somewhere to land.
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'New',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'archived' => 'Archived',
                    ]),
                TextColumn::make('amount')->label('Amount')->sortable(),
            ])
            ->searchable()
            ->selectable()
            ->stackedOnMobile()
            ->columnReorderable()
            ->gestures()
            ->fillHandle()
            ->recordActions([
                RecordAction::make(
                    Action::make('open')
                        ->label('Open')
                        ->icon('outline:eye')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Opened {$r->name}")
                        ->modalDescription('Enter, or a double-click, runs the primary record action.')
                        ->action(fn () => null)
                )->onDoubleClick(),
                RecordAction::make(
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('outline:document-duplicate')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Duplicate {$r->name}?")
                        ->action(fn () => null)
                )->onContextMenu(),
            ])
            ->bulkActions([
                BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->paginated(false);
    }

    public function render()
    {
        return view('livewire.previews.spa-table-preview');
    }
}
