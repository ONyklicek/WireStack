<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generates a dashboard class.
 *
 *   php artisan make:wire-dashboard Sales   →   app/Dashboards/SalesDashboard.php
 *
 * A generator rather than a class shipped in the package, because a dashboard is
 * application code: it counts the application's own rows. What the package can
 * usefully own is the *shape*, and that is the stub — publishable with
 * `vendor:publish --tag=wire-core::stubs`, after which
 * `base_path('stubs/dashboard.stub')` wins over the package's copy. That is
 * Laravel's own convention for `stub:publish`, and it is why the stub is not a
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

    protected function getStub(): string
    {
        return $this->resolveStubPath('/../../../stubs/dashboard.stub');
    }

    /** A published stub wins, so an application can change what this produces. */
    protected function resolveStubPath(string $stub): string
    {
        $published = base_path('stubs/dashboard.stub');

        return file_exists($published) ? $published : __DIR__.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Dashboards';
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
