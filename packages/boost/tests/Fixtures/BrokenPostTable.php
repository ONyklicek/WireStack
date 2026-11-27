<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Every fault here is silent at runtime: the table renders, no exception is
 * thrown, and a "it renders" test passes. That is precisely why a tool has to
 * look for them.
 */
class BrokenPostTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Post::class)
            ->columns([
                // Typo: renders an empty cell.
                TextColumn::make('titel'),
                // Not an attribute of Post at all.
                TextColumn::make('nonexistent_thing'),
                // Typo'd color: Color::resolve() greys it out silently.
                TextColumn::make('title')->color('bleu'),
                // Unregistered icon.
                TextColumn::make('published')->icon('heroicon-nope'),
            ])
            ->actions([
                // The fault is inside the modal, which only a click would build.
                Action::make('flag')
                    ->form([
                        TextInput::make('reason'),
                        Alert::make('policy')->color('orang')->content('Flags are reviewed.'),
                    ])
                    ->action(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
