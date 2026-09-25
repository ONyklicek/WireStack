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
 * Generates a page of the application's own and the view it draws.
 *
 *   php artisan make:wire-page TaskBoard                     app/Livewire/Pages/TaskBoard.php
 *   php artisan make:wire-page History --resource=Order      a record page of OrderResource
 *
 * A record page composes `BelongsToResource`, `ResolvesOneRecord` and
 * `LinksToRecordPages`, so it takes the record's key, draws the record's tabs
 * and — once the resource routes it under `{record}/…` — becomes one of them.
 * A page of its own is registered like a resource — the command says where; a
 * record page is routed from its resource's `pages()`, and the command prints
 * that line rather than editing the resource.
 */
#[AsCommand(name: 'make:wire-page')]
final class MakePageCommand extends Command
{
    use InteractsWithPublishedStubs;

    protected $signature = 'make:wire-page
        {name : The page, e.g. TaskBoard}
        {--resource= : Make it a page about one record of this resource — Order, or a class name}
        {--f|force : Overwrite files that already exist}';

    protected $description = 'Create a Wire page of your own, with its view';

    public function handle(Filesystem $files): int
    {
        $root = $this->laravel->getNamespace();
        $class = Str::studly(class_basename(str_replace('/', '\\', (string) $this->argument('name'))));
        $resource = $this->resourceClass($root);
        $folder = $resource === null ? 'Pages' : 'Resources\\'.Str::pluralStudly(Str::beforeLast(class_basename($resource), 'Resource'));
        $namespace = $root.'Livewire\\'.$folder;
        $kind = Str::kebab($class);
        $view = 'livewire.'.implode('.', array_map(fn (string $part): string => Str::kebab($part), explode('\\', $folder))).'.'.$kind;
        $title = Str::headline($class);

        $writer = new StubWriter($files);
        $force = (bool) $this->option('force');

        $written = $writer->write($this->publishedStubPath('page.stub'), app_path(str_replace('\\', '/', 'Livewire\\'.$folder.'\\'.$class).'.php'), [
            'namespace' => $namespace,
            'imports' => $this->imports($resource),
            'class' => $class,
            'title' => $title,
            'view' => $view,
            'kind' => $kind,
            'registration' => $resource === null
                ? "Registered in config('wire-panels.pages') or discovered, it is routed at /{$kind}\n * and listed in the menu — see the navigation statics on Page."
                : "Routed from the resource's `pages()`:\n *\n *   '{$kind}' => RoutePage::make({$class}::class)->uri('{record}/{$kind}'),",
            'implements' => $resource === null ? '' : ' implements ProvidesBreadcrumbs',
            'traits' => $resource === null ? '' : "    use BelongsToResource;\n    use LinksToRecordPages;\n    use ResolvesOneRecord;\n\n    protected static ?string \$resource = ".class_basename($resource)."::class;\n\n",
            'titleDeclaration' => $resource === null
                ? "\n    protected ?string \$title = '{$title}';\n"
                : "\n    public function getTitle(): ?string\n    {\n        return '{$title}';\n    }\n",
            'body' => $resource === null ? '' : "\n    /** The record's other pages, as tabs above this one. */\n    protected function getViewData(): array\n    {\n        return ['subNavigation' => \$this->subNavigation(\$this->nativeRecord())];\n    }\n",
        ], $force);

        $this->report($written, $namespace.'\\'.$class);

        $viewWritten = $writer->write(
            $this->publishedStubPath('page-view.stub'),
            resource_path('views/'.str_replace('.', '/', $view).'.blade.php'),
            ['class' => $class],
            $force,
        );

        $this->report($viewWritten, "view [{$view}]");

        $this->components->bulletList($resource === null
            ? [
                'Register it in config(\'wire-panels.pages\'): \\'.$namespace.'\\'.$class.'::class, — or name its folder in config(\'wire-core.discover.pages\').',
                'Route::wireResources() then routes it at /'.$kind.' and the menu lists it.',
            ]
            : ['Route it from the resource\'s pages(): '.var_export($kind, true).' => RoutePage::make(\\'.$namespace.'\\'.$class."::class)->uri('{record}/{$kind}'),"]);

        return self::SUCCESS;
    }

    /** The resource a record page is about: a class that exists, as given — or `App\Resources\{Name}Resource`. */
    private function resourceClass(string $root): ?string
    {
        $resource = $this->option('resource');

        if (! is_string($resource) || $resource === '') {
            return null;
        }

        if (str_contains($resource, '\\') || class_exists($resource)) {
            return ltrim($resource, '\\');
        }

        return $root.'Resources\\'.Str::studly(Str::beforeLast($resource, 'Resource')).'Resource';
    }

    private function imports(?string $resource): string
    {
        $classes = ['NyonCode\\WirePanels\\Pages\\Page'];

        if ($resource !== null) {
            array_push(
                $classes,
                $resource,
                'NyonCode\\WireCore\\Core\\Resources\\Contracts\\ProvidesBreadcrumbs',
                'NyonCode\\WirePanels\\Resources\\Concerns\\BelongsToResource',
                'NyonCode\\WirePanels\\Resources\\Concerns\\LinksToRecordPages',
                'NyonCode\\WirePanels\\Resources\\Concerns\\ResolvesOneRecord',
            );
        }

        sort($classes);

        return implode("\n", array_map(fn (string $class): string => 'use '.$class.';', $classes));
    }

    private function report(bool $written, string $what): void
    {
        $written
            ? $this->components->info("Created [{$what}].")
            : $this->components->warn("[{$what}] already exists — left as it is. Pass --force to overwrite it.");
    }
}
