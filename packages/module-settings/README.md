# wire-module-settings

Typed application settings for the wire framework: a table, a cache, and a
screen — with **what** is configurable left to the application.

```bash
composer require nyoncode/wire-module-settings
php artisan wire-module-settings:install
php artisan migrate
```

## The one thing you write

A package that shipped its own list of settings would be guessing what an
application needs, so a group is a class:

```php
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

final class BrandingSettings implements SettingsGroup
{
    public static function group(): string { return 'branding'; }

    public static function label(): string { return __('Branding'); }

    public static function schema(): array
    {
        return [TextInput::make('company_name'), Toggle::make('dark_default')];
    }
}
```

```php
// config/wire-module-settings.php
'groups' => [App\Settings\BrandingSettings::class],
```

Three optional contracts sit beside that one, each opt-in so a group that wants
none of them stays exactly this long:

| Contract | What it adds |
| --- | --- |
| `DescribesSettingsGroup` | `icon()`, `description()`, `sort()` |
| `ProvidesSettingsDefaults` | `defaults()` — the values before anybody sets one |
| `GuardsSettingsGroup` | `permission()` — an ability this group alone requires |

## A package can ship a tab

`groups` is the application's list. A package that ships a feature can ship the
tab that configures it, from its own provider:

```php
SettingsRegistry::instance()->register(BillingSettings::class);
```

The application's list wins a collision and sorts first among ties, and
`'except' => ['billing']` drops a contributed tab outright — a package-shipped
screen an application cannot remove is what makes people stop installing them.

## Reading and writing

```php
use NyonCode\WireModuleSettings\Support\Settings;

Settings::get('company_name', 'Acme', 'branding');  // stored → declared default → 'Acme'
Settings::set('dark_default', true, 'branding');
Settings::fill(['a' => 1, 'b' => 2], 'branding');   // one transaction
Settings::has('queue', 'mail');                     // stored, as opposed to defaulted
Settings::remove('queue', 'mail');
Settings::all('branding');
```

`SettingsSaved` is dispatched after every write, which is the hook for anything
configured *from* a setting — a cached config to rebuild, a transport to
re-resolve.

## What it guarantees

- **Values keep their type.** The column is JSON, so a boolean comes back a
  boolean and an array survives.
- **A group is one cache entry**, dropped on write — including a write made
  through the model from a seeder or a console command.
- **Reads are safe before the table exists**, and a read from before `migrate`
  is never what the application answers afterwards.
- **A group saves whole or not at all.**
- **A group is a URL**, so `settings/mail` opens mail settings.

Full documentation: [`docs/core/settings-module.md`](../../docs/core/settings-module.md).
