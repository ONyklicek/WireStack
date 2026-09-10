<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-dark.png">
  <img src="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-light.png" alt="WireStack" width="1200">
</picture>

# wire-module-notifications

The history behind the notification bell, as a screen: the same rows with a
table over them, scoped to the viewer by default.

```bash
composer require nyoncode/wire-module-notifications
php artisan wire-module-notifications:install
```

## How it works

**Scoped to the viewer.** A notification is addressed to somebody, so the
default shows the signed-in user's own. Signed out, that means *none* — the
tempting alternative, no filter at all, is a leak. `scope => 'all'` turns it
into an administrative view, which is a different screen and wants a policy on
its page.

**Opening one marks it read**, in the page's own record hook, so the unread
count is right without anyone pressing anything.

**It is not in the sidebar, and that is the design.** Notifications are an inbox
rather than an entity you administer from a menu, and the way in is the bell.
The page stays registered and routed either way, so its URL keeps working; set
`navigation.visible` (or `WIRE_NOTIFICATIONS_IN_NAVIGATION=true`) if you want
the row as well.

**Only the `database` driver stores anything.** Under the default `session`
driver there is nothing to list, and the installer says so.

## What you get

| Screen | Notes |
| --- | --- |
| Notifications | The line the notification was written to say, tinted by type, newest first; relative times; search over the payload; filter unread or read |
| One notification | Its type, its times and the payload as key/value |

It reads as an inbox rather than a table of columns: unread is weight rather
than a cell, the search reads the JSON payload, the row verbs sit behind one
quiet trigger, and `layout('list')` drops the three controls that only mean
something over a grid — no checkbox on every row, no column panel, no
`Show [10] records`.

Selecting rows offers the three verbs of an inbox — **mark read**, **mark
unread** and **delete**. Nothing there re-scopes: the selection comes out of the
same query the table lists, so a bulk action cannot reach further than the
screen it was started from.

## Documentation

Full docs: [`docs/modules/notifications.md`](../../docs/modules/notifications.md)
([česky](../../docs/cs/modules/notifications.md)) — and
[`docs/core/notifications/index.md`](../../docs/core/notifications/index.md) for
the drivers, the bell and the toasts.
