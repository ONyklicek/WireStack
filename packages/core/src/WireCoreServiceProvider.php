<?php

declare(strict_types=1);

namespace NyonCode\WireCore;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\ComponentHookRegistry;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\WireCore\Actions\View\BulkButtonComponent;
use NyonCode\WireCore\Actions\View\ButtonComponent;
use NyonCode\WireCore\Actions\View\GroupComponent;
use NyonCode\WireCore\Actions\View\ModalHostComponent;
use NyonCode\WireCore\Audit\AuditEventSubscriber;
use NyonCode\WireCore\Audit\Console\PruneAuditEntriesCommand;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionRegistry;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Assets\Bundle;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireCore\Foundation\Support\IslandViewScope;
use NyonCode\WireCore\Foundation\Support\PartialRenderHook;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireCore\Foundation\View\CellSync;
use NyonCode\WireCore\Foundation\View\CopyButton;
use NyonCode\WireCore\Foundation\View\FloatingAssets;
use NyonCode\WireCore\Foundation\View\Primitives;
use NyonCode\WireCore\Modals\View\ConfirmationComponent;
use NyonCode\WireCore\Modals\View\ModalComponent;
use NyonCode\WireCore\Modals\View\SlideOverComponent;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;
use NyonCode\WireCore\Notifications\NotificationManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WireCoreServiceProvider extends PackageServiceProvider
{
    /** Absolute path to the pre-bundled, self-registering browser assets. */
    public const ASSETS_PATH = __DIR__.'/../dist';

    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireCore')
            ->hasShortName('wire-core')
            ->registeredPackage(function ($packager) {
                $this->registerFoundation();
                $this->registerCore();
                $this->registerNotifications();
                $this->registerPlugins();
                $this->registerResources();
            })
            ->bootedPackage(function ($packager) {
                $this->bootFoundation();
                $this->bootActions();
                $this->bootNotifications();
                $this->bootModals();
                $this->bootPlugins();
                $this->bootResources();
                $this->registerAssetRoutes();
            })
            ->hasConfig()
            ->hasCommand(PruneAuditEntriesCommand::class)
            // Wire the audit pipeline: HasAuditable models fire AuditableEvents and
            // this subscriber persists them through AuditLogger. Declared rather than
            // subscribed by hand — the toolkit subscribes it in the same boot pass —
            // and unconditional, because the logger itself gates on
            // `wire-core.audit.enabled` and the subscription is idempotent for apps
            // that also register it themselves.
            ->hasSubscriber(AuditEventSubscriber::class)
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations('resources/lang')
            // `wire-core-dropdown.js` carries every shared Alpine controller
            // (wireDropdown, wireContextMenu, wireTabs, wireWizard, wireEditableCell,
            // wireFillHandle) — the interaction layer, which is exactly what must never
            // arrive late.
            ->hasAssets('dist', entries: [
                Bundle::make('wire-core-dropdown.js'),
                // The delegated clipboard controller. In core because two packages ask
                // for the same affordance — a table's copyable cell and an infolist's
                // copyable entry — and core is the lowest layer that can own it.
                Bundle::make('wire-core-copy.js'),
                // Was `loadedOnRequest()` under the old registry, on the grounds that
                // charts are the heavy optional class. They are not: this is 671 bytes
                // of Alpine registrar around `window.Chart`, which is the consuming
                // app's own dependency and is not shipped here. Delivering a registrar
                // late is the one thing ADR 0024 forbids, so it ships with the rest.
                Bundle::make('wire-core-chart.js'),
            ])
            ->hasAssetFallback(Bundle::servedByRoute('wire-core'))
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                // Assets are deliberately not published here. Publishing is what
                // switches this package's bundles from route delivery to static
                // files, and that is a decision about the whole stack — one
                // `vendor:publish --tag=laravel-assets` covers every installed
                // package, where the installer would flip only this one.
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->publishViews()
                    ->publishTranslations();
            });
    }

    /**
     * Extra rows for this package's `php artisan about` section (the toolkit
     * already prepends "Version"). Values are closures so config resolves at
     * boot, not at declaration time.
     *
     * @return array<string, string|\Closure>
     */
    public function aboutData(): array
    {
        return [
            'Notifications' => fn (): string => (string) config('wire-core.notifications.default', 'session'),
            'Icons' => fn (): string => (string) config('wire-core.icons.default_set', 'default'),
            'Audit' => fn (): string => config('wire-core.audit.enabled', true) ? 'enabled' : 'disabled',
        ];
    }

    // ─── Foundation ─────────────────────────────────────────────

    protected function registerFoundation(): void
    {
        // Row-granular rendering: a write can render the regions it touched
        // instead of the view. Inert until something calls renderPartial().
        //
        // In the REGISTER phase deliberately. ComponentHookRegistry::boot() walks
        // its static list once and wires an `on('mount')` listener per hook; one
        // registered after that sits in the list unwired until the NEXT app boot.
        // Under Testbench that means the first test of a process silently has no
        // hook and later ones do — which is exactly how it presented.
        ComponentHookRegistry::register(PartialRenderHook::class);

        $this->app->singleton(IconManager::class, function ($app) {
            $manager = new IconManager;

            // Register icon sets declared in config. The set whose key matches
            // `icons.default_set` becomes the unprefixed base (Heroicons by
            // default); every other set's key is its required prefix, so its icons
            // are addressed as `prefix:name` (e.g. `lucide:home`).
            $defaultKey = config('wire-core.icons.default_set', 'default');
            /** @var array<int|string, mixed> $sets */
            $sets = config('wire-core.icons.sets', []);

            foreach ($sets as $prefix => $class) {
                if (! is_string($class) || ! is_a($class, IconSet::class, true)) {
                    continue;
                }

                if ($prefix === $defaultKey) {
                    $manager->setDefaultIconSet($app->make($class));

                    continue;
                }

                if (! is_string($prefix) || $prefix === '') {
                    throw new \InvalidArgumentException(
                        "Icon set [{$class}] must be configured under a string prefix key in "
                        .'wire-core.icons.sets (e.g. \'lucide\' => LucideIconSet::class).'
                    );
                }

                $manager->registerIconSet($app->make($class), $prefix);
            }

            // Load SVG files from any configured directories. This is the
            // easiest way to add custom icons — no class required. A string key
            // is used as a name prefix (e.g. 'brand' => '/path' → 'brand-logo'),
            // which also avoids collisions when two folders share a file name.
            /** @var array<int|string, mixed> $paths */
            $paths = config('wire-core.icons.paths', []);

            foreach ($paths as $prefix => $path) {
                if (is_string($path) && is_dir($path)) {
                    $manager->registerIconsFromDirectory($path, is_string($prefix) ? $prefix : '');
                }
            }

            return $manager;
        });

        // Canonical owner of record-invariant primitive markup (spinner, success
        // check). Singleton so its per-request string memo spans the whole request.
        $this->app->singleton(Primitives::class);

        // Canonical owner of the copy-to-clipboard affordance — markup here, the
        // delegated listener in the `copy` bundle, the feedback pill in
        // `partials.copy-assets`. Singleton so its per-shape compile is per request.
        $this->app->singleton(CopyButton::class);

        // Canonical owner of an editable cell's server→client sync node. Singleton
        // for the same reason: the partial is rendered once into a skeleton and
        // every editable cell on the page splices its two values into it.
        $this->app->singleton(CellSync::class);

        // Stateless, and asked for once per editable cell — so leaving it
        // unregistered meant a reflective build per cell, which measured at
        // ~14µs each: 40ms of pure container on a 500-row table with three
        // editable columns, for an object with no constructor and no state.
        $this->app->singleton(RecordVersion::class);

        // Thin facade over the toolkit's renderer for the floating-dropdown bundle
        // URL, kept because a dozen partials already resolve it by that name.
        $this->app->singleton(FloatingAssets::class);
    }

    protected function bootFoundation(): void
    {
        // An island renders without the shared `$__livewire` a full render has, and
        // every modal here is an Htmlable that only shared data reaches. See the class.
        IslandViewScope::register();

        // Register <x-wire::icon />, <x-wire::badge />, etc. `<x-wire::icon>` stays
        // the consumer-facing Blade API. The framework's OWN partials never render
        // icons through it (a full Blade component = one view render per call);
        // they call the `icon()` helper — `{!! icon('check', 'w-5 h-5') !!}` — which
        // returns the memoised IconManager <svg> string (zero view renders) and can
        // forward Alpine/data-* attributes via its $attributes argument.
        Blade::componentNamespace('NyonCode\\WireCore\\Foundation\\View', 'wire');

        // `@wireStackScripts` — the one tag an app puts in its layout <head> to get
        // every wireStack Alpine controller into the initial document (which is what
        // survives Livewire's cached Back/Forward navigation).
        //
        // Now an alias for the toolkit's `@packageAssets`, which since 2.4.2 renders
        // every package that declared entries when given no argument. Kept rather than
        // removed because it is in consuming apps' layouts, and a minor release is no
        // place to break a `<head>`. Note the widened meaning: the aggregate is every
        // *toolkit* package, not only the four wireStack ones — which is what a layout
        // wants anyway, and the argument the aggregate was added for.
        //
        // A thin passthrough, the same as before: the whole expression goes to the
        // renderer, so an app may still narrow it to one package.
        Blade::directive('wireStackScripts', static fn (string $expression): string => sprintf(
            '<?php echo app(%s::class)->tags(%s); ?>',
            '\\'.PackageAssets::class,
            $expression,
        ));

        // Octane: three memos that are per-request everywhere else become
        // per-worker-lifetime here, so flush them as each request ends.
        //
        // The view-render memo is a class static that would otherwise accumulate
        // (unbounded growth; potential cross-tenant bleed). The asset URLs are the
        // subtler one: each carries the `?id=<mtime>` of its mirrored copy, so a
        // worker still alive across a deploy would keep emitting last release's query
        // string — and `data-navigate-track`, which exists to catch exactly that,
        // would never fire. Since the toolkit owns the tag there is one memo to
        // flush, not two.
        //
        // RecordVersion's baselines are the third, and the one where a leak would
        // be a correctness bug rather than a stale URL: a baseline is "the stamp
        // this REQUEST first saw", and holding one into the next request would
        // forgive an optimistic-lock version that has genuinely gone stale since.
        //
        // Referenced by string, not ::class import: laravel/octane is an optional
        // dependency the package does not require, so the symbol may not exist.
        $octaneRequestTerminated = 'Laravel\\Octane\\Events\\RequestTerminated';
        if (class_exists($octaneRequestTerminated)) {
            Event::listen($octaneRequestTerminated, function (): void {
                Component::flushViewRenderCache();
                $this->app->make(PublishedAssets::class)->flush();
                $this->app->make(RecordVersion::class)->flush();
            });
        }
    }

    // ─── Core Infrastructure ──────────────────────────────────

    protected function registerCore(): void
    {
        $this->app->singleton(ValidationPipeline::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(MetadataRegistry::class);

        // ActionPipeline is transient — each execution gets a fresh instance
        $this->app->bind(ActionPipeline::class);
    }

    // ─── Actions ────────────────────────────────────────────────

    protected function bootActions(): void
    {
        // Register <x-wire-actions::button />, <x-wire-actions::group />, etc.
        Blade::componentNamespace('NyonCode\\WireCore\\Actions\\View', 'wire-actions');

        // Map short aliases for cleaner component names
        Blade::component('wire-actions::button', ButtonComponent::class);
        Blade::component('wire-actions::group', GroupComponent::class);
        Blade::component('wire-actions::bulk-button', BulkButtonComponent::class);
        Blade::component('wire-actions::modal-host', ModalHostComponent::class);
    }

    // ─── Notifications ──────────────────────────────────────────

    protected function registerNotifications(): void
    {
        $this->app->singleton(NotificationDriver::class, function ($app) {
            $driver = $app['config']->get('wire-core.notifications.default', 'session');

            return match ($driver) {
                'livewire' => new LivewireEventDriver,
                'flasher' => new FlasherDriver,
                'null' => new NullDriver,
                default => new SessionDriver,
            };
        });

        $this->app->singleton(NotificationManager::class);
    }

    protected function bootNotifications(): void
    {
        // Register <x-wire-notifications::toast-container /> etc.
        Blade::componentNamespace('NyonCode\\WireCore\\Notifications\\View', 'wire-notifications');
    }

    // ─── Modals ─────────────────────────────────────────────────

    protected function bootModals(): void
    {
        // Register <x-wire-modals::modal />, <x-wire-modals::confirmation />, etc.
        Blade::componentNamespace('NyonCode\\WireCore\\Modals\\View', 'wire-modals');

        // Map short aliases for cleaner component names
        Blade::component('wire-modals::modal', ModalComponent::class);
        Blade::component('wire-modals::confirmation', ConfirmationComponent::class);
        Blade::component('wire-modals::slide-over', SlideOverComponent::class);

        // Universal alias: <x-wire::modal />
        Blade::component('wire::modal', ModalComponent::class);
    }

    // ─── Plugins ────────────────────────────────────────────────

    /**
     * One registry per application.
     *
     * A singleton because the menu, the model router and boost introspection
     * must see the same set, and a resource added in code has to survive the
     * request that added it.
     */
    protected function registerResources(): void
    {
        $this->app->singleton(ResourceRegistry::class);
    }

    /**
     * Put the configured resources in the registry.
     *
     * Booted rather than registered: a resource class is application code that
     * may reference models and policies, so it is read once the application's
     * own providers have run.
     */
    protected function bootResources(): void
    {
        $this->app->make(ResourceRegistry::class)->registerMany(config('wire-core.resources', []));
    }

    protected function registerPlugins(): void
    {
        $this->app->singleton(PluginManager::class);

        // Register plugins from config
        $this->app->afterResolving(PluginManager::class, function (PluginManager $manager) {
            /** @var list<mixed> $plugins */
            $plugins = $this->app['config']->get('wire-core.plugins', []);

            foreach ($plugins as $pluginClass) {
                if (! is_string($pluginClass) || ! is_subclass_of($pluginClass, Plugin::class)) {
                    continue;
                }

                $manager->register($this->app->make($pluginClass));
            }
        });
    }

    protected function bootPlugins(): void
    {
        if ($this->app->bound(PluginManager::class)) {
            $this->app->make(PluginManager::class)->boot();
        }
    }

    // ─── Assets ─────────────────────────────────────────────────

    /**
     * Serve the package's pre-bundled JS directly so consumers get the floating
     * dropdown behaviour without running npm, a build step, or `vendor:publish`.
     */
    protected function registerAssetRoutes(): void
    {
        Route::get('/wire-core/assets/{asset}.js', function (string $asset): BinaryFileResponse {
            $file = self::ASSETS_PATH.'/wire-core-'.basename($asset).'.js';

            abort_unless(is_file($file), 404);

            return response()
                ->file($file, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('asset', '[A-Za-z0-9_-]+')
            ->name('wire-core.asset');
    }
}
