---
order: 5
summary: "What lives in wire-core, how its modules are layered, and which page answers which question."
---

# Wire Core

`wire-core` is what everything else is built out of. It ships no table and no
form: it ships the vocabulary both of them speak — actions, modals,
notifications, icons, colours, a schema — plus the surfaces that need neither a
list nor a form of their own.

```bash
composer require nyoncode/wire-core
```

Every other package requires it, and it requires none of them. That is why a
concern that is genuinely shared belongs here rather than in the package that
happened to need it first.

## How It Works

The modules are **layered**, and the layering is enforced by a test rather than
by review:

| Layer | What is in it | May see |
| --- | --- | --- |
| **L0** | `Foundation/`, `Exceptions/` — traits, contracts, enums, value objects | nothing above it |
| **L1** | `Core/` — the headless engine: plugins, resources identity, registries | L0 |
| **L2** | `Actions/`, `Modals/`, `Notifications/`, `Widgets/`, `Infolists/`, `Panels/`, `Audit/` | L0 and L1 — never each other |

Two surfaces in L2 that need to talk do it through a contract in
`Foundation/Contracts/`, not by importing each other. That is what keeps an
application that uses infolists and no notifications from loading the
notification manager, and what would let any of these become its own package
without a rewrite.

The practical consequence for a reader: **a capability that appears in two places
has one owner here.** A colour is `HasColor`, an icon is `HasIcon`, a size is
`HasSize` — and a badge column, a badge entry and a notification all resolve
theirs through the same code, so the vocabulary is worth learning once.

## In This Section

| Page | Answers |
| --- | --- |
| [Foundation](foundation/index.md) | The shared traits, base classes, icons, colours, enums and Blade components |
| [Actions](actions/index.md) | What runs on a click — row, bulk and header actions, groups, confirmation, queues |
| [Modals](modals.md) | The dialogs an action opens: confirmation, slide-over, multi-step wizard |
| [Notifications](notifications/index.md) | Toasts and stored notifications, their drivers, and where each delivers |
| [Widgets](widgets/index.md) | Stats, charts, embedded tables, custom views, and the dashboards that hold them |
| [Infolists](infolists/index.md) | One record displayed read-only from a schema |
| [Editable Panels](record-panels.md) | The same shape, with entries that write straight back to the record |
| [Schema](schema/overview.md) | The layout vocabulary forms, infolists and modals all consume |
| [Global Search](global-search.md) | The ⌘K palette over everything registered |
| [Audit Log](audit.md) | The trail of model changes, recorded for you |
| [Plugins](plugins/index.md) | The extension point for applications and companion packages |

## Where To Go Next

Core is rarely the whole answer. What is built on it:

- [Forms](../forms/overview.md) and [Table](../table/overview.md) — the two surfaces
- [Panels](../panels/overview.md) — one entity declared once, with pages and routes
- [The Admin Shell](../admin/overview.md) — the optional frame around all of it

## Related

- [Configuration](../start/configuration.md) — `config/wire-core.php` in full
- [Theming](../start/theming.md) — the colour and icon vocabulary, and how to extend it
- [Authorization](../start/authorization.md) — the gate every surface here consults
