<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Concerns\InteractsWithComponentScaffold;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a configured action — a class to reuse instead of repeating the
 * same `Action::make()` chain on every surface.
 *
 *   php artisan make:wire-action Archive          app/Wire/Actions/ArchiveAction.php
 *   php artisan make:wire-action Archive --bulk   app/Wire/Actions/ArchiveBulkAction.php
 *
 * No view: an action is drawn by the surface that hosts it. And not
 * `App\Actions`, which is where an application keeps its business actions
 * (Fortify's included) — a button that calls one is not one.
 */
#[AsCommand(name: 'make:wire-action')]
final class MakeActionCommand extends Command
{
    use InteractsWithComponentScaffold;

    protected $signature = 'make:wire-action
        {name : The action, e.g. Archive — "Action" is added}
        {--bulk : A bulk action, run over the table\'s selected records}
        {--f|force : Overwrite the class if it already exists}';

    protected $description = 'Create a reusable Wire action class';

    public function handle(): int
    {
        $bulk = (bool) $this->option('bulk');

        return $this->scaffold(new ComponentBlueprint(
            stubs: new PublishedStubs('wire-core', dirname(__DIR__, 3).'/stubs'),
            namespace: 'Wire\Actions',
            suffix: $bulk ? 'BulkAction' : 'Action',
            classStub: $bulk ? 'bulk-action.stub' : 'action.stub',
        ), $bulk ? '->bulkActions([{class}::make()])' : '->actions([{class}::make()])');
    }
}
