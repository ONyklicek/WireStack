<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Concerns\InteractsWithComponentScaffold;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a custom infolist entry and the Blade view it renders.
 *
 *   php artisan make:wire-entry Money
 *     →  app/Infolists/Components/MoneyEntry.php
 *     →  resources/views/infolists/entries/money.blade.php
 */
#[AsCommand(name: 'make:wire-entry')]
final class MakeEntryCommand extends Command
{
    use InteractsWithComponentScaffold;

    protected $signature = 'make:wire-entry
        {name : The entry, e.g. Money — "Entry" is added}
        {--f|force : Overwrite the class if it already exists; the view is never overwritten}';

    protected $description = 'Create a custom Wire infolist entry and the Blade view it renders';

    public function handle(): int
    {
        return $this->scaffold(new ComponentBlueprint(
            stubs: new PublishedStubs('wire-core', dirname(__DIR__, 3).'/stubs'),
            namespace: 'Infolists\Components',
            suffix: 'Entry',
            classStub: 'entry.stub',
            viewStub: 'entry-view.stub',
            viewFolder: 'infolists.entries',
        ), "->schema([{class}::make('price')])");
    }
}
