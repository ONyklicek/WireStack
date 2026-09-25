<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NyonCode\WirePanels\Resources\Console\Concerns\InteractsWithPublishedStubs;
use NyonCode\WirePanels\Resources\Console\Support\StubWriter;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a relation manager for one of a resource's relationships.
 *
 *   php artisan make:wire-relation-manager Order items
 *     →  app/Livewire/Resources/Orders/ItemsRelationManager.php
 *
 * It prints the line that embeds it rather than editing the resource: the
 * resource is the application's file, and `relationManagers()` is a list the
 * application orders.
 */
#[AsCommand(name: 'make:wire-relation-manager')]
final class MakeRelationManagerCommand extends Command
{
    use InteractsWithPublishedStubs;

    protected $signature = 'make:wire-relation-manager
        {resource : The owning resource, e.g. Order}
        {relationship : The relationship method on its model, e.g. items}
        {--f|force : Overwrite the file if it already exists}';

    protected $description = 'Create a relation manager for a Wire resource';

    public function handle(Filesystem $files): int
    {
        $owner = Str::studly(Str::beforeLast(class_basename(str_replace('/', '\\', (string) $this->argument('resource'))), 'Resource'));
        $relationship = Str::camel((string) $this->argument('relationship'));
        $class = Str::studly($relationship).'RelationManager';
        $folder = 'Livewire\\Resources\\'.Str::pluralStudly($owner);
        $namespace = $this->laravel->getNamespace().$folder;

        $written = (new StubWriter($files))->write(
            $this->publishedStubPath('relation-manager.stub'),
            app_path(str_replace('\\', '/', $folder.'\\'.$class).'.php'),
            [
                'namespace' => $namespace,
                'class' => $class,
                'relationship' => $relationship,
                'owner' => Str::headline($owner),
                'title' => Str::headline($relationship),
            ],
            (bool) $this->option('force'),
        );

        $written
            ? $this->components->info("Created [{$namespace}\\{$class}].")
            : $this->components->warn("[{$namespace}\\{$class}] already exists — left as it is. Pass --force to overwrite it.");

        $this->components->bulletList([
            "Embed it: have {$owner}Resource implement ProvidesRelationManagers and return \\{$namespace}\\{$class}::class from relationManagers().",
        ]);

        return self::SUCCESS;
    }
}
