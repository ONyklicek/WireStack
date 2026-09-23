---
order: 82
summary: Guided walkthroughs that run the first time somebody sees a screen, and again after an upgrade changes it. Scoped by zone, resource, page and permission.
---

# Tour

A walkthrough that points at real elements of a screen, one step at a time: a
panel beside the element, a line or two about it, and Next, Back and Skip. It
runs by itself the first time a person sees a screen it claims. After an upgrade
you can have it run again for everybody who finished the previous version.
Reach for it when a screen has enough on it that a new user would not know where
to start. A tooltip is enough for one control; a tour is for how several of them
fit together.

```php
use NyonCode\WireCore\Tours\Tour;
```

## How It Works

A tour is a definition you register once, in a service provider. The framework
then decides on every full page render whether this person should see one, and
the browser runs it.

**Which tour runs.** While the layout renders the page, the framework reads
three things from the current route: the zone, the registered resource and the
kind of page (`index`, `create`, `view`, `edit`, or a resource's own page). It
then walks the registered tours, **lowest `sort()` first**, and takes the first
one that passes all of these:

1. This person has not already finished or skipped **this version** of it, and
   has not [put it off](tour-welcome.md) for the session they are in.
2. Its location constraints match. Each of `zones()`, `resource()` and `page()`
   that you set must match, and one you did not set matches anything.
3. Its visibility allows it. That is `visible()` / `hidden()` and the shared
   authorization: `permission()`, `authorize()`, `authorizeUsing()`.

If more than one tour matches, only the first runs. The rest wait for a later
visit and run once the first has been finished or skipped. Two walkthroughs back
to back is worse than one.

**Defaults.** A tour with no constraints runs for everybody, on every screen,
at version `'1'` and sort `0`. Nothing is registered by default. The framework
ships no tour of its own, so an application that registers none gets nothing at
all.

**What "seen" means.** Finishing and skipping are recorded the same way. Both
store the tour's current `since()` value against its id, for that person. The
tour runs again only if that stored value differs from `since()`. That one
comparison covers both cases:

- a first visit, where nothing is stored yet, and
- an edited tour, where the stored value is old.

Nothing compares version numbers as numbers, and nothing reads a package
version. To show a tour again to everybody, change `since()`.

**Where it runs.** Deciding and recording happen on the server. Moving between
steps happens in the browser, in Alpine. The panel is positioned by the
Floating UI helper that `@wireStackScripts` already puts on every page, so this
feature adds no JavaScript bundle of its own. Two things make a Livewire
request: each newly shown step, to record how far this person got, and
finishing or skipping, to record that they are done.

**Left halfway.** Somebody who leaves a tour by clicking elsewhere, without
finishing or skipping it, meets it again on their next visit to a page it
starts on, at the step they left it on. If that step is on another page, the
tour opens at the last step before it on this page, so "Next" leads back to
it. The step is stored against the tour's `since()` value, so after a new
version the tour starts from the beginning. Finishing, skipping and replaying
all clear it.

**Where it is stored.** Under the `tours` key of the per-user preference store,
using its own driver setting, `wire-core.tours.preferences.default`. **That
defaults to `session`**, not to the `null` driver the rest of the preference
store defaults to. With a store that remembers nothing, a tour would interrupt
the same person on every page load. With `session`, the worst case is once per
session. For "once, ever", switch it to `database` and run the migration (see
[Remembering](#remembering)). A guest uses the guest driver, which is also
`session`.

**Traps.**

- **The route is read once, while the page renders.** During a Livewire request
  the current route is Livewire's own update endpoint, not your page, so zone,
  resource and page are all unknown there. The framework therefore reads them
  once, while the layout renders, and passes them along from then on.
- **The id is the identity.** Acknowledgements are stored against it. Renaming a
  tour's id is the same as registering a new tour: everybody sees it again.
- **A step whose element is missing or hidden is skipped, and not counted.**
  The browser checks every step when the tour starts and keeps only the ones
  whose element is showing, so the progress counter always says how many steps
  the person will actually see. An element that is rendered but hidden, such as
  a dropdown that has not been opened, counts as missing. A tour that loses all
  of its steps this way does not start.
- **On a phone the panel docks to the bottom.** Below 640px it stops floating
  beside the element and sits across the bottom of the screen, while the
  highlight ring still marks the element. Each step scrolls its element into the
  room between the top bar and the panel. What a phone hides — the sidebar is a
  drawer there — is skipped like any hidden element, so a phone can count fewer
  steps than a desktop. The panel is re-placed when the window crosses 640px
  either way.
- **A step on another page navigates there.** A step made with
  [`on()`](tour-step.md#on-another-page) belongs to another page of the same
  zone, and is skipped for somebody that page's route does not let in. "Next" navigates there and the tour carries on; "Back" from it comes
  back. The page and step travel in the query string (`wire-tour`,
  `wire-tour-step`), which the browser removes once the tour has picked them up.
  The server accepts it only for a tour this person has not finished, in the
  zone it was declared for, at a step that really is on that page.
- **It needs the shell's layout.** The tour is drawn by `wire-admin`'s layout.
  If you render your own layout instead, see [Your own layout](#your-own-layout).

## Basic Usage

Register it from a service provider's `boot()`:

```php
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

$this->app->make(Tours::class)->register(
    Tour::make('getting-started')->steps([
        TourStep::make('admin-sidebar')->text('Every area of the application is here.'),
        TourStep::make('global-search-trigger')->text('Or jump straight to any record.'),
    ]),
);
```

Each step names an **element hook**, the `data-wire` name the framework puts on
its markup. You find one the way you would find anything on a page — open
devtools and read the attribute off the element, or list them in bulk with the
one-liner in [Theming → Finding a name](../start/theming.md#finding-a-name).
[TourStep](tour-step.md) covers steps in full.

## Asking First

A tour that starts by itself is an interruption somebody never agreed to. Give
it a [welcome block](tour-welcome.md) and the first beat becomes a question
instead — a card in the middle of the screen, with a button to start now and one
to be asked again later:

```php
use NyonCode\WireCore\Tours\TourWelcome;

Tour::make('getting-started')
    ->welcome(
        TourWelcome::make()
            ->heading('Two minutes, and you will know your way around')
            ->text('We will point at four things and then leave you to it.'),
    )
    ->steps([...]);
```

**Later is not Skip.** Skip is final — it is recorded exactly as finishing is.
Later puts the tour down for the session and asks again on the next visit, and
is counted: the one that reaches `postpone()`'s limit (default `3`) records the
tour as seen, so a greeting cannot come back for ever.
[TourWelcome](tour-welcome.md) covers the block, its labels and its own markup.

## Registering

`Tours` is a singleton. Every call to `register()` adds to the same registry, so
a package and an application can both register tours. Registration is
**idempotent by id**: if an id is registered again, the second registration is
ignored and the first one stays. A provider that boots twice therefore cannot
create two copies of a tour. It also means two definitions with the same id are
never merged.

A tour is checked when it is registered. **A tour with no steps throws** a
`TourDefinitionException` at that point. Without the check, it would be
recorded as seen the moment it started, for something the person never saw.

```php
$this->app->make(Tours::class)->register($first, $second, $third);
```

## Who Sees It

A tour is visible to people the way any other component is. It uses the same
[shared authorization](../start/authorization.md#shared-component-rules) as
columns, actions and widgets, checked through Laravel's `Gate`. Wildcard
permissions and the super-admin gate from `laravel-permission-extended` apply
as they do everywhere else:

```php
Tour::make('sales-orders')->permission('sales.*');

Tour::make('month-end')->authorize('closeTheBooks');

Tour::make('team-leads')->authorizeUsing(fn (User $user) => $user->leads_a_team);
```

A tour with a permission is **never shown to a guest**. Showing a walkthrough of
a screen to somebody who cannot open it gives that screen away, so authorization
fails closed here like everywhere else.

Different people usually need different tours: an administrator a tour of the
settings, somebody in sales a tour of orders. Register one tour per audience.
Each has its own id, so each is acknowledged separately. Somebody whose role
changes has seen one and not the other, and gets the other.

## Where It Runs

Three constraints, all optional, combined with AND:

```php
Tour::make('orders-list')
    ->zones('sales')          // only in the sales zone
    ->resource('orders')      // only on the orders resource's pages
    ->page('index');          // only on its list
```

Each takes several values and matches any of them:

```php
->resource('orders', 'invoices')
->page('index', 'view')
```

**Zones** are the route-name prefixes of [routing zones](../panels/routing.md).
`zones('sales')` and `zones('sales.')` mean the same thing. An application
without zones has one unnamed zone, and **`zones(null)`** names it. You need
that to say "only outside every zone"; leaving `zones()` out means "any zone,
including none".

```php
->zones(null)             // the unzoned application only
->zones('sales', null)    // the sales zone, or no zone at all
```

For a condition these three cannot express, use `visible()` with a closure. It
runs while the layout renders the page, only for a tour whose zone, resource
and page already matched, and never during a Livewire request. It can run more
than once per page, because the replay entry asks the same question, so keep it
cheap.

These say where a tour **starts**. A step can still lead elsewhere in the same
zone with [`on()`](tour-step.md#on-another-page). The page it lands on does not
have to match the tour's constraints, because the tour is carried there rather
than chosen there.

## What's New After an Upgrade

A tour of something new is the same kind of tour. Give it the release you are
shipping it in:

```php
Tour::make('saved-views')
    ->since('2.2')
    ->resource('orders')
    ->steps([
        TourStep::make('table-view-save')->text('Save the filters you use every day.'),
    ]);
```

When you change what a tour shows, change its `since()` as well. Everybody who
finished the old version will see the new one once, and nobody else sees it
twice. The value only needs to be different: it can be a release number, a date
or a word.

**Editing the steps is not enough on its own.** Nothing hashes a tour's content,
so a tour whose `since()` did not move stays acknowledged and the new wording
reaches only people who had never finished it. Deploying a new release does not
do it either — a tour is not new because a patch went out. Changing `since()` is
the one thing that says "show this again", and it is the whole upgrade path:
there is no second store to clear and no command to run.

A what's-new tour is often one step long. It uses the same scoping, so a
feature only one role can use gets a tour only that role sees.

## Order

When several tours claim the same screen for the same person, the **lowest
`sort()` runs**. The next one runs on a later visit, once the first has been
finished or skipped.

```php
Tour::make('admin-settings')->permission('admin.*')->sort(-10);
Tour::make('everyone')->sort(0);
```

It is an explicit number because there is no reliable way to pick the most
specific match. A zone constraint and a permission constraint are not
comparable. Tours with the same sort keep the order they were registered in.

## Remembering

The tour's own storage setting, separate from the rest of the preference store:

```php
// config/wire-core.php
'tours' => [
    'preferences' => [
        'default' => env('WIRE_TOURS_DRIVER', 'session'),
        'guest' => env('WIRE_TOURS_GUEST_DRIVER', 'session'),
    ],
],
```

| Driver | Remembers for | Needs |
| --- | --- | --- |
| `session` (default) | The session | Nothing |
| `database` | Ever | The `wire_preferences` migration |
| `null` | Nothing — the tour runs on every page load | Nothing; for testing only |

```bash
php artisan vendor:publish --tag="wire-core::migrations"
php artisan migrate
```

## Replaying

When a tour claims the current screen, a **Replay the tour** entry appears in
the signed-in person's user menu. It clears their record of that one tour, and
only that one, and reloads the page, so the tour runs again. On screens no tour
claims, the entry is not rendered at all.

The reload is intentional. The panel is part of the page layout, which a
Livewire request does not re-render. The server does not store the address to
return to either: the browser reloads the page it is already on.

### Your Own Trigger

The user-menu entry is the one the framework draws, and it is rendered only
where a tour already claims the screen — so forgetting the tour is enough there,
and the browser reloads the page it is on. A trigger **somewhere else** — a help
button in a page header, a row on a settings screen — is for a tour that runs
where the person is not, so forgetting it alone would appear to do nothing.
`replayNow()` forgets it *and* hands back the way to the screen it runs on:

```php
use Livewire\Component;
use NyonCode\WireCore\Tours\TourState;

class HelpButton extends Component
{
    public function replayOnboarding(TourState $tours)
    {
        return $tours->replayNow('getting-started', auth()->user());   // [tl! focus]
    }
}
```

The address is built from the tour's own `zones()`, `resource()` and `page()`
through the same owner every menu link comes from, so it follows the routes when
they move. It carries the tour in the query, the way a step on another page
travels, so the tour is already running when the page opens.

Three things to know before you wire one up:

- **It can answer with nothing, and the tour is still forgotten.** A tour scoped
  to nothing narrower than a zone has no single screen to be sent to, and one
  whose page this person may not open has none they may be sent to. Returning
  null from a Livewire action does nothing, so reload when there is nowhere to
  go:

  ```php
  if (($redirect = $tours->replayNow('getting-started', auth()->user())) === null) {
      $this->js('window.location.reload()');
  }

  return $redirect;
  ```

- **The address is never taken from the request.** It is computed from the tour
  and the router. The obvious implementation keeps the page's URL in a public
  Livewire property — and every public property is writable from the browser, so
  "the user can only redirect themselves" stops being true the moment somebody
  is handed a link that sets it.
- **An unknown id is ignored**, not an error — a tour may have been removed
  between the page rendering and the click.

`replay()` is the same call without the redirect, for a trigger on a screen the
tour already claims. Call either once per tour id you want to offer.

## Your Own Layout

The tour and the replay entry are both drawn through `PageChrome`, the registry
through which packages add views to a layout without knowing it. `wire-admin`'s
layout renders that registry. If you render your own layout, add the same two
loops the shell uses. This is the same step any other [page chrome](../modules/media.md#where-the-modal-comes-from)
needs:

```blade
{{-- at the end of <body> --}}
@foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views() as $view)
    @include($view)
@endforeach

{{-- inside the user menu --}}
@foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views(\NyonCode\WireCore\Foundation\View\PageChrome::USER_MENU) as $view)
    @include($view)
@endforeach
```

`@wireStackScripts` must be in the `<head>` as well, because the panel is
positioned by the controllers it delivers.

## Styling

Every part of the walkthrough carries an element hook, so you can restyle it
from your own stylesheet without publishing a view:

| Hook | Element |
| --- | --- |
| `tour-backdrop` | The dimmed layer over the page |
| `tour-highlight` | The ring drawn around the current element |
| `tour-panel` | The panel beside it |
| `tour-heading`, `tour-text` | The step's title and body |
| `tour-progress` | "Step 2 of 4" |
| `tour-next`, `tour-back`, `tour-skip` | The three buttons |
| `tour-welcome`, `tour-welcome-*` | The [welcome block](tour-welcome.md#styling), when the tour has one |

```css
[data-wire="tour-panel"] { @apply rounded-2xl shadow-2xl; }
[data-wire="tour-highlight"] { @apply ring-amber-400; }
```

The names follow the [styling hook](../start/theming.md#styling-hooks) promise:
they may be added to, and are not renamed or removed in a minor release.

## Extended Example

Three tours in one application: a first-run tour for everybody, one for the
sales team inside its own zone, and a what's-new tour for a feature shipped in
2.2.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('getting-started')                    // [tl! focus:start]
                ->zones(null)
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Everything lives here')
                        ->text('Each area of the application has an entry in this menu.')
                        ->placement('right-start'),
                    TourStep::make('global-search-trigger')
                        ->text('Search every record in the application from here.'),
                    TourStep::make('admin-user')
                        ->text('Your profile, and a way to replay this tour, are in here.')
                        ->placement('bottom-end'),
                ]),                                          // [tl! focus:end]

            Tour::make('sales-orders')                       // [tl! focus:start]
                ->permission('sales.*')
                ->zones('sales')
                ->resource('orders')
                ->page('index')
                ->sort(-10)                                  // beats getting-started for sales staff
                ->steps([
                    TourStep::make('admin-nav-item')
                        ->where('resource', 'customers')     // one entry among many
                        ->text('Customers and their open orders are one click away.')
                        ->placement('right'),
                    TourStep::make('table-filters-trigger')
                        ->text('Filter by status to see what still needs shipping.'),
                ]),                                          // [tl! focus:end]

            Tour::make('saved-views')                        // [tl! focus:start]
                ->since('2.2')                               // change this to show it again
                ->resource('orders')
                ->steps([
                    TourStep::make('table-view-save')
                        ->heading('New in 2.2')
                        ->text('Save the filters and columns you use every day, and switch back in one click.'),
                ]),                                          // [tl! focus:end]
        );
    }
}
```

A sales user who opens the orders list in the sales zone gets `sales-orders`
first, because its sort is lower, then `saved-views` on a later visit.
`getting-started` is limited to the unzoned part of the application, so it
never runs in the sales zone. Everybody else sees `getting-started` wherever
there is no zone, and `saved-views` on any orders page.

## Tour API

Scoping and content. Visibility and authorization — `->visible()`,
`->hidden()`, `->permission()`, `->authorize()`, `->authorizeUsing()` — are the
[shared component rules](../start/authorization.md#shared-component-rules), and
steps are documented on [TourStep](tour-step.md).

```php
Tour::make(string $id)                // stable id — acknowledgements are stored against it; throws on an empty one
->steps(array $steps)                 // array<TourStep>, in the order they are shown; a tour with none throws at register()
->welcome(TourWelcome $welcome)       // open with a block that asks before it points — default: none, the tour just starts   // [tl! focus:start]
->postpone(int $times)                // how many "Later"s before it records itself as seen; 0 removes the button — default: wire-core.tours.postpone (3) // [tl! focus:end]
->since(string $version)              // content version, compared for inequality — default '1'
->sort(int $sort)                     // lowest runs first when several claim a screen — default 0
->zones(?string ...$zones)            // zone names; null is the unzoned application — default: any zone
->resource(string ...$resources)      // registered resource keys — default: any, or none
->page(string ...$pages)              // 'index'|'create'|'view'|'edit'|a resource's own page — default: any
->getId(): string
->getVersion(): string
->getSort(): int
->getSteps(): array                   // array<int, TourStep>
->getPostponeLimit(): ?int            // the author's number, or null to use the configured one
->getWelcome(): ?TourWelcome
```

Registration, on the `Tours` singleton:

```php
app(Tours::class)->register(Tour ...$tours): void   // idempotent by id — a later registration of an id is ignored
app(Tours::class)->all(): array                     // array<int, Tour>, lowest sort first
app(Tours::class)->get(string $id): ?Tour
app(Tours::class)->has(string $id): bool
```

What one person has seen, on the `TourState` singleton. This is what the replay
entry calls, and what [your own trigger](#your-own-trigger) and a test call:

```php
app(TourState::class)->replay(string $tourId, ?Authenticatable $user): void                 // forget it, so it runs again; an unknown id is ignored
app(TourState::class)->replayNow(string $tourId, ?Authenticatable $user): ?RedirectResponse // and the way to the screen it runs on, or null
app(TourState::class)->acknowledge(string $tourId, ?Authenticatable $user): void            // record it as finished, the way Finish and Skip do
app(TourState::class)->postpone(string $tourId, ?Authenticatable $user): void               // "Later" — down for this session, counted; the last one acknowledges
```

## Related

- [TourStep](tour-step.md) — what a step points at, and how it is narrowed
- [TourWelcome](tour-welcome.md) — asking before it starts, and what "Later" costs
- [Testing → Testing a Tour](../start/testing.md#testing-a-tour) — asserting that one is registered, and that it runs
- [Troubleshooting → A tour never appears](../start/troubleshooting.md#a-tour-never-appears) — the checklist when nothing happens
- [Authorization](../start/authorization.md) — the shared rules a tour's visibility uses
- [Routing zones](../panels/routing.md) — where zone names come from
- [Theming → Finding a name](../start/theming.md#finding-a-name) — how to read a step's hook name off the page
