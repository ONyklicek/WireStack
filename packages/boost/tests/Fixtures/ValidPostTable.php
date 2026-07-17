<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Every column here is legitimate, each resolving by a different route: a real
 * column, a cast, an appended accessor, a non-appended Attribute accessor, a
 * classic accessor, a relation path, and a computed column whose name is not an
 * attribute at all.
 */
class ValidPostTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Post::class)
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('published'),
                TextColumn::make('excerpt'),
                TextColumn::make('summary'),
                TextColumn::make('headline'),
                TextColumn::make('author.name'),
                TextColumn::make('computed')->state(fn (): string => 'computed'),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
