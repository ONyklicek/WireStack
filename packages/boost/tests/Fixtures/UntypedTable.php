<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/**
 * Declares table() without a return type — still recognisable as a wire builder
 * from the parameter it accepts. (It cannot compose WithTable, whose own table()
 * is typed.)
 */
class UntypedTable
{
    public function table(Table $table)
    {
        return $table->model(Post::class)->columns([
            TextColumn::make('title'),
        ]);
    }
}
