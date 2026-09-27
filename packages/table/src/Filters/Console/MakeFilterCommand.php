<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Concerns\InteractsWithComponentScaffold;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\PublishedStubs;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a custom table filter and the Blade view of its control.
 *
 *   php artisan make:wire-filter Region
 *     →  app/Tables/Filters/RegionFilter.php
 *     →  resources/views/tables/filters/region.blade.php
 *
 * For a constraint that is reused across tables, or a control none of the
 * shipped filters draws. A one-off constraint is `Filter::make()->query()`.
 */
#[AsCommand(name: 'make:wire-filter')]
final class MakeFilterCommand extends Command
{
    use InteractsWithComponentScaffold;

    protected $signature = 'make:wire-filter
        {name : The filter, e.g. Region — "Filter" is added}
        {--f|force : Overwrite the class if it already exists; the view is never overwritten}';

    protected $description = 'Create a custom Wire table filter and the Blade view of its control';

    public function handle(): int
    {
        return $this->scaffold(new ComponentBlueprint(
            stubs: new PublishedStubs('wire-table', dirname(__DIR__, 3).'/stubs'),
            namespace: 'Tables\Filters',
            suffix: 'Filter',
            classStub: 'filter.stub',
            viewStub: 'filter-view.stub',
            viewFolder: 'tables.filters',
        ), "->filters([{class}::make('region')])");
    }
}
