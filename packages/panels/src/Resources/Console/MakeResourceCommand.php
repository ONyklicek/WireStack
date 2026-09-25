<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NyonCode\WirePanels\Resources\Console\Concerns\InteractsWithPublishedStubs;
use NyonCode\WirePanels\Resources\Console\Concerns\ReportsNextSteps;
use NyonCode\WirePanels\Resources\Console\Support\PanelSetup;
use NyonCode\WirePanels\Resources\Console\Support\ResourceScaffold;
use NyonCode\WirePanels\Resources\Console\Support\SchemaFields;
use NyonCode\WirePanels\Resources\Console\Support\StubWriter;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a resource and the pages that render it.
 *
 *   php artisan make:wire-resource Order                  resource + list, create, edit
 *   php artisan make:wire-resource Order --view           + a read-only view page
 *   php artisan make:wire-resource Tag --simple           resource + one ManagePage
 *   php artisan make:wire-resource Order --generate       fields and columns from the table
 *   php artisan make:wire-resource Order --soft-deletes   ManagesTrashedRecords + restore/force delete
 *   php artisan make:wire-resource Order --register       and add it to config/wire-core.php
 *
 * What it writes is worked out by {@see ResourceScaffold} and written by
 * {@see StubWriter}; this is the part that talks to the terminal. A file that
 * exists is left alone unless `--force` says otherwise, so running it twice
 * never loses an edit.
 */
#[AsCommand(name: 'make:wire-resource')]
final class MakeResourceCommand extends Command
{
    use InteractsWithPublishedStubs;
    use ReportsNextSteps;

    protected $signature = 'make:wire-resource
        {name : The entity, e.g. Order — "Resource" is added}
        {--model= : The model class, when it is not App\Models\{Name}}
        {--generate : Write fields, columns and entries from the model\'s table}
        {--view : Add a read-only view page with an infolist}
        {--simple : One ManagePage, create and edit as modals}
        {--soft-deletes : Manage the trash — the model must use SoftDeletes}
        {--register : Add the resource to config/wire-core.php}
        {--f|force : Overwrite files that already exist}';

    protected $description = 'Create a Wire resource and the pages that render it';

    public function handle(Filesystem $files, SchemaFields $schema): int
    {
        $model = $this->option('model');
        $scaffold = new ResourceScaffold(
            name: (string) $this->argument('name'),
            rootNamespace: $this->laravel->getNamespace(),
            model: is_string($model) && $model !== '' ? $model : null,
            view: (bool) $this->option('view') && ! $this->option('simple'),
            simple: (bool) $this->option('simple'),
            softDeletes: (bool) $this->option('soft-deletes'),
            lines: $this->generatedLines($schema, is_string($model) && $model !== '' ? ltrim($model, '\\') : null),
        );

        $writer = new StubWriter($files);
        $force = (bool) $this->option('force');

        $this->report($writer->write(
            $this->publishedStubPath('resource.stub'),
            $this->pathFor($scaffold->resourceFqn()),
            $scaffold->resourceReplacements(),
            $force,
        ), $scaffold->resourceFqn());

        foreach ($scaffold->pages() as [$class, $base, $body]) {
            $fqn = $scaffold->pagesNamespace.'\\'.$class;

            $this->report($writer->write(
                $this->publishedStubPath('resource-page.stub'),
                $this->pathFor($fqn),
                $scaffold->pageReplacements($class, $base, $body),
                $force,
            ), $fqn);
        }

        $setup = new PanelSetup($files);
        $fqn = $scaffold->resourceFqn();
        $namespace = $scaffold->resourceNamespace;

        $this->reportNextSteps(
            $setup,
            $setup->resourceKey($scaffold->model),
            $this->register($files, $fqn) || $setup->isRegistered($fqn, 'resources', (array) config('wire-core.resources', [])),
            "Register it: rerun with --register, add \\{$fqn}::class to config('wire-core.resources'), "
                ."or discover the whole folder — 'discover' => ['resources' => ['".str_replace('\\', '\\\\', $namespace)."' => app_path('Resources')]] in config/wire-core.php.",
        );

        return self::SUCCESS;
    }

    /**
     * The generated lines, when asked for — or none, and a word about why.
     *
     * @return array{fields: array<int, string>, columns: array<int, string>, entries: array<int, string>, imports: array<int, string>}
     */
    private function generatedLines(SchemaFields $schema, ?string $model): array
    {
        $none = ['fields' => [], 'columns' => [], 'entries' => [], 'imports' => []];

        if (! $this->option('generate')) {
            return $none;
        }

        $model ??= $this->laravel->getNamespace().'Models\\'.Str::studly((string) Str::of(class_basename((string) $this->argument('name')))->beforeLast('Resource'));

        if (! class_exists($model)) {
            $this->components->warn("[{$model}] does not exist yet, so there is no table to read: the resource is written without generated fields.");

            return $none;
        }

        $columns = $schema->columns($model);

        if ($columns === []) {
            $this->components->warn("No columns could be read for [{$model}] — has its migration run? The resource is written without generated fields.");

            return $none;
        }

        return $schema->lines($columns);
    }

    private function report(bool $written, string $class): void
    {
        $written
            ? $this->components->info("Created [{$class}].")
            : $this->components->warn("[{$class}] already exists — left as it is. Pass --force to overwrite it.");
    }

    /**
     * Add the resource to `config/wire-core.php` when `--register` asks, and say
     * whether it is now in that file.
     *
     * Only into a published config that has a `resources` list, and only once.
     * Anything else — no published file, a list built some other way — is the
     * application's to edit, and the next steps say how.
     */
    private function register(Filesystem $files, string $resource): bool
    {
        $config = config_path('wire-core.php');

        if (! $this->option('register') || ! $files->exists($config)) {
            return false;
        }

        $contents = $files->get($config);

        if (str_contains($contents, $resource.'::class')) {
            $this->components->info('Already registered in config/wire-core.php.');

            return true;
        }

        $updated = preg_replace("/('resources'\s*=>\s*\[)/", "$1\n        \\\\".$resource.'::class,', $contents, 1, $count);

        if ($count === 1 && is_string($updated)) {
            $files->put($config, $updated);
            $this->components->info('Registered in config/wire-core.php.');

            return true;
        }

        return false;
    }

    /** Where a class of the application's lives on disk. */
    private function pathFor(string $class): string
    {
        $relative = Str::after($class, $this->laravel->getNamespace());

        return app_path(str_replace('\\', '/', $relative).'.php');
    }
}
