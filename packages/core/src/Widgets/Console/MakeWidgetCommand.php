<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\Widget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a widget class and the Blade view it renders.
 *
 *   php artisan make:wire-widget Revenue
 *     →  app/Widgets/RevenueWidget.php
 *     →  resources/views/widgets/revenue.blade.php
 *
 * ## Why two files
 *
 * Because one of them is useless. A {@see Widget} subclass declares
 * `viewName()`, so a generator that wrote only the class would hand back
 * something that throws `View [widgets.revenue] not found` the first time it is
 * put on a dashboard — and the reader would have to work out from the exception
 * which path the name it just read maps to. The pair is the extension point;
 * generating half of it is generating a puzzle.
 *
 * An existing view is never overwritten. Re-running the command after editing
 * the markup is a thing people do — usually to get the class back — and
 * silently replacing a view somebody wrote is not a trade a generator gets to
 * make. The class itself is guarded by `GeneratorCommand`'s own `--force`.
 *
 * ## Why a stub and not a shipped class
 *
 * Same reason {@see MakeDashboardCommand} gives: a widget counts the
 * application's own rows, so the package can own the *shape* and not the thing.
 * Both stubs are publishable with `vendor:publish --tag=wire-core::stubs`, after
 * which the published copy wins — see {@see resolveStubPath()} for where that
 * is, and for how long it was somewhere the generator never looked.
 */
#[AsCommand(name: 'make:wire-widget')]
class MakeWidgetCommand extends GeneratorCommand
{
    protected $name = 'make:wire-widget';

    protected $description = 'Create a new Wire widget class and its Blade view';

    protected $type = 'Widget';

    protected function getStub(): string
    {
        return $this->resolveStubPath('widget.stub');
    }

    /**
     * A published stub wins, so an application can change what this produces.
     *
     * `stubs/wire-core/` first, because that is where
     * `vendor:publish --tag=wire-core::stubs` actually puts them — the toolkit
     * namespaces published stubs by package so two packages shipping a
     * `dashboard.stub` cannot overwrite each other. This looked in `stubs/`
     * alone for as long as it existed, so publishing the stubs and editing them
     * changed nothing at all: the generator kept reading the package's copy and
     * said nothing about it.
     *
     * `stubs/` is still consulted second. That is Laravel's own `stub:publish`
     * convention and where somebody who put the file there by hand would expect
     * it to be read from.
     */
    protected function resolveStubPath(string $stub): string
    {
        foreach ([base_path('stubs/wire-core/'.$stub), base_path('stubs/'.$stub)] as $published) {
            if (file_exists($published)) {
                return $published;
            }
        }

        return __DIR__.'/../../../stubs/'.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Widgets';
    }

    /**
     * "Revenue" and "RevenueWidget" both produce RevenueWidget, so the class
     * reads as what it is wherever it turns up in a dashboard declaration.
     *
     * @param  string  $name
     */
    protected function qualifyClass($name): string
    {
        $name = str_ends_with($name, 'Widget') ? $name : $name.'Widget';

        return parent::qualifyClass($name);
    }

    /**
     * Write the class, then the view it names — and say where both went.
     *
     * @return bool|null
     */
    public function handle()
    {
        if (parent::handle() === false) {
            return false;
        }

        $this->writeView();

        return null;
    }

    /**
     * Substitute the view name into the class, on top of everything
     * `GeneratorCommand` already substitutes.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        return str_replace('{{ view }}', $this->viewName(), parent::buildClass($name));
    }

    /**
     * The dot path the generated class renders, and the file that answers it.
     *
     * Derived from the class name with the `Widget` suffix dropped, for the
     * reason {@see Dashboard::key()} gives for
     * deriving its own: a name written twice in two places drifts the moment
     * somebody renames one of them.
     */
    private function viewName(): string
    {
        $base = Str::of(class_basename($this->qualifyClass($this->getNameInput())))
            ->beforeLast('Widget')
            ->value();

        return 'widgets.'.Str::kebab($base ?: 'widget');
    }

    /**
     * Overwriting the class is opt-in; overwriting the view is not offered at
     * all. `--force` is what makes re-running the generator after editing the
     * class useful, and {@see writeView()} is what keeps the markup safe from it.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the widget class if it already exists'],
        ];
    }

    private function writeView(): void
    {
        $path = resource_path('views/'.str_replace('.', '/', $this->viewName()).'.blade.php');

        if ($this->files->exists($path)) {
            $this->components->warn('View already exists, left alone: '.$path);

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->files->get($this->resolveStubPath('widget-view.stub')));

        $this->components->info('View created: '.$path);
    }
}
