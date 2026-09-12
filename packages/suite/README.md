<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-dark.png">
  <img src="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-light.png" alt="WireStack" width="1200">
</picture>

# WireSuite

The whole [WireSuite](https://github.com/nyoncode) stack in one require, and one
command that turns a clean Laravel into a working admin.

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

`wire:install` finds the parts that are installed, asks which to set up
(everything is pre-selected), and runs each package's own installer rather than a
copy of it. Then it lists the modules this application does not have yet, with
the `composer require` line for each.

**It never runs composer itself.** The command runs inside the application it is
about to change — the autoloader in use is the one composer would rewrite — and
the failure modes (memory, plugins, a production image without composer) are the
ones nobody can debug from a stack trace. A line to paste is the honest answer.

## What comes with it

| | Package |
| --- | --- |
| Engine, actions, modals, notifications, widgets, infolists | `wire-core` |
| Forms | `wire-forms` |
| Tables | `wire-table` |
| Drag-and-drop reordering | `wire-sortable` |
| Resources, pages, routing, zones | `wire-panels` |
| The admin shell | `wire-admin` |

## Modules, separately

Each is its own require, because an application that wants users and nothing else
should not carry a media library:

```bash
composer require nyoncode/wire-module-users
composer require nyoncode/wire-module-settings
composer require nyoncode/wire-module-audit
composer require nyoncode/wire-module-notifications
composer require nyoncode/wire-module-media
```

Then `php artisan wire:install` again — a module registers itself, so nothing
goes into a config file.

## Documentation

Full docs: [`docs/start/installation.md`](../../docs/start/installation.md)
([česky](../../docs/cs/start/installation.md)).

## License

MIT.
