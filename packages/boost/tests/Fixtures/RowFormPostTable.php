<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Illuminate\Support\Collection;
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Action forms that are entitled to their context: a row action's form takes
 * the row it was clicked on, a bulk action's the selected rows. Both are typed,
 * so a form built against nothing throws.
 */
class RowFormPostTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Post::class)
            ->columns([])
            ->actions([
                Action::make('rename')
                    ->form(fn (Post $record): array => [TextInput::make('title')->default($record->title)])
                    ->action(fn () => null),
            ])
            ->headerActions([
                // A table's header action has no row: its form gets nothing.
                Action::make('import')
                    ->form(fn ($record): array => $record === null
                        ? [TextInput::make('file')]
                        : throw new \RuntimeException('a header action was handed a record'))
                    ->action(fn () => null),
            ])
            ->bulkActions([
                BulkAction::make('retitle')
                    ->form(fn (Collection $records): array => $records->isEmpty()
                        ? throw new \RuntimeException('no rows selected')
                        : [TextInput::make('prefix')])
                    ->action(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
