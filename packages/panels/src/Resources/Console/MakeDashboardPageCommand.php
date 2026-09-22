<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use NyonCode\WirePanels\Resources\Pages\DashboardPage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates the page that mounts a dashboard.
 *
 *   php artisan make:wire-dashboard-page Sales   →   app/Livewire/Dashboards/ShowSales.php
 *
 * Usually not run by hand: `make:wire-dashboard` calls it, so the pair arrives
 * together. It exists as a command of its own because the halves can be wanted
 * separately — a second page over the same dashboard, or the page for a
 * dashboard written before wire-panels was installed.
 *
 * **Here rather than in wire-core, which owns the dashboard.** The class it
 * extends is {@see DashboardPage}, which is this package's; core may not name
 * it, and a generator whose template references a class from a package above it
 * is that dependency written in a string. So the two commands meet by *name*:
 * core calls `make:wire-dashboard-page` if the application has it, and says
 * what to install when it does not.
 *
 * The template is publishable with `vendor:publish --tag=wire-panels::stubs`,
 * and a published copy wins ({@see resolveStubPath()}).
 */
#[AsCommand(name: 'make:wire-dashboard-page')]
class MakeDashboardPageCommand extends GeneratorCommand
{
    protected $name = 'make:wire-dashboard-page';

    protected $description = 'Create the page that renders a Wire dashboard';

    protected $type = 'Dashboard page';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/../../../stubs/dashboard-page.stub');
    }

    /**
     * A published stub wins, so an application can change what this produces.
     *
     * `stubs/wire-panels/` first — where `vendor:publish --tag=wire-panels::stubs`
     * puts it, the toolkit namespacing published stubs by package so two
     * packages shipping the same file name cannot overwrite each other — then
     * `stubs/`, Laravel's own `stub:publish` convention.
     */
    protected function resolveStubPath(string $stub): string
    {
        foreach ([base_path('stubs/wire-panels/dashboard-page.stub'), base_path('stubs/dashboard-page.stub')] as $published) {
            if (file_exists($published)) {
                return $published;
            }
        }

        return __DIR__.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Livewire\Dashboards';
    }

    /**
     * The name argument is the *dashboard's*, not this class's.
     *
     * `Sales`, `SalesDashboard` and `ShowSales` all produce `ShowSales` over
     * `SalesDashboard`: the caller is naming the thing they are building a page
     * for, and making them spell the page's own name would be asking them to
     * repeat a convention this already knows.
     *
     * @param  string  $name
     */
    protected function qualifyClass($name): string
    {
        // The guard is not defensive: `GeneratorCommand::qualifyClass()` calls
        // **this** method again with the name it has qualified, and an override
        // that re-derives from the basename hands back an unqualified name every
        // time — so the two recurse until the stack runs out. PHP does not throw
        // there; it segfaults, with no output and exit 139, which is what this
        // did before the guard.
        if (Str::startsWith($name, $this->rootNamespace())) {
            return parent::qualifyClass($name);
        }

        return parent::qualifyClass('Show'.$this->dashboardBase($name));
    }

    /**
     * Substitute the dashboard this page mounts.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $dashboard = $this->dashboardClass();

        return str_replace(
            ['{{ dashboardFqn }}', '{{ dashboard }}'],
            [$dashboard, class_basename($dashboard)],
            parent::buildClass($name),
        );
    }

    /**
     * The dashboard class this page is for.
     *
     * `app/Dashboards/{Name}Dashboard`, which is where `make:wire-dashboard`
     * writes one and what the configuration example shows. An application that
     * keeps dashboards elsewhere edits the one line in the generated page.
     */
    private function dashboardClass(): string
    {
        return trim($this->rootNamespace(), '\\').'\\Dashboards\\'.$this->dashboardBase($this->getNameInput()).'Dashboard';
    }

    /**
     * The dashboard's name with every suffix this accepts stripped: `Sales`
     * from `Sales`, `SalesDashboard` and `ShowSales` alike.
     *
     * The fallback covers a name that is nothing but a suffix, where stripping
     * leaves nothing to call the class.
     */
    private function dashboardBase(string $name): string
    {
        $base = Str::of(class_basename(str_replace('/', '\\', $name)))
            ->after('Show')
            ->beforeLast('Dashboard')
            ->value();

        return $base !== '' ? $base : 'Dashboard';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the page if it already exists'],
        ];
    }
}
