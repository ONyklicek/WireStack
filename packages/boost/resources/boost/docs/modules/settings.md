---
order: 50
summary: Typed application settings — storage, a cache and a screen from the module, and what is configurable from the application.
---

# The Settings Module

Every application ends up with a handful of things somebody wants to change
without a deploy. This is the table, the cache and the screen for them.

```bash
composer require nyoncode/wire-module-settings
php artisan wire-module-settings:install
php artisan migrate
```

## How It Works

**The module owns storage and a screen; the application owns what is
configurable.** A package that shipped its own list of settings would be guessing
what an application needs, so a group is a class you write:

```php
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

final class BrandingSettings implements SettingsGroup    // [tl! focus:start]
{
    public static function group(): string { return 'branding'; }

    public static function label(): string { return __('Branding'); }

    public static function schema(): array
    {
        return [TextInput::make('company_name'), Toggle::make('dark_default')];
    }
}                                                        // [tl! focus:end]
```

```php
// config/wire-module-settings.php
'groups' => [App\Settings\BrandingSettings::class],
```

**Values keep their type.** The column is JSON, so a boolean comes back a
boolean and an array survives — a settings table that stringifies everything
makes every reader cast by hand, and they disagree.

**A group is cached as one entry.** Settings are read on nearly every request and
written almost never; per key would be one lookup per read, per group is one for
a page that reads six. Every write drops the group's entry, including a write
made through the model — a seeder, a data migration, tinker — because
invalidation lives where the write happens rather than only in `Settings`.

**Reads are safe before the table exists**, and the answer is not remembered.
A settings call sits in code that also runs during `migrate` and in a fresh
install, so a missing table answers the default rather than throwing. It is
deliberately not cached: `rememberForever` means forever, and a deploy that
touched a setting before `migrate` would otherwise leave the application
answering "empty" until something wrote a value.

**A group saves whole or not at all.** `Settings::fill()` is one transaction, and
that is the same promise the screen is built on: it edits one group at a time so
it never has to say "your branding saved but your mail did not", and a loop of
six writes where the fourth fails says exactly that about one group's own fields.

**A group is a URL.** The route is `settings/{record}`, so `settings/mail` opens
mail settings and a person can bookmark it. That is why the switcher is links
rather than tabs — a tab state living in a Livewire snapshot cannot be
bookmarked. A group this application does not declare is a 404 and one this user
may not see is a 403, because the alternative for both is a form with no fields
in it, and empty reads as "there is nothing to configure here".

## Reading Settings

```php
use NyonCode\WireModuleSettings\Support\Settings;

Settings::get('company_name', 'Acme', 'branding');
Settings::set('dark_default', true, 'branding');
Settings::fill(['company_name' => 'Acme', 'dark_default' => true], 'branding');
Settings::has('dark_default', 'branding');
Settings::remove('dark_default', 'branding');
Settings::clear('branding');
Settings::all('branding');
```

`get()` resolves in one order, and it is worth knowing which: **the stored value,
then the group's declared default, then the `$default` you passed.** The last one
is the fallback for a key the group never declared at all.

## Defaults Belong To The Group

The alternative is the default living at every call site — the same
`'noreply@example.com'` written in six places, five of them agreeing. A group
declares them once by implementing one more interface:

```php
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

final class MailSettings implements SettingsGroup, ProvidesSettingsDefaults
{
    public static function group(): string { return 'mail'; }

    public static function label(): string { return __('Mail'); }

    public static function schema(): array
    {
        return [TextInput::make('from_address')->email(), Toggle::make('queue')];
    }

    public static function defaults(): array          // [tl! focus:start]
    {
        return ['from_address' => 'noreply@example.com', 'queue' => true];
    }                                                 // [tl! focus:end]
}
```

`Settings::get('from_address', null, 'mail')` now answers the declared value, and
so does the screen — the fields have the right values in them the first time
somebody opens it, rather than being a page of empty inputs.

`has()` is the question the default does *not* answer: whether anybody actually
stored a value. A data migration auditing the table wants that one.

## Icons, Descriptions And Order

A second optional interface, for a group that wants to look like something:

```php
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;

final class MailSettings implements SettingsGroup, DescribesSettingsGroup
{
    // …

    public static function icon(): ?string { return 'outline:envelope'; }    // [tl! focus:start]

    public static function description(): ?string
    {
        return __('Where transactional mail is sent from.');
    }

    public static function sort(): int { return 20; }                     // [tl! focus:end]
}
```

The icon goes beside the group in the switcher, the description under the
heading, and `sort()` decides the order. Groups that declare no order keep the
one the config listed them in — the sort is stable, so a group that names a
number does not shuffle the ones that say nothing. A single declared group draws
no switcher at all: one group is not a choice.

**The page gives a flat schema its card.** Every resource form gets its surface
from the `Section`s the resource declared, and a settings group — whose whole
contract is a heading and a list of fields — is not obliged to declare any;
rendered bare, its inputs would sit directly on the page background, which no
other screen in a panel does. So the page wraps them, and only where the wrapper
is missing: a group that brings its own `Section`, `Grid` or `Tabs` is rendered
as it stands, because a card around a card is a border inside a border and the
group already said where its own edges are.

## A Package Can Ship A Tab

`groups` is the application's list and stays that way — a panel's owner says
what their panel configures. The other half is a package that ships a feature
*and* the tab that configures it, which otherwise has to end its README with
"now add this class to your config": the one instruction every other surface in
this framework stopped giving. A module registers itself, and so does its
settings.

```php
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;

public function configure(Packager $packager): void
{
    $packager
        ->name('AcmeBilling')
        ->hasShortName('acme-billing')
        ->hasConfig()
        ->bootedPackage(function (): void {
            SettingsRegistry::instance()->register(BillingSettings::class);   // [tl! focus]
        });
}
```

Nothing else changes: the group is the same `SettingsGroup` an application
writes, with the same optional contracts, saving into the same table.

**Who wins.** The application's list is read first, so a group it declares under
the same storage name replaces the contributed one and sorts ahead of it among
groups that name no `sort()`. A tab it does not want at all goes in `except`:

```php
// config/wire-module-settings.php
'except' => ['billing'],
```

That escape hatch is the reason a package may ship a tab at all — a
package-shipped screen an application cannot remove is what makes people stop
installing them.

**Register from `boot`, and it still works from `register`.** Provider order in
a Laravel application is composer's discovery order, not a contract, so a
contributing package's provider may well run before this module's.
`SettingsRegistry::instance()` binds itself on first touch, so whichever gets
there first creates the one instance and the other finds it — without that, a
registration would land in a throwaway object and the tab would be missing on
some machines depending on a lockfile.

## Who May Change What

Two levels, and both are `Gate::allows()` — nothing here re-implements an
authorization check, so Laravel's own gates, `spatie/laravel-permission` and
`nyoncode/laravel-permission-extended` all answer them the way they answer every
other check in this framework.

**The screen** is guarded by one line of config. It becomes Laravel's `can:`
middleware on both routes and hides the menu entry that leads to them:

```php
// config/wire-module-settings.php
'permission' => 'settings.manage',
```

Null by default, because a permission this package invented would lock the screen
out of every installation that has no such ability. A settings screen is, after
the audit log, the one most worth naming one for: it is where an application's
behaviour is changed without a deploy.

**One group** may ask for more than the screen does:

```php
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;

final class BillingSettings implements SettingsGroup, GuardsSettingsGroup
{
    // …

    public static function permission(): ?string { return 'settings.billing'; }
}
```

A group the current user fails is not in the switcher, opening its URL is a 403,
and so is saving it — the group name is a public property, so it rides in the
Livewire snapshot and comes back from the browser, and a user who may open one
group must not be able to save another by editing the value that travels.

A user who fails *every* declared group gets a 403 for the screen too, rather
than the empty state: that one says "declare a SettingsGroup class and list it in
config", which is an instruction for the developer and a lie to everybody else.
An application that has genuinely declared nothing still sees it.

## Reacting To A Change

Settings are the values something else is configured *from*, so changing one
usually has to reach something. `SettingsSaved` is dispatched after every write:

```php
use Illuminate\Support\Facades\Event;
use NyonCode\WireModuleSettings\Events\SettingsSaved;

Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
    if ($event->group === 'mail') {
        Cache::forget('mail-transport');
    }
});
```

`$event->values` is what this write carried, not the whole group; a listener that
wants the rest asks `Settings::all()`, which by then answers with these in it.

## Where It Is Cached

```php
// config/wire-module-settings.php
'cache' => [
    'enabled' => env('WIRE_SETTINGS_CACHE', true),
    'store' => env('WIRE_SETTINGS_CACHE_STORE'),
],
```

The store matters more than it looks. Leaving it null uses the application's
default one, and on an application whose default is `database` the read this
cache exists to avoid is simply replaced by a different query. Name the memory
store you already run and a settings read stops touching the database — the
installer says so when it finds the default is `database`.

Turning caching off is for debugging and for tests that assert against the table
directly.

## Extended Example

A group that uses all three optional contracts, and something reading it:

```php
namespace App\Settings;

use App\Support\Branding;
use Illuminate\Support\Facades\Event;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Support\Settings;

final class BrandingSettings implements
    DescribesSettingsGroup,
    GuardsSettingsGroup,
    ProvidesSettingsDefaults,
    SettingsGroup
{
    public static function group(): string
    {
        return 'branding';
    }

    public static function label(): string
    {
        return __('settings.branding');
    }

    public static function schema(): array
    {
        return [
            TextInput::make('company_name')->label(__('settings.company_name'))->required(),
            TextInput::make('support_email')->label(__('settings.support_email'))->email(),
            Toggle::make('dark_by_default')->label(__('settings.dark_by_default')),
        ];
    }

    public static function icon(): ?string          // [tl! focus:start]
    {
        return 'outline:swatch';
    }

    public static function description(): ?string
    {
        return __('settings.branding_description');
    }

    public static function sort(): int
    {
        return 10;
    }

    public static function defaults(): array
    {
        return ['company_name' => config('app.name'), 'dark_by_default' => false];
    }

    public static function permission(): ?string
    {
        return 'settings.branding';
    }                                               // [tl! focus:end]
}
```

```php
// A view composer, a mailable, a PDF header — anywhere the value is needed.
Settings::get('company_name', group: 'branding');

// And the cache something else keeps, dropped when the value moves.
Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
    if ($event->group === 'branding') {
        Branding::flush();       // [tl! focus]
    }
});
```

## Related

- [Modules](../panels/modules.md) — how a package ships an area like this
- [Resources](../panels/resources.md) — the catalogue this screen is registered in
- [Forms](../forms/overview.md) — the components a group's schema is written with
