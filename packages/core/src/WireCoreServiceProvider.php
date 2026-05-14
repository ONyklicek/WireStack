<?php

declare(strict_types=1);

namespace NyonCode\WireCore;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Actions\View\BulkButtonComponent;
use NyonCode\WireCore\Actions\View\ButtonComponent;
use NyonCode\WireCore\Actions\View\GroupComponent;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionRegistry;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Modals\View\ConfirmationComponent;
use NyonCode\WireCore\Modals\View\ModalComponent;
use NyonCode\WireCore\Modals\View\SlideOverComponent;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;
use NyonCode\WireCore\Notifications\NotificationManager;

class WireCoreServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireCore')
            ->hasShortName('wire-core')
            ->hasConfig()
            ->hasViews()
            ->hasTranslations('resources/lang')
            ->hasAbout();
    }

    public function register(): void
    {
        parent::register();

        $this->registerFoundation();
        $this->registerCore();
        $this->registerNotifications();
        $this->registerPlugins();
    }

    public function boot(): void
    {
        parent::boot();

        $this->bootFoundation();
        $this->bootActions();
        $this->bootNotifications();
        $this->bootModals();
        $this->bootPlugins();
    }

    // ─── Foundation ─────────────────────────────────────────────

    protected function registerFoundation(): void
    {
        $this->app->singleton(IconManager::class, function () {
            return new IconManager;
        });
    }

    protected function bootFoundation(): void
    {
        // Register <x-wire::icon />, <x-wire::badge />, etc.
        Blade::componentNamespace('NyonCode\\WireCore\\Foundation\\View', 'wire');
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
    }

    // ─── Plugins ────────────────────────────────────────────────

    protected function registerPlugins(): void
    {
        $this->app->singleton(PluginManager::class);

        // Register plugins from config
        $this->app->afterResolving(PluginManager::class, function (PluginManager $manager) {
            /** @var array<int, class-string<Plugin>> $plugins */
            $plugins = $this->app['config']->get('wire-core.plugins', []);

            foreach ($plugins as $pluginClass) {
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
}
