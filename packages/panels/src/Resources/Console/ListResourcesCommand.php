<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * What the application has registered, read back: `describe-resource` for a
 * terminal.
 *
 *   php artisan wire:resources           every resource, its surfaces, its pages
 *   php artisan wire:resources orders    one resource's pages: route, URI, permission
 *
 * Read off the catalogue and the router, never off a list of its own — so what
 * it prints is what the menu, the palette and `Route::wireResources()` see, and
 * a page routed in two zones shows both of its routes.
 */
#[AsCommand(name: 'wire:resources')]
final class ListResourcesCommand extends Command
{
    protected $signature = 'wire:resources {key? : One resource, by its key}';

    protected $description = 'List the registered Wire resources, their surfaces, pages and routes';

    public function handle(Catalog $catalog, Router $router): int
    {
        $resources = $catalog->implementing(DescribesResource::class);
        $key = $this->argument('key');

        if (is_string($key) && $key !== '') {
            if (! isset($resources[$key])) {
                $this->components->error("No resource is registered under [{$key}].");

                return self::FAILURE;
            }

            $this->describe($key, $resources[$key], $router);

            return self::SUCCESS;
        }

        if ($resources === []) {
            $this->components->info('No resources are registered. Add one to config(\'wire-core.resources\'), or run make:wire-resource.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($resources as $resourceKey => $class) {
            $pages = $this->pages($class);

            $rows[] = [
                $resourceKey,
                $class,
                $class::modelClass() ?? '—',
                implode(', ', $this->surfaces($class)) ?: '—',
                $pages === [] ? '—' : implode(', ', array_keys($pages)),
                (string) count($this->routesOf($router, (string) $resourceKey)),
            ];
        }

        $this->table(['Key', 'Resource', 'Model', 'Surfaces', 'Pages', 'Routes'], $rows);

        return self::SUCCESS;
    }

    /**
     * One resource's pages, one row per route — a page in two zones is two rows.
     *
     * @param  class-string<DescribesResource>  $class
     */
    private function describe(string $key, string $class, Router $router): void
    {
        $this->components->twoColumnDetail('Resource', $class);
        $this->components->twoColumnDetail('Model', $class::modelClass() ?? '—');
        $this->components->twoColumnDetail('Surfaces', implode(', ', $this->surfaces($class)) ?: '—');

        $routes = $this->routesOf($router, $key);
        $rows = [];

        foreach ($this->pages($class) as $kind => $page) {
            $component = $page instanceof RoutePage ? $page->component : $page;
            $permission = $page instanceof RoutePage ? ($page->getPermission() ?? '—') : '—';
            $matched = array_filter($routes, fn (Route $route): bool => str_ends_with((string) $route->getName(), "wire.{$key}.{$kind}"));

            if ($matched === []) {
                $rows[] = [$kind, $component, 'not routed', '—', $permission];

                continue;
            }

            foreach ($matched as $route) {
                $rows[] = [$kind, $component, (string) $route->getName(), '/'.ltrim($route->uri(), '/'), $permission];
            }
        }

        $this->table(['Page', 'Component', 'Route', 'URI', 'Permission'], $rows);
    }

    /**
     * @param  class-string  $class
     * @return array<int, string>
     */
    private function surfaces(string $class): array
    {
        $surfaces = [
            'table' => ProvidesResourceTable::class,
            'form' => ProvidesResourceForm::class,
            'infolist' => ProvidesResourceInfolist::class,
            'relation managers' => ProvidesRelationManagers::class,
            'trash' => ManagesTrashedRecords::class,
        ];

        return array_keys(array_filter($surfaces, fn (string $contract): bool => is_subclass_of($class, $contract)));
    }

    /**
     * @param  class-string  $class
     * @return array<string, class-string|RoutePage>
     */
    private function pages(string $class): array
    {
        return is_subclass_of($class, ProvidesPages::class) ? $class::pages() : [];
    }

    /**
     * Every route registered for this key, in any zone.
     *
     * @return array<int, Route>
     */
    private function routesOf(Router $router, string $key): array
    {
        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (preg_match('/(^|\.)wire\.'.preg_quote($key, '/').'\.[^.]+$/', (string) $route->getName()) === 1) {
                $routes[] = $route;
            }
        }

        return $routes;
    }
}
