---
order: 30
summary: "The notification that outlives the request — stored, counted, marked read, and shown in the bell's panel."
---

# Persistent Notifications

Some things are worth telling someone who is not looking at the screen right now.
A persistent notification is stored against a recipient, counted while it is
unread, and shown in a panel behind a bell — the same value object as a toast,
kept instead of shown.

## Persistent Notifications

The drivers above deliver to the page being rendered, which is the right answer
for "Saved" and the wrong one for a queued export finishing twenty minutes later:
by then there is no component to dispatch to and no session to flash into.
`DatabaseDriver` writes the notification down instead.

```php
// config/wire-core.php
'notifications' => [
    'default' => ['session', 'database'],
],
```

A **list** picks several drivers at once, and that is usually what you want: the
toast now, and the record in the bell for a user who was looking at another tab.
A single string still works and stays a single driver.

### Who it is for

Persisted notifications belong to a recipient. Two ways to say who, and the
order matters: **what the notification says**, then **who is authenticated**.

```php
Notification::success('Your export is ready')->to($user);

NotificationManager::sendTo($user, Notification::success('Your export is ready'));
```

`->to()` is for the case the session cannot answer, which is also the case the
stored drivers exist for: a queued job finishing at three in the morning has
nobody logged in, and the user it concerns is an argument it was given. One job
can address several people in a loop, and nothing has to be rebound.

Said nothing? The recipient comes from `ResolvesNotifiable` — whoever is
authenticated. With nobody there the driver writes **nothing**: a row stored
against no one cannot be read by anyone. Bind your own resolver when the answer
is something other than "the logged-in user" for the whole application — an
impersonated user, a tenant:

```php
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;

app()->bind(ResolvesNotifiable::class, fn () => new class implements ResolvesNotifiable {
    public function resolve(): ?Model
    {
        return User::find(session('acting_as'));
    }
});
```

The binding has one owner and every reader agrees with it: the drivers that
write the row, the channel they broadcast on, and the bell that subscribes.

The recipient is deliberately **not** part of the stored payload. That JSON is
what the notification says; the recipient is which row it is stored in.

### From a Laravel notification

`via() => ['wire']` delivers a Laravel notification into the bell, which brings
`ShouldQueue`, `Notifiable`, per-user `via()`, mail going out beside it and
`Notification::fake()` with it:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NyonCode\WireCore\Notifications\Notification as WireNotification;

class InvoicePaid extends Notification implements ShouldQueue
{
    public function __construct(public Invoice $invoice) {}

    public function via($notifiable): array
    {
        return ['mail', 'wire'];                                    // [tl! focus]
    }

    public function toWire($notifiable): WireNotification           // [tl! focus:start]
    {
        return WireNotification::success('Invoice paid')
            ->title($this->invoice->number)
            ->url(route('invoices.show', $this->invoice));
    }                                                               // [tl! focus:end]
}

$user->notify(new InvoicePaid($invoice));
```

The recipient comes from Laravel, not from the session, so this works from a
queue worker as it stands. A `toWire()` that calls `->to()` itself wins — an
escalation addressed to a manager is a real thing to do.

Pair it with `database`: a Laravel notification delivered only to a transient
driver is delivered to whatever page happened to be rendering, which for a queued
job is no page at all. `illuminate/notifications` is not a requirement of this
package — the channel registers only when the class is there, which in a Laravel
application it always is.

### Retention

A notification is the one kind of row designed to stop mattering, so nothing
keeps them forever unless you say so:

```php
// config/wire-core.php
'notifications' => [
    'database' => [
        'retention_days' => 365,        // everything
        'read_retention_days' => 30,    // what the user has already seen
    ],
],

// routes/console.php
Schedule::command('wire-core:notifications-prune')->daily();
```

Two windows, because "read" and "never looked at" are different claims. Either
may be null, which means keep. `--days` and `--read-days` override for a one-off
run, and with neither a period nor an option the command says so and prunes
nothing — deleting rows because nobody said not to is not a default worth having.

### Live: telling the other tabs

`database` fixes *where* the notification is kept. It does not fix *when* the
user finds out: the row is invisible until that tab next talks to the server,
which for a tab nobody is clicking in is never. `broadcast` is the half that
closes it.

```php
// config/wire-core.php
'notifications' => [
    'default' => ['session', 'database', 'broadcast'],
],
```

The three together are the whole arrangement: the **toast** for the tab that
asked, the **row** for later, and the **nudge** for every other tab and device.
`broadcast` on its own announces a notification that was never stored — the
client re-reads and finds nothing.

**Nothing about the notification is on the wire.** `NotificationReceived` carries
one thing, the recipient's channel name, and `broadcastWith()` is empty. It is a
nudge to re-read, not a message to render: the client answers it with
`$wire.$refresh()`, so the list and the count come back through
`NotificationCenter` — through the recipient scoping that is the only thing
stopping one user seeing another's rows. A payload here would move that decision
to a channel subscription and put the text of every notification somewhere an
intercepted socket can read it.

That is also why a missed one is survivable: the next render is correct
regardless. No Echo on the page, a socket that dropped over lunch, a refused
subscription — the bell is **late, never wrong**.

The event is `ShouldBroadcastNow`, deliberately. A queued broadcast in the very
common setup of a configured queue with no worker running for it does nothing at
all, silently — and here there is no poll underneath to cover for it. The cost is
stated plainly: the write waits on the broadcaster's HTTP call before it answers.

#### The channel

One private channel per recipient, named by `NotificationChannel`, which owns
both the name and the authorization so the two cannot disagree:

```php
NotificationChannel::for($user);   // wire-notifications.App-Models-User.7
NotificationChannel::PATTERN;      // wire-notifications.{notifiable}.{key}
```

The morph class travels with its backslashes replaced by `-`. Laravel compiles a
`{placeholder}` to `([^\.]+)`, so a dotted class could not be matched by a
wildcard at all, and a backslash is not a legal channel character either. It is
never decoded back: a morph map alias may legitimately contain a hyphen
(`'blog-post' => BlogPost::class`), so authorizing compares the viewer's own
**encoded** morph class against the segment.

Authorization is registered for you when `broadcast` is in the driver list, with
the strictest rule there is — a viewer may subscribe to their own channel and to
nobody else's. Turn it off and write your own when that is not your policy:

```php
// config/wire-core.php
'notifications' => [
    'broadcast' => ['authorize' => false],
],

// routes/channels.php
use NyonCode\WireCore\Notifications\Support\NotificationChannel;

NotificationChannel::authorize(                                     // [tl! focus:start]
    fn ($user, string $notifiable, string $key): bool => $user->isSupervisor()
        || NotificationChannel::matches($user, $notifiable, $key),  // [tl! focus:end]
);
```

The two segments arrive from the client and are handed on exactly as they came —
encoded, unresolved, and not to be trusted as a class name.

**A refused subscription is the one failure worth being loud about**, because it
is the only one that looks like success: the bell keeps updating on every render,
so nothing appears broken while the live half is simply dead. The bridge writes
one `console.warn` and stops.

Which broadcaster carries it is entirely your business. This is a plain Laravel
broadcast event with string channel names, and the client half calls nothing but
`window.Echo.private()` and `window.Echo.leave()`.

### The bell

```blade
@livewire('wire-notification-bell')
@livewire('wire-notification-bell', ['limit' => 5])
```

An unread count, and a **slide-over panel** behind it: the newest few with two
tabs (*All* and *Unread*), mark-one and mark-all, and a link to the full list
where one is routed. A panel rather than a 320px dropdown because an inbox is a
place you go, not a menu you brush past — there is room for the message under its
title, and for the tab that hides what has been read.

**The mark has three states, not two.** A bell with no badge cannot say whether
nothing has ever happened or whether you have read everything, so it says both:
a plain bell for nothing, a quiet grey dot for "something is in there, nothing is
waiting", and the count — the only loud thing here — for unread. The count sits
in a ring the colour of the surface behind it, so at 16px it reads as a chip
attached to the bell rather than a blob overlapping it, and it is repeated in the
button's accessible name because a coloured circle says nothing to a screen
reader.

**The list reads as a timeline**, newest first, under *Today* / *Yesterday* /
*Earlier* headings that stick while their own run scrolls past. It used to float
unread to the top so a burst of reads could not push an old unread item off a
ten-row list — the **Unread** tab answers that directly and for every row, where
the ordering only ever answered it for the first ten.

**A row goes where the notification says.** `->url()` is the thing it is about —
the invoice, the export — and the row links there; its own page is the fallback,
and exists only where something routes the `notifications` key, which is what
installing [the notifications module](../../modules/notifications.md) does. The bell
asks `ResolvesPageUrls` and renders the panel without links when the answer is
null, so there is nothing to configure either way.

The row keeps a real `href` so the link can be copied and middle-clicked, while
the plain left click goes through the server — which is what marks it read on the
way past, instead of racing a navigation the browser is entitled to win.

**Stored action buttons render too**, and a link is the kind to reach for: an
event reaches a Livewire listener that has to be on the page, which a
notification opened three days later has no reason to expect. Their colours are
the six the toast container knows — `success`, `error`, `warning`, `info`,
`primary`, `gray` — falling back to the notification's own type, so an action
written once reads the same on both surfaces.

**The bell carries its zone.** `Zone::current()` answers nothing on a Livewire
round trip (ADR 0027 §3), so the bell reads it once while the page renders and
carries it from there — its links then point back into the zone they were opened
in. Pass one explicitly for a bell in a shell that is not itself a wire route:
`@livewire('wire-notification-bell', ['zone' => 'admin'])`.

**The verbs are the ones an inbox has**: mark read, mark unread, delete per row,
mark-all and clear-read in the footer. Each is absent rather than disabled when
it has nothing to do. There is deliberately no "delete everything" — what has
been read is what the user has already seen, so clearing that cannot lose them
something they have not looked at, and a button that can is a button an inbox
should not have.

With `broadcast` in the driver list the bell subscribes to the recipient's
channel and re-reads as notifications land. Without it, it is right on every
render and no sooner: add `wire:poll` yourself, or dispatch
`wire-notification-received` from an application that knows one arrived.

Behind it, `NotificationCenter` answers the same questions without a component,
for a console command or a JSON endpoint:

```php
$center = app(NotificationCenter::class);

$center->unreadCount();          // the number on the bell
$center->latest(10);             // unread first, then newest
$center->unread(10);
$center->markAsRead($id);        // scoped to the recipient
$center->markAllAsRead();        // returns how many were unread
```

Every one is scoped to the resolved recipient, `markAsRead()` included — an id
arriving from a Livewire action is user input, and an unscoped lookup would let
one user mark another's notification read.

### The table

The migration matches Laravel's own `notifications` shape (id / type /
notifiable / data / read_at), so an application that already has that table can
point `wire-core.notifications.database.table` at it and read both through its
own `Notifiable::notifications()` relation.

The id is a **ULID** in that uuid column. Both are strings that fit, but a ULID
sorts by the time it was made — and five notifications from one bulk job land in
the same second, where `created_at` alone orders them arbitrarily.

## Related

- [Notifications](index.md) — the object and its builder
- [Toasts](toasts.md) — the same notification, delivered to the screen
- [The Notifications Module](../../modules/notifications.md) — the full history screen over these rows
- [Custom Drivers](custom-drivers.md) — storing them somewhere else
