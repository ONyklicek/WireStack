---
order: 10
summary: The whole stack in one require, and one interactive command that turns a clean Laravel into a working admin.
---

# Installing Wire

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

The first line brings core, forms, tables, sortable, resources and the admin
shell. The second sets them up — interactively, one part at a time, running each
package's own installer rather than a copy of it.

## What The Installer Does

```text
 INFO  Found in this application

  • Core — nyoncode/wire-core
  • Forms — nyoncode/wire-forms
  • Tables — nyoncode/wire-table
  • Admin shell — nyoncode/wire-admin

 Which parts should be set up? [All of them]

 INFO  Available, not installed here

  User administration, with roles where the application has them.
  composer require nyoncode/wire-module-users
  …
```

Everything is offered pre-selected: an installer whose default is "nothing" makes
the common case the tedious one. `--all` skips the question, which is what a
scripted setup wants.

**It never runs composer.** Offering a module that is not installed is the point
of that second list, and the answer is a line to paste. An artisan command that
shells out to composer runs *inside* the application it is about to change — the
autoloader in use is the one composer is rewriting — and the failure modes
(memory limits, plugins, a production image with no composer at all) are the ones
nobody can debug from a stack trace.

**A part whose provider is not loaded is reported, not fatal.** A class can be
autoloadable while its provider is absent — a `dont-discover` entry, a package
registered in one environment only — and calling a command that is not there
would otherwise abort the whole run.

## The Modules

Each is a separate `composer require`, because an application that wants users
and nothing else should not carry a media library:

| Module | Package |
| --- | --- |
| [Users](../modules/users.md) | `nyoncode/wire-module-users` |
| [Settings](../modules/settings.md) | `nyoncode/wire-module-settings` |
| [Audit log](../modules/audit.md) | `nyoncode/wire-module-audit` |
| [Notifications](../modules/notifications.md) | `nyoncode/wire-module-notifications` |
| [Media](../modules/media.md) | `nyoncode/wire-module-media` |

Install one and run `php artisan wire:install` again; it registers itself, so
there is nothing to add to a config file.

## What Is Left To You

Two things the installer will not decide:

```php
// routes/web.php — the middleware and the prefix are yours
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

```bash
php artisan migrate
```

## Every Command This Ships

`wire:install` runs the per-package installers for you; each is also callable on
its own, which is what an application adding one package later reaches for:

```bash
php artisan wire:install                      # the interactive setup over everything installed
php artisan wire-core:install                 # one package at a time — config, assets, translations
php artisan wire-forms:install
php artisan wire-table:install
php artisan wire-sortable:install
php artisan wire-admin:install                # also writes the layout and the Tailwind @source line
php artisan wire-module-users:install         # …and one per installed module
php artisan wire-boost:install --agent=claude # AI agent guidelines and the MCP entry
```

Two generators and three maintenance commands come with them:

| Command | What it does |
| --- | --- |
| `make:wire-dashboard` | A dashboard class in `app/Dashboards`, ready to name widgets — a dashboard is written, not shipped |
| `wire-core:audit-prune --days=` | Delete audit entries older than the retention window ([Audit Log](../core/audit.md)) |
| `wire-core:notifications-prune` | The same for stored notifications |
| `wire-module-media:thumbnails` | (Re)generate conversions for library files ([Media](../modules/media.md)) |
| `wire-module-media:usage` | Recompute where each file is used |
| `wire-boost:update` | Refresh the AI guidelines after an upgrade |
| `wire-boost:mcp` | Run the MCP server ([Boost](../boost/mcp-tools.md)) |

## Related

- [Getting Started](getting-started.md) — the manual path, package by package
- [The Admin Shell](../admin/overview.md) — the layout your pages render in
- [Modules](../panels/modules.md) — what a module is, and how to write your own
- [Ready-Made Modules](../modules/index.md) — what each installable area brings
