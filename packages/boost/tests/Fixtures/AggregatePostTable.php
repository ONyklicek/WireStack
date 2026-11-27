<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Rollups, in both spellings: the named alias with `->counts()`, and the dotted
 * path. Neither name is an attribute of the model; the relation behind it is
 * what has to exist — and `nope` is not one.
 */
class AggregatePostTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Post::class)
            ->columns([
                TextColumn::make('tags_count')->counts('tags'),
                TextColumn::make('author_count')->counts('author'),
                TextColumn::make('author.count'),
                TextColumn::make('nope_count')->counts('nope'),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
