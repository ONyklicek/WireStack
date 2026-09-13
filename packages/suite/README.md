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

**It only runs what has something left to do.** A part whose installer has
already written everything it publishes is named and skipped, which is a
correctness rule rather than a speed one: a migration shipped without a date
prefix is stamped when the publish mapping is built, so re-publishing writes a
*second* copy of one the application already has and `migrate` then fails on it.
Re-running after adding a module is therefore safe. `--force` sets up every part
regardless and publishes over what it wrote.

**A failed installer fails the command**, and `--no-interaction` reaches the
installers it runs — each of them prompts before touching a production
application, from inside a progress spinner where nobody can answer.

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
composer require nyoncode/wire-module-auth
composer require nyoncode/wire-module-users
composer require nyoncode/wire-module-settings
composer require nyoncode/wire-module-audit
composer require nyoncode/wire-module-notifications
composer require nyoncode/wire-module-media
```

Then `php artisan wire:install` again — a module registers itself, so nothing
goes into a config file, and the parts already set up are left alone.

`nyoncode/wire-boost` (AI agent guidelines, skills and the MCP server) is listed
beside them and never run: `wire-boost:install` asks which agents to configure,
and that is not a question this command should answer for anyone.

## Documentation

Full docs: [`docs/start/installation.md`](../../docs/start/installation.md)
([česky](../../docs/cs/start/installation.md)).

## License

MIT.
