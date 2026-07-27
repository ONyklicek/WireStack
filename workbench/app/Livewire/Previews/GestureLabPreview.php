<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;
use Workbench\App\Models\GestureRow;
use Workbench\App\Models\User;

/**
 * The gesture lab: every capability the selection-gesture work added, switched
 * on at once, on one table.
 *
 * The per-feature previews each isolate one thing on purpose — `selection-only`
 * proves grid semantics come from `selectable()` rather than from record
 * actions, `sortable-columns` proves a header drag does not disturb the fixed
 * cells. This one does the opposite: it is the combination, which is where the
 * features can interfere with each other. Selection gestures next to record
 * actions next to column reordering next to a context menu, with the checkbox
 * column that the sweep drags down and that column reordering must leave alone.
 *
 * The blade view pairs it with a live read-out of the state the gestures drive
 * (mode, selection, anchor, active row, the announcement a screen reader would
 * hear), so a person can drive the table by hand and watch what each gesture
 * actually does — and so `verify-gesture-lab.mjs` can assert on the same
 * values without reaching into Alpine internals.
 *
 * Variants:
 *   lab   — 40 rows on one page (the document scrolls; PageUp/PageDown, sweep
 *           with autoscroll, ranges across a long list)
 *   paged — 20 a page, which is what "select all matching" needs to mean
 *           anything: a page that is smaller than the filtered set
 */
class GestureLabPreview extends Component
{
    use WithSortable;
    use WithTable;

    public string $variant = 'lab';

    public function mount(string $variant = 'lab'): void
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
        $table
            // The lab is the gesture layer, so it asks for all of it. Everything
            // below is what a table gets on top of that opt-in.
            ->gestures()
            ->model(GestureRow::class)
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'new' => 'info',
                        'active' => 'success',
                        'paused' => 'warning',
                        'archived' => 'gray',
                    ])
                    ->filterable(),
                TextColumn::make('amount')->label('Amount')->sortable(),
            ])
            ->searchable()
            ->selectable()
            ->stackedOnMobile()
            ->columnReorderable()
            ->bulkActions([
                BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                BulkAction::make('archive')->label('Archive')->icon('outline:archive-box')->color('warning'),
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');

        // A table whose ONLY record action is a single click opening a modal —
        // no double-click binding, so nothing defers the open. Reported as the
        // shape where the keyboard stops working after the modal closes.
        if ($this->variant === 'click-only') {
            return $table->recordActions([
                RecordAction::make(
                    Action::make('inspect')
                        ->label('Inspect')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Inspecting {$r->name}")
                        ->action(fn () => null)
                )->onClick(),
            ]);
        }

        $table
            // One of each trigger, so Enter, Shift+Enter, the context menu and
            // an onKey() binding all have something to run. Delete doubles as
            // Backspace in the client matcher.
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
                    Action::make('peek')
                        ->label('Peek')
                        ->icon('outline:magnifying-glass')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Peeked {$r->name}")
                        ->modalDescription('Shift+Enter runs the secondary binding.')
                        ->action(fn () => null)
                )->onClick(),
                RecordAction::make(
                    Action::make('archive-one')
                        ->label('Archive')
                        ->icon('outline:archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Archive {$r->name}?")
                        ->modalDescription('Bound with onKey(Delete) — Backspace answers to it too.')
                        ->action(fn () => null)
                )->onKey('Delete'),
                RecordAction::make(
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('outline:document-duplicate')
                        ->requiresConfirmation()
                        ->modalHeading(fn (GestureRow $r) => "Duplicate {$r->name}?")
                        ->action(fn () => null)
                )->onContextMenu(),
            ])
            ->defaultSort('name');

        $this->variant === 'paged'
            ? $table->paginated()->perPage(20)
            : $table->paginated(false);

        // The same table with the gesture layer switched off — what a public
        // page asks for. The declared record actions stay (a double-click still
        // opens, right-click is gone with the menu), the selection keeps its
        // checkboxes, and the keyboard, the ranges, the sweep and `?` are not
        // there at all. `verify-gestures-off.mjs` drives this one.
        if ($this->variant === 'plain') {
            $table->gestures(false);
        }

        return $table;
    }

    public function render()
    {
        return view('livewire.previews.gesture-lab');
    }
}
