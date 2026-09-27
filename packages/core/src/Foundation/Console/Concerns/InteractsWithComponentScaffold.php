<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Console\Concerns;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Console\Actions\ScaffoldComponent;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;

/**
 * The terminal half of a custom-component generator: run the scaffold, then
 * say what was written, what was left alone, and how the thing is used.
 *
 * The writing is {@see ScaffoldComponent}'s; this only reports it, so every
 * `make:wire-{component}` command reads the same.
 *
 * @mixin Command
 */
trait InteractsWithComponentScaffold
{
    /**
     * @param  string  $usage  One line of code that puts the component to work, with `{class}` for its short name.
     */
    protected function scaffold(ComponentBlueprint $blueprint, string $usage): int
    {
        $result = $this->laravel->make(ScaffoldComponent::class)->execute(
            $blueprint,
            (string) $this->argument('name'),
            (bool) $this->option('force'),
        );

        $result->classWritten
            ? $this->components->info("Created [{$result->class}].")
            : $this->components->warn("[{$result->class}] already exists — left as it is. Pass --force to overwrite it.");

        if ($result->viewPath !== null) {
            $result->viewWritten
                ? $this->components->info("View created: {$result->viewPath}")
                : $this->components->warn("View already exists, left alone: {$result->viewPath}");
        }

        $this->components->twoColumnDetail('Use it', str_replace('{class}', class_basename($result->class), $usage));

        return Command::SUCCESS;
    }
}
