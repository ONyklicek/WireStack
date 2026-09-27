<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Console\Support\StubWriter;
use NyonCode\WirePanels\Resources\Console\Concerns\InteractsWithPublishedStubs;
use NyonCode\WirePanels\Resources\Console\Concerns\ReportsNextSteps;
use NyonCode\WirePanels\Resources\Console\Support\PanelSetup;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a cluster — a section its members name (ADR 0039).
 *
 *   php artisan make:wire-cluster Settings        app/Clusters/Settings.php
 *
 * A cluster is a page, so it is registered as one and the command says so;
 * `make:wire-page Taxes --cluster=Settings` writes a member of it.
 */
#[AsCommand(name: 'make:wire-cluster')]
final class MakeClusterCommand extends Command
{
    use InteractsWithPublishedStubs;
    use ReportsNextSteps;

    protected $signature = 'make:wire-cluster
        {name : The section, e.g. Settings}
        {--f|force : Overwrite the file if it already exists}';

    protected $description = 'Create a Wire cluster — one menu entry and one URL prefix over several pages';

    public function handle(Filesystem $files): int
    {
        $class = Str::studly(class_basename(str_replace('/', '\\', (string) $this->argument('name'))));
        $namespace = $this->laravel->getNamespace().'Clusters';
        $fqn = $namespace.'\\'.$class;
        $slug = Str::kebab($class);

        $written = (new StubWriter($files))->write($this->publishedStubPath('cluster.stub'), app_path('Clusters/'.$class.'.php'), [
            'namespace' => $namespace,
            'class' => $class,
            'title' => Str::headline($class),
            'slug' => $slug,
        ], (bool) $this->option('force'));

        $written
            ? $this->components->info("Created [{$fqn}].")
            : $this->components->warn("[{$fqn}] already exists — left as it is. Pass --force to overwrite it.");

        $setup = new PanelSetup($files);

        $this->reportNextSteps(
            $setup,
            $slug,
            $setup->isRegistered($fqn, 'pages', (array) config('wire-panels.pages', [])),
            "Register it like a page: add \\{$fqn}::class to config('wire-panels.pages'), "
                ."or discover the folder — 'discover' => ['pages' => ['".str_replace('\\', '\\\\', $namespace)."' => app_path('Clusters')]] in config/wire-core.php.",
        );

        return self::SUCCESS;
    }
}
