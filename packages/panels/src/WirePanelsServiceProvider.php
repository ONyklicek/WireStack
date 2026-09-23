<?php

declare(strict_types=1);

namespace NyonCode\WirePanels;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\RegistersPageRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Install\RegisterResourceRoutes;
use NyonCode\WirePanels\Resources\Console\MakeDashboardPageCommand;
use NyonCode\WirePanels\Routing\ConfiguredRoutes;
use NyonCode\WirePanels\Routing\RegisteredPageUrls;
use NyonCode\WirePanels\Routing\ResourceRoutes;
use NyonCode\WirePanels\Routing\RouteAccess;
use NyonCode\WirePanels\Routing\RouteClaims;

/**
 * The application owner layer.
 *
 * Sits above every component package — it requires wire-table, wire-forms and
 * wire-core, and nothing requires it. That direction is the whole point: a
 * resource composes the primitives, so the package that owns resources has to be
 * the one that may name all of them, and none of them may name it back.
 *
 * What lives here is only the part that needs a component type. The identity
 * half of a resource (`DescribesResource`, `DescribesRecords`,
 * `ResourceRegistry`) is in wire-core and the per-surface contracts live with
 * the types they name — `ProvidesResourceForm` in wire-forms,
 * `ProvidesResourceInfolist` beside Infolists — so an application that has a
 * form-only resource installs wire-forms and never sees a table package.
 */
class WirePanelsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WirePanels')
            ->hasShortName('wire-panels')
            ->registeredPackage(function (): void {
                $this->registerRouteMacros();

                // What the macros registered, so no page of theirs takes a
                // route's path or loses its own to one — see RouteClaims.
                $this->app->singleton(RouteClaims::class);

                // Core asks "where does this key live?" and answers null until
                // something owns routing. This package does, so it answers.
                $this->app->bind(ResolvesPageUrls::class, RegisteredPageUrls::class);

                // And who may open them: the `can:` middleware this package
                // writes onto those routes, asked of the Gate.
                $this->app->bind(AuthorizesUrls::class, RouteAccess::class);

                // And core calls this once the registries are full, which is the
                // only moment auto-registration can read a complete catalogue.
                // Bound during register() so it is there before core boots,
                // rather than depending on which provider the manifest lists
                // first — see ConfiguredRoutes.
                $this->app->bind(RegistersPageRoutes::class, ConfiguredRoutes::class);

                // The last thing between a complete installation and a 404 on every
                // screen. The prefix and the middleware are the application's to
                // choose, so the step asks rather than guessing.
                SetupRegistry::instance()->register(RegisterResourceRoutes::class);
            })
            ->bootedPackage(function (): void {
                // Once every route is loaded: a route file registers its
                // routes from a provider's booted callback, and the one that
                // replaces a page may come after the page.
                $this->app->booted(fn () => $this->app->make(RouteClaims::class)->verify());
            })
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            // The page half of a dashboard. It lives here because the class it
            // extends does: core owns the declaration and may not name a class
            // from a package above it, so `make:wire-dashboard` asks for this
            // one by name — see MakeDashboardPageCommand.
            ->hasCommand(MakeDashboardPageCommand::class)
            ->hasStubs(['../stubs/dashboard-page.stub'])
            ->hasAbout();
    }

    /**
     * `Route::wireResources()` and friends, for an application's own route file.
     *
     * Macros rather than a facade of our own, for the reason wire-sortable's
     * `Table::macro()` calls give: the thing being extended is Laravel's, the
     * call reads beside `Route::resource()`, and the surrounding
     * `prefix`/`middleware`/`domain` group applies for free — which is what
     * keeps routing the application's while removing only the repetition.
     *
     * The bodies stay in {@see ResourceRoutes}; these are endpoints.
     */
    protected function registerRouteMacros(): void
    {
        Route::macro('wireResources', function (array $only = [], array $except = []): array {
            // Both paths at once registers every page twice under one route
            // name. Refused rather than resolved — see the exception for why.
            if (app()->bound(ConfiguredRoutes::MARKER)) {
                throw ResourceRoutingException::alreadyRegisteredFromConfig();
            }

            return ResourceRoutes::all($only, $except);
        });

        Route::macro('wireResource', function (string $resource, array $pages = []): array {
            return ResourceRoutes::for($resource, $pages);
        });

        Route::macro('wireZoneEntry', function (string $uri = '/'): RouteDefinition {
            return ResourceRoutes::zoneEntry($uri);
        });
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Resources' => (string) count(app(ResourceRegistry::class)->all()),
        ];
    }
}
