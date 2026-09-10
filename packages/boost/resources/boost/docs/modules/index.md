---
order: 10
summary: Six whole areas shipped as composer packages — what each one installs, what it needs from the application, and how to take one back out.
---

# Ready-Made Modules

A [module](../panels/modules.md) is one business area's manifest: the resources,
dashboards and menu heading it consists of. These six ship that manifest **as a
composer package**, so an area arrives with `composer require` rather than with a
class to write and a config line to remember.

```bash
composer require nyoncode/wire-module-users
```

That is the whole installation of most of them. The package's own provider
registers the module as a plugin, the module fills the registries that already
existed, and the menu, the router and the search palette pick it up from there —
the same path an application's own module takes.

## How It Works

Nothing here is a second kind of thing. A shipped module is
[a plugin](../core/plugins/index.md) that names [resources](../panels/resources.md) and
dashboards, and everything downstream reads it through
[the catalogue](../panels/navigation.md#catalog-api):

1. `composer require` puts the package on disk; Laravel discovers its provider.
2. The provider registers the module with `PluginManager` — no config edit, which
   is the difference between a shipped module and one an application declares.
3. `WireCoreServiceProvider` spreads what it declares into the resource registry,
   the dashboard registry and the navigation groups.
4. `Route::wireResources()`, the sidebar and the palette read those registries and
   find the new area without being told.

**A module an application cannot remove is what makes people stop installing
them**, so every one of them is removable in two ways: `composer remove` takes
the whole area out, and each module's config can drop or replace what it
contributed while the package stays. Where a module ships a screen over something
the application already owns — a settings tab, a user resource — the
application's own declaration wins over the contributed one.

## What Each One Installs

| Module | Package | What arrives | Needs |
| --- | --- | --- | --- |
| [Users](users.md) | `wire-module-users` | The user resource, its pages, a profile screen, and role management where the application has roles | A user model; `nyoncode/laravel-permission-extended` for the role surfaces |
| [Auth](auth.md) | `wire-module-auth` | Sign-in, password reset, e-mail verification, the two-factor challenge, **Sign out** in the user menu, and [one-time codes](auth.md#one-time-codes) where a mailed code stands in for a password or a link | `laravel/fortify` — it owns the security, the module owns the screens |
| [Settings](settings.md) | `wire-module-settings` | A typed settings table, its cache, and the screen over it | A database connection |
| [Notifications](notifications.md) | `wire-module-notifications` | The history behind the notification bell, as a table | Laravel's `notifications` table |
| [Audit](audit.md) | `wire-module-audit` | A screen for the trail `wire-core` already records | `HasAuditable` on the models you want followed |
| [Media](media.md) | `wire-module-media` | A media library — uploads, folders, previews, and a picker for forms | A filesystem disk |

[Teams and Two-Factor](teams-and-two-factor.md) is not a seventh package: it is
the guide to switching on the two surfaces the users module keeps behind an
`auto` switch, which look for the thing itself — Fortify for two-factor, the
permission package for teams — and stay off when it is absent.

## Installing Them Together

`wire:install` offers every module it can see and names the ones it cannot,
because offering a module that is not installed is the point — a list of
`composer require` lines you can copy is more useful than silence:

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

It never runs composer for you. See [Installing Wire](../start/installation.md)
for what the installer does and what it refuses to do.

## Writing Your Own

The six are ordinary modules with a provider around them, and
[`packages/module-users`](users.md) is the reference implementation. What a module
declares, how it depends on another, and how a package ships one are covered in
[Modules](../panels/modules.md).

## Related

- [Modules](../panels/modules.md) — the manifest itself, and declaring one in an application
- [Resources](../panels/resources.md) — what a module's areas are made of
- [Plugins](../core/plugins/index.md) — the registration path a module takes
- [Installing Wire](../start/installation.md) — the interactive installer that offers them
