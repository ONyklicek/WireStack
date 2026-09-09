---
order: 60
summary: The history behind the notification bell — the same rows with a table over them, scoped to the viewer by default.
---

# The Notifications Module

The bell's panel shows the latest few. This is the same rows with a table over
them: searchable, filterable by read state, and readable after they have scrolled
out of the panel.

```bash
composer require nyoncode/wire-module-notifications
php artisan wire-module-notifications:install
```

## How It Works

**Scoped to the viewer.** A notification is addressed to somebody, so the default
shows the signed-in user's own. Signed out, that means **none** — the tempting
alternative, no filter at all, is a leak. `scope => 'all'` turns it into an
administrative view, which is a different screen and wants a policy on its page.

**Opening one marks it read**, in the page's own record hook, because that is
what every inbox has always done and the unread count is then right without
anyone pressing anything.

**It is not in the sidebar, and that is the design.** Notifications are not an
entity you administer from a menu — they are an inbox, and the way in is the
bell: it carries the unread count, and its panel links straight here. A permanent
row for a screen you reach from a badge is the row nobody reads. The page stays
registered and routed either way, so its URL keeps working; set
`navigation.visible` (or `WIRE_NOTIFICATIONS_IN_NAVIGATION=true`) if you want the
row as well.

**Only the `database` driver stores anything.** Under the default `session`
driver there is nothing to list, and the installer says so.

## What You Get

| Screen | Notes |
| --- | --- |
| Notifications | The notification with the line it was written to say, tinted by type, newest first; relative times; search over the payload; filter unread or read |
| One notification | Its type, its times and the payload as key/value |

It reads as an inbox rather than as a table of columns, and the choices behind
that are worth stating because they are easy to undo by accident:

- **Unread is weight, not a cell.** The row is tinted and set in medium, the same
  way the bell's panel says it — so the two surfaces read as one product, and the
  column a `STATE` chip used to occupy is the one the message now uses.
- **The search reads the payload.** The visible text lives in the JSON `data`
  column, so the search is declared over `data->title` and `data->message`. A
  search box that finds nothing is worse than no search box.
- **The row verbs sit behind one quiet trigger.** Mark read, mark unread, delete.
  A page whose whole job is to be read should not be dominated by a solid blue
  button and a solid red one on every line.
- **It is not drawn as a table.** `layout('list')`, and with it the three
  controls that only mean something over a grid of columns: no checkbox on every
  row, no column panel, no `Show [10] records`. Marking everything read is a
  header action over the whole filtered set, which is stronger than a selection
  and quieter than one.
- **The row opens the notification's own page**, which is what marks it read —
  deliberately its own page rather than whatever `->url()` points at, because a
  list whose rows sometimes go to an invoice and sometimes to a notification is a
  list you cannot click confidently. The payload's link is on the page it opens.

Selecting rows offers the three verbs of an inbox — **mark read**, **mark
unread** and **delete**. Nothing there re-scopes: the selection comes out of the
same query the table lists, which is already the viewer's own rows, so a bulk
action cannot reach further than the screen it was started from.

## Related

- [Notifications](../core/notifications/index.md) — the drivers, the bell, the live half and the toasts
- [Modules](../panels/modules.md) — how a package ships an area like this

