---
order: 20
summary: "The two registration paths — an application's config and a package's provider — plus per-plugin configuration and the dependencies that must register first."
---

# Registering Plugins

A plugin reaches the framework two ways, and which one you use says who owns it:
an **application** lists its own in config, a **package** registers its own from
its service provider. Everything after that — configuration, defaults, and
depending on another plugin — is the same on both paths.

## Register Plugins In Config

Publish the core config:

```bash
php artisan vendor:publish --tag=wire-core::config
```

Add plugin classes to `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
    App\Wire\Plugins\AuditExportPlugin::class,
],
```

Wire resolves config-registered plugins through Laravel's container when the plugin manager is resolved.

An entry that cannot be a plugin is refused, not skipped:

| In `plugins` | What happens |
|---|---|
| A class implementing `Plugin` | Registered |
| `''` — what a trailing comma leaves behind | Skipped |
| A class name that does not exist, or a class without the contract | `PluginRegistrationException`, naming the class and which of the two it is |
| Anything that is not an array | `PluginRegistrationException` |

The skip these replace was the expensive one: a typo in a class name meant the plugin — a whole [module](../../panels/modules.md), for the axis that registers this way — simply did not exist, with no menu entry and nothing anywhere saying why. The config is read at boot, so a refusal cannot reach a request.

## Register Plugins From A Package

If you are building a companion package, register your plugin from the package service provider.

```php
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeWireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
            if (! $manager->has('acme')) {
                $manager->register($this->app->make(AcmePlugin::class));
            }
        });
    }
}
```

The `has()` guard prevents duplicate registration if the application also lists the plugin in config.

**`register()`, never `boot()`.** `resolving` fires while the container builds the manager, so the plugin is in the list before `PluginManager::boot()` runs and before the core provider spreads a module's declarations into the registries. Registering later throws `PluginRegistrationException`:

```php
public function boot(): void
{
    // Too late — and it used to be silent.
    $this->app->make(PluginManager::class)->register(new AcmePlugin);
}
```

The registration itself would have succeeded and `has('acme')` would answer `true`, which is what made it worth refusing: `boot()` is never called on a plugin that arrives then, and a module's resources, dashboards and navigation group never reach the registries. It cannot be fixed by booting the late plugin either — page routes are registered inside the provider's `boot()`, because Laravel installs a cached route collection after that, so a module arriving later cannot be routed at all.

Do not defer a provider that registers a plugin. A deferred provider runs when something it provides is resolved, and by then the manager is built, so the `resolving` callback never fires and the plugin is never registered.

## Plugin Configuration

Plugins that accept user options can implement `HasConfiguration`.

```php
<?php

namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class ExportPlugin implements HasConfiguration, Plugin
{
    public function getId(): string
    {
        return 'export';
    }

    public function defaultConfig(): array
    {
        return [
            'format' => 'csv',
            'chunk_size' => 500,
        ];
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        $config = $manager->getPluginConfig($this->getId());

        // $config is the merged default and user configuration.
    }
}
```

User overrides live under `wire-core.plugins.config.{pluginId}`:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,

    'config' => [
        'export' => [
            'format' => 'xlsx',
        ],
    ],
],
```

The manager merges the plugin defaults with user config using `array_merge()`. Top-level keys from user config replace default keys.

## Plugin Dependencies

Plugins that require other plugins can implement `HasDependencies`.

```php
<?php

namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class BillingExportPlugin implements HasDependencies, Plugin
{
    public function getId(): string
    {
        return 'billing-export';
    }

    public function dependencies(): array
    {
        return ['export'];
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        //
    }
}
```

Dependencies must already be registered. If a dependency is missing, `PluginManager::register()` throws a `RuntimeException`.

Register dependent plugins after their dependencies:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,
    App\Wire\Plugins\BillingExportPlugin::class,
],
```

## Related

- [Plugins](index.md) — the contract and the lifecycle these hook into
- [Hooks](hooks.md) — the callbacks a registered plugin adds
- [Modules](../../panels/modules.md) — the same registration path, for a whole area
- [Configuration](../../start/configuration.md) — the `plugins` key in full
