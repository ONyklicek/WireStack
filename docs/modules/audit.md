---
order: 70
summary: A screen for the audit trail wire-core already records — the event, the person, the record, and the before and after of every field that moved.
---

# The Audit Module

The engine has shipped for versions: `HasAuditable` fires the events,
`AuditLogger` writes them, and a pruning command keeps the table honest. What no
package shipped was a way to read any of it without SQL.

```bash
composer require nyoncode/wire-module-audit
php artisan wire-module-audit:install
```

## How It Works

**Read-only, and that is the design.** An audit entry that can be edited is not
an audit entry, so the resource declares an index and a view and nothing else —
no form, no create page, and `ProvidesResourceForm` deliberately absent. There is
no delete button either: retention is `wire-core.audit.retention_days` and the
`wire-core:audit-prune` command, which is a scheduled decision rather than a
button somebody can be talked into pressing.

**Over `wire-core`'s own model.** The module owns no table: `AuditEntry`, its
migration and its change diff belong to core, and this points at them. Core
publishes that migration on demand, so a fresh installation may have the screen
before it has the table — the installer says so rather than leaving you an empty
log to explain.

```bash
php artisan vendor:publish --tag=wire-core::migrations
php artisan migrate
```

**Every column has a named owner.** The event's label and colour, the actor's
name, the record's name, the fields that moved — each answer is needed in a
column, a filter *and* the detail page, and a screen that worked each of them out
three times would eventually work them out differently.

**Recording is still core's switch.** With `wire-core.audit.enabled` off there is
nothing to show, and the installer says that too.

## Who Did It

An entry stores the key of whoever was signed in; the screen shows a name, read
through the relation core defines and eager-loaded once for the page rather than
once per row.

Three answers, because they are three different facts:

| Stored | Shown | Why |
| --- | --- | --- |
| no `user_id` | *System* | Seeders, queued jobs and console commands audit changes with no auth context — `AuditLogger` records those on purpose |
| a key with no user | *Unknown user #12* | The account is gone, so the key is all that is left of it — and it stays visible |
| a key with a user | their name | The first of the configured attributes they actually have |

`name` is a convention rather than a contract, so which attribute reads as a
person's name is yours to say:

```php
// config/wire-module-audit.php
'actor' => [
    'attributes' => ['full_name', 'email'],
],
```

The user model itself is core's setting, because core defines the relation:

```php
// config/wire-core.php
'audit' => [
    'user_model' => App\Models\Account::class,
],
```

## What Changed

The list names the fields that moved; the entry page puts the before beside the
after as **one table**, a row per field, over the diff core already computes
(`AuditEntry::getChangeDiff()`).

One table rather than a card per field, which is what it drew first: a card
carries the *Field / Old / New* headings once per row, so an update touching
eight columns repeated them twenty-four times — the work the diff exists to have
already done. It is core's [`ChangesEntry`](../core/infolists/entries.md#changesentry), the same
component the trail slide-over draws inside a record, so a change reads the same
in both places.

How a value reads has one owner too,
`NyonCode\WireCore\Foundation\ValueObjects\ChangeSet`: a stored array — a JSON
column, a bulk action's list of ids — is rendered as text rather than as the word
`Array`; a boolean is `true` or `false` rather than `1` and nothing; and a value
that was not there reads as *(empty)* rather than as a blank that looks
unchanged.

The page itself is three sections, in the order the questions arrive: **what
happened** (the event, the moment, the person, the record), **changes**, and
**the request** it came in on — the IP and the user agent `AuditLogger` records,
plus anything the event carried. The last one starts collapsed: it is the half
nobody opens the page for, and the half that can be longest.

## From The Log To The Record

A row says something happened to invoice seven, and the next thing anybody wants
is invoice seven. Where the audited model has a resource and that resource is
routed, the row offers a link to it — resolved through the registry, in the zone
the list was opened in.

Where it cannot be resolved there is no link, rather than one that leads nowhere.
That covers more cases than it sounds: a model with no resource, an application
that routes the log and nothing else, and a class the log outlived.

Applications that keep class names out of their database are read back through
the same map that wrote them, so a `morphMap` alias is labelled and linked like
any other type.

## Guarding The Screen

Unset, the log is as open as the rest of the panel. Name an ability and it guards
the routes and hides the links that lead to them, from the one line:

```php
// config/wire-module-audit.php
'permission' => 'audit.view',
```

It is checked through `Gate::allows()`, which both permission packages register
themselves into — so a wildcard (`audit.*`) and a super-admin bypass work without
this module knowing they exist. An audit log is the screen most worth naming one
for.

## What You Get

| Screen | Notes |
| --- | --- |
| Audit log | Event, record, actor, the fields that changed, when — filter by event, record type, actor and date range; newest first |
| One entry | The before and after of every field that moved, and the request it arrived in |

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `model` | `AuditEntry::class` | The entry model; it must extend core's, and one that does not is refused rather than shown as an empty log |
| `actor.attributes` | `['name', 'email']` | Which attribute of a user reads as their name; the first they have wins |
| `permission` | `null` | Ability required to read the log — `null` leaves it open |
| `navigation.group` | `system` | Menu group |
| `navigation.label` | `null` | Menu group heading; `null` uses the module's own |
| `navigation.icon` | `outline:clipboard-document-list` | Menu icon |
| `navigation.sort` | `95` | Menu group order |

## Related

- [Audit Log](../core/audit.md) — the engine, and what it records
- [Modules](../panels/modules.md) — how a package ships an area like this
