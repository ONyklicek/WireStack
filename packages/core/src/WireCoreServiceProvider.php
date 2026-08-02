<?php

declare(strict_types=1);

namespace NyonCode\WireCore;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
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
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\Assets\Js;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireCore\Foundation\View\CellSync;
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
            })
            ->bootedPackage(function ($packager) {
                $this->bootFoundation();
                $this->bootActions();
                $this->bootAudit();
                $this->bootNotifications();
                $this->bootModals();
                $this->bootPlugins();
                $this->registerAssetRoutes();
                $this->registerAssets();
            })
            ->hasConfig()
            ->hasCommand(PruneAuditEntriesCommand::class)
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations('resources/lang')
            ->hasAssets('dist')
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->publishViews()
                    ->publishTranslations()
                    ->publishAssets();
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

        // Canonical owner of an editable cell's server→client sync node. Singleton
        // for the same reason: the partial is rendered once into a skeleton and
        // every editable cell on the page splices its two values into it.
        $this->app->singleton(CellSync::class);

        // Stateless, and asked for once per editable cell — so leaving it
        // unregistered meant a reflective build per cell, which measured at
        // ~14µs each: 40ms of pure container on a 500-row table with three
        // editable columns, for an object with no constructor and no state.
        $this->app->singleton(RecordVersion::class);

        // Canonical owner of every package's browser assets. Singleton so the
        // registry — and each asset's route + mtime memo — spans the whole request.
        $this->app->singleton(AssetManager::class);

        // Thin facade over the manager for the floating-dropdown bundle URL, kept
        // because a dozen partials already resolve it by that name.
        $this->app->singleton(FloatingAssets::class);
    }

    protected function bootFoundation(): void
    {
        // Register <x-wire::icon />, <x-wire::badge />, etc. `<x-wire::icon>` stays
        // the consumer-facing Blade API. The framework's OWN partials never render
        // icons through it (a full Blade component = one view render per call);
        // they call the `icon()` helper — `{!! icon('check', 'w-5 h-5') !!}` — which
        // returns the memoised IconManager <svg> string (zero view renders) and can
        // forward Alpine/data-* attributes via its $attributes argument.
        Blade::componentNamespace('NyonCode\\WireCore\\Foundation\\View', 'wire');

        // `@wireStackScripts` — the one tag an app puts in its layout <head> to get
        // every wireStack Alpine controller into the initial document (which is what
        // survives Livewire's cached Back/Forward navigation). A thin passthrough:
        // the whole expression is forwarded to the canonical owner, which resolves
        // and memoises the markup; no presentation logic lives in the compiler.
        Blade::directive('wireStackScripts', static fn (string $expression): string => sprintf(
            '<?php echo app(%s::class)->renderScripts(%s); ?>',
            '\\'.AssetManager::class,
            $expression,
        ));

        // Octane: the state-driven view-render memo is a class static that would
        // otherwise accumulate across requests in a long-lived worker (unbounded
        // growth; potential cross-tenant bleed). Flush it as each request ends.
        // Referenced by string, not ::class import: laravel/octane is an optional
        // dependency the package does not require, so the symbol may not exist.
        $octaneRequestTerminated = 'Laravel\\Octane\\Events\\RequestTerminated';
        if (class_exists($octaneRequestTerminated)) {
            Event::listen($octaneRequestTerminated, static function (): void {
                Component::flushViewRenderCache();
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

    /**
     * Wire the audit pipeline: HasAuditable models fire AuditableEvents, and this
     * subscriber persists them through AuditLogger. Registered unconditionally —
     * the logger itself gates on `wire-core.audit.enabled`, and the subscription
     * is idempotent for apps that also register it manually.
     */
    protected function bootAudit(): void
    {
        Event::subscribe(AuditEventSubscriber::class);
    }

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

    /**
     * Declare the package's browser bundles with the canonical {@see AssetManager},
     * so `@wireStackScripts` emits them into the app's layout.
     *
     * `wire-core-dropdown.js` carries every shared Alpine controller (wireDropdown,
     * wireContextMenu, wireTabs, wireWizard, wireEditableCell, wireFillHandle) — the
     * interaction layer, which is exactly what must never arrive late.
     *
     * `wire-core-chart.js` is declared `loadedOnRequest()`: charts are the optional,
     * heavy class, so the directive leaves the body out of every page and the widget's
     * own partial fetches it. Registering it anyway keeps one owner of its URL —
     * the partial asks {@see AssetManager::url()} instead of recomputing route+mtime.
     * The registrar inside the bundle is unconditional, so arriving late is safe.
     */
    protected function registerAssets(): void
    {
        $this->app->make(AssetManager::class)->register([
            Js::make('dropdown', self::ASSETS_PATH.'/wire-core-dropdown.js')
                ->navigateTrack()
                ->navigateOnce(),
            Js::make('chart', self::ASSETS_PATH.'/wire-core-chart.js')
                ->navigateTrack()
                ->navigateOnce()
                ->loadedOnRequest(),
        ], 'wire-core');
    }
}
