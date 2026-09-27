<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Concerns\InteractsWithComponentScaffold;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a custom table column and the Blade view its cells render.
 *
 *   php artisan make:wire-column Money
 *     →  app/Tables/Columns/MoneyColumn.php
 *     →  resources/views/tables/columns/money.blade.php
 *
 * For a cell no fluent method on the shipped columns can draw. A column that
 * only formats its value differently is `TextColumn::make()->formatStateUsing()`,
 * not a class.
 */
#[AsCommand(name: 'make:wire-column')]
final class MakeColumnCommand extends Command
{
    use InteractsWithComponentScaffold;

    protected $signature = 'make:wire-column
        {name : The column, e.g. Money — "Column" is added}
        {--f|force : Overwrite the class if it already exists; the view is never overwritten}';

    protected $description = 'Create a custom Wire table column and the Blade view its cells render';

    public function handle(): int
    {
        return $this->scaffold(new ComponentBlueprint(
            stubs: new PublishedStubs('wire-table', dirname(__DIR__, 3).'/stubs'),
            namespace: 'Tables\Columns',
            suffix: 'Column',
            classStub: 'column.stub',
            viewStub: 'column-view.stub',
            viewFolder: 'tables.columns',
        ), "->columns([{class}::make('price')])");
    }
}
