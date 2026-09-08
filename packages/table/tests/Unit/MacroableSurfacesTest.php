<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\Filter;

/*
 * The counterpart of the forms file of the same name: `Table` was macroable and
 * the pieces it is made of were not, so "add a word to every column" had no
 * answer short of a subclass per column type.
 */

it('lets an application add vocabulary to a column', function () {
    TextColumn::macro('internalOnly', function (): TextColumn {
        /** @var TextColumn $this */
        return $this->label('Total (CZK)');
    });

    expect(TextColumn::make('total')->internalOnly()->getLabel())->toBe('Total (CZK)');
});

it('lets an application add vocabulary to a filter', function () {
    Filter::macro('internal', function (): Filter {
        /** @var Filter $this */
        return $this->label('Internal only');
    });

    expect(Filter::make('flag')->internal()->getLabel())->toBe('Internal only');
});
