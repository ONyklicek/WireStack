<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a dashboard class and the page that mounts it.
 *
 *   php artisan make:wire-dashboard Sales
 *     →  app/Dashboards/SalesDashboard.php
 *     →  app/Livewire/Dashboards/ShowSales.php   (with wire-panels installed)
 *
 * ## Why two files
 *
 * Because one of them cannot be opened. A dashboard is a declaration; what
 * routes it is a page, and a generated dashboard that declared none appeared in
 * the menu as **an entry with no link** and answered 404 at every address —
 * still true after registering it, which is what made it look like a bug in the
 * framework rather than half a generator.
 *
 * The page is wire-panels' class, so wire-panels generates it: this calls
 * `make:wire-dashboard-page` by name if that command is registered, and says
 * what to install when it is not. The dashboard's own `pages()` is written
 * either way — nothing reads it until something routes, so it is inert in an
 * application that has no page package yet, and correct the moment one arrives.
 *
 * A generator rather than a class shipped in the package, because a dashboard is
 * application code: it counts the application's own rows. What the package can
 * usefully own is the *shape*, and that is the stub — publishable with
 * `vendor:publish --tag=wire-core::stubs`, after which the published copy wins
 * over the package's ({@see resolveStubPath()} for where it is looked for). It
 * is why the stub is not a
 * `.php` file inside the package: a template referencing classes that exist only
 * after installation should not be loaded by the test suite, analysed by PHPStan
 * or counted by coverage.
 */
#[AsCommand(name: 'make:wire-dashboard')]
class MakeDashboardCommand extends GeneratorCommand
{
    protected $name = 'make:wire-dashboard';

    protected $description = 'Create a new Wire dashboard class';

    protected $type = 'Dashboard';

    /** wire-panels' generator for the page half, asked for by name. */
    private const PAGE_COMMAND = 'make:wire-dashboard-page';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/../../../stubs/dashboard.stub');
    }

    /**
     * A published stub wins, so an application can change what this produces.
     *
     * `stubs/wire-core/` first: that is where
     * `vendor:publish --tag=wire-core::stubs` puts them, the toolkit namespacing
     * published stubs by package so two packages shipping a `dashboard.stub`
     * cannot overwrite each other. Looking only in `stubs/` — which this did —
     * meant publishing the stub and editing it changed nothing, silently.
     *
     * `stubs/` stays as a second place to look: Laravel's own `stub:publish`
     * convention, and where a file put there by hand belongs.
     */
    protected function resolveStubPath(string $stub): string
    {
        foreach ([base_path('stubs/wire-core/dashboard.stub'), base_path('stubs/dashboard.stub')] as $published) {
            if (file_exists($published)) {
                return $published;
            }
        }

        return __DIR__.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Dashboards';
    }

    /**
     * Write the dashboard, then ask wire-panels for the page it names.
     *
     * @return bool|null
     */
    public function handle()
    {
        if (parent::handle() === false) {
            return false;
        }

        if ($this->option('no-page')) {
            return null;
        }

        // By name, never by class: wire-panels sits *above* core in the package
        // graph, so this is allowed to know that the command may exist and
        // nothing more. An application without the page package gets the line
        // that installs it rather than a class it cannot autoload.
        if (! $this->getApplication()?->has(self::PAGE_COMMAND)) {
            $this->components->warn(
                'No page was generated: '.self::PAGE_COMMAND.' comes with nyoncode/wire-panels. '.
                'Until it is installed this dashboard is a declaration nothing routes.'
            );

            return null;
        }

        $this->call(self::PAGE_COMMAND, ['name' => $this->getNameInput()]);

        return null;
    }

    /**
     * Substitute the page this dashboard names, on top of everything
     * `GeneratorCommand` already substitutes.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        return str_replace(
            ['{{ pageFqn }}', '{{ page }}'],
            [$this->pageClass(), class_basename($this->pageClass())],
            parent::buildClass($name),
        );
    }

    /**
     * The page class generated beside this dashboard.
     *
     * Derived here rather than passed between the two commands, and derived the
     * same way on both sides: a name written twice drifts the moment somebody
     * renames one of them. `Sales` and `SalesDashboard` both give `ShowSales`,
     * for the reason {@see qualifyClass()} gives.
     */
    private function pageClass(): string
    {
        $base = Str::of(class_basename($this->qualifyClass($this->getNameInput())))
            ->beforeLast('Dashboard')
            ->value();

        return trim($this->rootNamespace(), '\\').'\\Livewire\\Dashboards\\Show'.($base ?: 'Dashboard');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the dashboard class if it already exists'],
            ['no-page', null, InputOption::VALUE_NONE, 'Write the dashboard only, without the page that mounts it'],
        ];
    }

    /**
     * "Sales" and "SalesDashboard" both produce SalesDashboard — the suffix is
     * what `Dashboard::key()` strips, so a class without it would key itself
     * after a name that reads like a page rather than a dashboard.
     */
    protected function qualifyClass($name): string
    {
        $name = str_ends_with($name, 'Dashboard') ? $name : $name.'Dashboard';

        return parent::qualifyClass($name);
    }
}
