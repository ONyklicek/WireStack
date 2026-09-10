<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-dark.png">
  <img src="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-light.png" alt="WireStack" width="1200">
</picture>

# Wire Module Audit

The audit trail [Wire](https://github.com/nyoncode) already records, as a screen
you can read.

```bash
composer require nyoncode/wire-module-audit
php artisan wire-module-audit:install
```

The engine has shipped in `wire-core` for versions — `HasAuditable` fires the
events, `AuditLogger` writes them, a prune command keeps the table honest. What
no package shipped was a way to read any of it without SQL.

## What it shows

| Screen | Notes |
| --- | --- |
| Audit log | Event, record, actor, the fields that changed and when; filter by event, record type, actor and date range; newest first |
| One entry | The before and after of every field that moved, and the request it came from — IP, user agent, whatever the event carried |

Nothing here owns a table. `AuditEntry`, its migration and the change diff are
core's, and core publishes that migration on demand:

```bash
php artisan vendor:publish --tag=wire-core::migrations
php artisan migrate
```

The installer says so when it is missing, rather than leaving you an empty screen
to explain.

## What it does not do

**Write.** An audit entry that can be edited is not an audit entry — there is no
form, no create page, and no delete button. Retention is
`wire-core.audit.retention_days` and the `wire-core:audit-prune` command, which
is a scheduled decision rather than a button somebody can be talked into
pressing.

**Guard itself.** `wire-module-audit.permission` names an ability and the routes
and links follow it; unset, the screen is as open as the rest of the panel. Who
may read the log is your policy — this is the screen most worth naming one for.

**Invent an actor.** Entries store the key of whoever was signed in, and jobs,
seeders and console commands legitimately store none. Those read as *System*; a
key whose user is gone reads as *Unknown user* with the key beside it.

## Documentation

Full docs: [`docs/modules/audit.md`](../../docs/modules/audit.md)
([česky](../../docs/cs/modules/audit.md)) — and
[`docs/core/audit.md`](../../docs/core/audit.md) for the engine underneath.
