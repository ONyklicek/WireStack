---
order: 20
summary: "Dvě registrační cesty — config aplikace a provider balíčku — plus konfigurace pluginu a závislosti, které se musí zaregistrovat dřív."
---

# Registrace pluginů

Plugin se k frameworku dostane dvěma způsoby a to, který použiješ, říká, kdo ho
vlastní: **aplikace** si své vypíše v configu, **balíček** si svůj zaregistruje ze
service provideru. Všechno další — konfigurace, výchozí hodnoty i závislost na
jiném pluginu — je na obou cestách stejné.

## Registrace pluginů v configu

Publikujte core config:

```bash
php artisan vendor:publish --tag=wire-core::config
```

Přidejte třídy pluginů do `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
    App\Wire\Plugins\AuditExportPlugin::class,
],
```

Wire resolvuje config-registrované pluginy přes Laravel container, když se resolvuje plugin manager.

Položka, která pluginem být nemůže, se odmítne, nepřeskočí:

| V `plugins` | Co se stane |
|---|---|
| Třída implementující `Plugin` | Zaregistruje se |
| `''` — to, co po sobě nechá koncová čárka | Přeskočí se |
| Název třídy, která neexistuje, nebo třída bez kontraktu | `PluginRegistrationException` se jménem třídy a tím, o který z těch dvou případů jde |
| Cokoli, co není pole | `PluginRegistrationException` |

Přeskakování, které tohle nahrazuje, bylo to drahé: překlep v názvu třídy znamenal, že plugin — a pro osu, která se registruje takhle, celý [modul](../../panels/modules.md) — prostě neexistoval, bez položky v menu a bez čehokoli, co by řeklo proč. Config se čte při bootu, takže odmítnutí se do requestu nedostane.

<a id="register-plugins-from-a-package"></a>

## Registrace pluginů z balíčku

Pokud stavíte doprovodný balíček, zaregistrujte svůj plugin z package service provideru.

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

Guard `has()` předchází duplicitní registraci, pokud aplikace plugin také uvádí v configu.

**`register()`, nikdy `boot()`.** `resolving` se spustí ve chvíli, kdy container manager staví, takže plugin je v seznamu dřív, než proběhne `PluginManager::boot()`, a dřív, než core provider rozprostře deklarace modulu do registrů. Pozdější registrace hodí `PluginRegistrationException`:

```php
public function boot(): void
{
    // Pozdě — a dřív to bylo tiché.
    $this->app->make(PluginManager::class)->register(new AcmePlugin);
}
```

Samotná registrace by proběhla a `has('acme')` by odpovědělo `true`, a právě proto stojí za odmítnutí: pluginu, který přijde takhle pozdě, se `boot()` nikdy nezavolá a resources, dashboardy ani navigační skupina modulu se do registrů nedostanou. Nespraví to ani to, že by se pozdní plugin rovnou nabootoval — routy stránek se registrují uvnitř `boot()` provideru, protože Laravel po něm instaluje cachovanou kolekci rout, takže modul, který dorazí později, nejde zaroutovat vůbec.

Provider, který registruje plugin, nedeferujte. Deferovaný provider se spustí, až se resolvuje něco, co poskytuje — a to už je manager postavený, takže se `resolving` callback nikdy nespustí a plugin se nezaregistruje nikdy.

## Konfigurace pluginu

Pluginy, které přijímají uživatelské volby, mohou implementovat `HasConfiguration`.

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

        // $config je sloučená výchozí a uživatelská konfigurace.
    }
}
```

Uživatelské přepisy žijí pod `wire-core.plugins.config.{pluginId}`:

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

Manager sloučí výchozí hodnoty pluginu s uživatelskou konfigurací pomocí `array_merge()`. Top-level klíče z uživatelské konfigurace nahradí výchozí klíče.

## Závislosti pluginu

Pluginy, které vyžadují jiné pluginy, mohou implementovat `HasDependencies`.

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

Závislosti už musí být registrované. Pokud závislost chybí, `PluginManager::register()` vyhodí `RuntimeException`.

Registrujte závislé pluginy po jejich závislostech:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,
    App\Wire\Plugins\BillingExportPlugin::class,
],
```

<a id="hook-system"></a>

## Související

- [Pluginy](index.md) — kontrakt a životní cyklus, do kterého se tohle zapojuje
- [Hooky](hooks.md) — callbacky, které zaregistrovaný plugin přidává
- [Moduly](../../panels/modules.md) — táž registrační cesta, pro celou oblast
- [Konfigurace](../../start/configuration.md) — celý klíč `plugins`
