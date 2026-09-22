# First-run tours and what's-new tours

A guided walkthrough that runs the first time a user sees a screen — a popover
anchored to a real element, a short line of text, next / back / skip — and the
same mechanism again after an upgrade, showing only what is new since the
version that user last acknowledged.

Written before any code exists, because the survey that preceded it found the
expensive half already built. This plan is mostly about **not** building things.

## The findings that shaped this

### 1. The anchors exist, and they are already public API

A tour's hardest prerequisite is a stable way to say "this element". The repo
has had one since the theming work: `@wireEl('admin-sidebar')` emits
`data-wire="admin-sidebar"`, and `scripts/hook-names.json` carries **254 names**
behind `npm run hooks:verify`, whose H3 rule is that the ledger may grow and
never shrink. `docs/start/theming.md` § Styling Hooks states the promise to
applications in as many words: *a name may be added; it is not renamed or
removed in a minor release*.

That is exactly the contract a tour needs, and it is already being kept for a
different reason. `ElementHook`'s docblock is explicit that this is **not**
`data-testid` — the two carry the same name where both are present, but testids
are allowed to change. **A tour anchors on `[data-wire]` and never on a
testid.**

Granularity is already sufficient in the places a first-run tour would point at.
`packages/admin/resources/views/partials/nav-item.blade.php:139` emits
`admin-nav-item` *plus* `data-resource="{{ $itemKey }}"`, so a step can target
one specific sidebar entry rather than "the sidebar". `tables/index.blade.php`
carries `table-toolbar`, `table-search`, `table-filters-trigger`,
`table-column-toggle`, `table-view-save` and `table-bulk-bar` on the exact
controls a table tour would introduce.

### 2. The JavaScript is already on every page

`wire-core-dropdown.js` is **declared** in `hasAssets(entries:)`
(`WireCoreServiceProvider.php:157`), which means `@wireStackScripts` renders it
into the `<head>` of every page. And that bundle already exports what a tour
popover is made of:

```
window.Alpine.magic('float',          () => floatingAnchor)   // dropdown.js:~975
window.Alpine.magic('clickedInside',  …)
```

`floatingAnchor(reference, floating, config)` is Floating UI with
`offset/flip/shift/size`, a `layerAbove()` z-index resolver that walks the
trigger's ancestors so the panel clears a modal, a viewport-aware height cap, and
re-assertion of the layer after a Livewire morph strips the inline style. Every
one of those is a bug a hand-written tour popover would have shipped with.

Desktop focus containment is `packages/core/resources/views/modals/partials/focus-trap.blade.php`
— expression-only, deliberately dependent on no bundle, included inside a root
element's opening tag. (The `x-focus-trap` *directive* in the same JS bundle is
not the one to use: it early-returns unless the viewport is below the sheet
breakpoint. It is for bottom sheets.)

**So this feature ships no new JavaScript bundle, adds no bytes to any existing
one, and needs no entry in `build:core-assets` or a committed `dist/` file.**
The same conclusion the customisable-dashboards plan reached, for the same
reason: the primitive was already paid for.

### 3. The per-user store exists, with an open bag

`Foundation\Preferences\*` — `PreferenceManager::resolve(?driver, bool
$authenticated, string $configPrefix)`, a `PreferenceDriver` contract over
`load/save/forget/views`, three drivers, and `wire_preferences (user_id,
surface_key, view, preferences json)` with a unique on the triple.

The contract's docblock says the bag is open on purpose and that a driver must
keep keys it does not recognise — *"that is what lets a surface grow a new
preference without a contract change"*. "Which tours this user has
acknowledged, and at which version" is one more surface with one more bag.

### 4. The mount point exists

`Foundation\View\PageChrome` was written for precisely this dependency
direction: a thing that must exist on every screen, contributed by a package the
shell cannot name. `packages/admin/resources/views/layout.blade.php:363` renders
whatever is registered in `BODY`; `:270` and `:341` do the same for `TOPBAR` and
`USER_MENU`. `add()` is idempotent by view name.

A tour host is the `BODY` case exactly. A "replay the tour" entry is the
`USER_MENU` case.

### 5. What does **not** exist: a version to compare against

There is no per-package version constant, no per-package `CHANGELOG.md` (only a
root one), and nothing in config that says which release the installed stack is.
A "what's new since X" feature has nothing to read.

This is the one genuinely new thing, and the plan below keeps it as small as it
can be: **the version is a string the tour author writes on the tour**, not a
package version discovered at runtime. See the decisions table.

### Nearest existing precedent

`packages/table/resources/views/tables/partials/shortcut-help-modal.blade.php`
— the `?` overlay — is the repo already explaining its own UI from server-side
data (`Table::shortcutLegend()` → `ShortcutHint` value objects → a `Modals\Html\Modal`
with `openOn`, `bodyView`, `bodyData`, opened with no Livewire roundtrip). The
tour's chrome should read like a sibling of that file.

## Decisions

| Question | Answer |
| --- | --- |
| Package | **`wire-core`**, a new L2 module `Tours/` |
| New JS bundle | **None.** `$float` and the focus-trap partial are already everywhere |
| Anchors | `[data-wire="…"]` only, optionally narrowed by a second attribute |
| A missing anchor | **Skip the step silently.** Never block, never throw |
| Who sees a tour | `HasAuthorization` — `permission()` / `authorize()` / `authorizeUsing()` |
| Where a tour runs | Zone, resource key and page kind, all from `Foundation\Routing\Zone` |
| Several tours match | The lowest `sort` runs; the rest wait for another visit |
| Mobile | **No tour below the sheet breakpoint.** Not a sheet, not a cut-out — nothing |
| A tour in the box | **None.** Every tour is the application's; the docs ship one to copy |
| Persistence | `PreferenceDriver`, one surface key `tours`, bag `{tourId: version}` |
| Version model | **An author-written string on the tour**, compared as "not equal" |
| What's-new | Not a second mechanism: a tour whose `since()` differs from the acknowledged value |
| Trigger | Client-side on mount, from state the server rendered. No roundtrip to *decide* |
| Acknowledge | One Livewire call on finish or skip. The only roundtrip |
| Mounting | `PageChrome::add('wire-core::tours.host')` |
| Opt-in | Total. No tour is registered by default; an app that registers none pays one empty `@foreach` |

### Scoping: a tour is per zone and per role, and neither is new machinery

The requirement this feature actually has to meet is that an administrator and
somebody in sales do not get the same walkthrough, and that a zoned application
gives each zone its own. Both axes already have a canonical owner, so the tour
adds vocabulary for neither.

**Who** is `Foundation\Concerns\HasAuthorization` — the trait actions, columns,
filters, fields and widgets already use. It carries `permission()`,
`authorize()` and `authorizeUsing()`, resolves through Laravel's `Gate`, and its
own docblock states the compatibility that matters here: it works with
`nyoncode/laravel-permission-extended`, **wildcards included**. So
`->permission('sales.*')` inherits wildcard matching and the super-admin gate
from the package CLAUDE.md names as the only permission owner, and `Tours/` does
not require that package or know it exists.

Using it is not a convenience, it is the rule: CLAUDE.md makes existing
Foundation concerns binding extension points, and a `->forRole('sales')` of this
module's own would be a second, weaker answer to a question already owned — one
that would miss wildcards, miss the super-admin gate, and drift the first time
either changed.

**Where** is `Foundation\Routing\Zone`, which is in core's L0 and therefore
reachable from `Tours/`. It already parses a page route name into the three
things a tour wants to match on:

```php
Zone::current()      // 'business.'  — the mount point, null in a one-zone app
Zone::currentKey()   // 'invoices'   — the registered resource
Zone::currentPage()  // 'index'      — index | create | view | edit | a custom page
```

That the zone vocabulary sits in core rather than in `wire-panels` is what makes
core ownership of tours viable at all. Had `Zone` lived in panels, a zone-aware
tour engine could not have lived below it, and the whole placement decision
below would have gone the other way.

**The trap that shapes the implementation.** `Zone`'s docblock is explicit and
was measured, not assumed: `Route::currentRouteName()` answers `livewire.update`
during a Livewire round trip, so all three readers are right on the first render
of a page and null on every request after it. The rule it states — *the
identifier travels, the thing is resolved per request* — is binding here. The
tour host reads the zone, key and page **once, in `mount()`**, into public
properties Livewire snapshots, and every later decision uses those properties.
A tour host that called `Zone::current()` while resolving an acknowledgement
would match nothing, silently, and only in production.

**Scope is not part of the stored key.** Each tour has its own id, so an
administrator's tour and a sales tour are two ids in one bag. Somebody whose
role changes has acknowledged the sales tour and not the administrator one, and
therefore sees the administrator one — which is the wanted behaviour, and it
falls out of the existing model rather than needing a rule.

### Why the version is a string the author writes

The alternative is reading an installed package version and deriving "new since"
from it. That looks more automatic and is worse in every case that matters: a
tour is not new because `wire-table` bumped a patch, and an application's own
tours have no wire version at all. It also drags in a runtime dependency on
composer metadata that nothing else here has.

So `Tour::make('tables')->since('2.2')` means *this content changed at 2.2*. The
stored value for a user is whatever `since()` said when they last acknowledged
it. They see the tour when the two differ — which covers first run (nothing
stored) and re-run after an edit (stored value is stale) with one comparison and
no ordering rules. Bumping `since()` is how an author says "show this again".

What it gives up: no "show me only steps 4 and 5, which are the new ones".
Acknowledged-or-not is per tour, not per step. Named as a limitation rather
than discovered later — a per-step ledger is a bigger bag, a migration of
meaning for everyone who already stored a string, and it can be added on top of
this without changing what is stored.

### Why a missing anchor skips rather than throws

A tour points at markup in another package, in another layer, that an
application may have styled, may have hidden by configuration, or may not render
on this screen at all. A table tour whose `table-bulk-bar` step fires on a table
with no bulk actions is not a bug in the tour.

Throwing would make every tour a liability on every upgrade. Blocking on a
missing element would strand the user mid-tour with no way forward. Skipping
means the worst upgrade outcome is a tour that got shorter, which is a thing an
author can notice and fix without anyone's screen breaking.

### Why no tour ships with the framework

Registering one for the table's own toolbar is tempting: the feature would be
visible the moment somebody installs it, and the reference implementation would
be the docs' example rather than prose.

It is still the wrong default, because it means **the framework deciding to
interrupt a user on their first page load** — and nothing else in this
repository takes that liberty. Every other surface here waits to be asked: a
modal opens on an action, the shortcut help opens on `?`, a widget appears
because a dashboard declared it. A walkthrough that starts itself is a different
kind of thing, and the decision to show one belongs to whoever knows the
audience, which is never the framework.

There is also a version of it that would be actively wrong: an application that
has replaced the toolbar, hidden the search or renamed the columns would ship a
tour describing a screen that no longer exists, and the step-skipping above
would quietly shorten it to nothing.

So the docs carry a complete, copy-ready tour and the framework registers
nothing. The cost is that the feature is invisible until somebody reads about
it, which is the same deal every other opt-in here makes.

### Why core, and what that costs

The stated rule is that a canonical shared abstraction lives in the lowest layer
that can own it. A tour anchors on admin markup, table markup and form markup at
once, so anything above core can only reach some of them. Core is also where
`ElementHook`, `PreferenceDriver`, `floatingAnchor`, the focus trap and
`PageChrome` already are — every one of this feature's dependencies.

The cost is explicit and should be paid deliberately: `Tours` has to be added to
`coreModuleLayers()` in
`packages/core/tests/Unit/Architecture/ModuleLayersTest.php` as an L2 entry. That
file's docblock says editing it *"is exactly the conversation that should
happen"*. As an L2 module `Tours/` may see `Foundation/` and `Core/` and **not**
`Actions/`, `Modals/`, `Widgets/` or the rest — which is satisfiable, because
the tour's chrome is its own Blade view and not a `Modals\Html\Modal`.

A separate `wire-module-tours` was considered and rejected: modules sit above
the line and may require the whole stack, so it would work, but it costs a
`packages/*` directory, a root `composer.json` path repository, a `phpunit.xml`,
a `composer test:module-tours`, a **100 %** coverage floor, an install command
and its own docs page — to own behaviour whose anchors all live in core, table
and admin anyway.

## The shape of the API

```php
// A service provider's boot, or an application's own.
Tours::register(
    Tour::make('sales-getting-started')
        ->since('2.2')
        ->permission('sales.*')          // HasAuthorization — Gate, wildcards, super-admin
        ->zones('sales')                 // Zone::current()
        ->page('index')                  // Zone::currentPage(); omit for any
        ->steps([
            TourStep::make('admin-sidebar')
                ->heading(__('…'))
                ->text(__('…'))
                ->placement('right-start'),

            TourStep::make('admin-nav-item')
                ->where('resource', 'orders')         // the second attribute
                ->text(__('…')),

            TourStep::make('table-search')
                ->text(__('…'))
                ->placement('bottom-start'),
        ]),

    Tour::make('admin-getting-started')
        ->since('2.2')
        ->permission('admin.*')
        ->sort(-10)                      // wins if somebody matches both
        ->steps([ /* … */ ]),
);
```

`TourStep::make()` takes the hook name, not a selector: the step cannot be
written against a class or an id, which is what keeps every step on the
contracted surface. `where()` narrows to a sibling attribute — the
`admin-nav-item` + `data-resource` case, which is the only narrowing the current
markup needs.

Every scoping method is optional and absence means "anywhere". A tour with no
`permission()`, no `zones()`, no `key()` and no `page()` is the single-audience
case, and an application that never had zones or roles writes exactly what the
first draft of this plan proposed.

`zones()` takes zone names and treats **null — an unzoned application — as a
matchable value**, spelled `->zones(null)`. Without that, a tour in the
overwhelmingly common one-zone installation could only be written by omitting
the constraint, and "this tour is for the default zone and no other" would be
inexpressible.

## Steps

### Step 1 — `Tours/` in core, headless — **DONE**

**Landed.** `Tours\{Tour, TourStep, Tours, TourLedger, TourState}` in core,
`Exceptions\TourDefinitionException`, the `wire-core.tours.preferences` config
block, the `Tours` singleton, and `'Tours' => 2` in `coreModuleLayers()`. 79
tests, every new line covered, `test:core` 3055 green and the Integration suite
70 green.

**Three things the writing changed.**

*The hook-name shape was extracted rather than copied.* `TourStep` has to know
whether an anchor is a real hook name, and that regex lived inside
`ElementHook::render()`. Copying it would have been two regexes for one
contract, disagreeing at the first edit — so `ElementHook` grew
`isValidName()`, a `selector()` counterpart and an `ATTRIBUTE` constant, and
`render()` now delegates to the first two. One owner of the shape, one owner of
the attribute name.

*`TourLedger` and `TourState` are two classes, not one.* Persistence and "which
tour now" are separate responsibilities and separately testable; the ledger's
in-memory driver in the tests is what makes the open-bag cases — another
surface's keys, a corrupted value — assertable at all.

*The stepless check sits in `Tours::register()`.* A tour is assembled a method
at a time, so it is legitimately stepless mid-build; registration is the first
moment the definition is finished and therefore the first moment it can be
judged.

**One trap found while writing the tests, worth recording:** Pest's `toThrow()`
resolves a class-string with `class_exists()`, which is **false for an
interface**, and silently falls back to matching the string against the
exception *message*. `toThrow(WireException::class)` therefore passes or fails
for a reason unrelated to the hierarchy. Asserting `WireException` needs a
`try`/`catch` and `toBeInstanceOf`.

The original step description follows.

`Tour`, `TourStep`, `Tours` (the registry), and a `TourState` that answers "which
tour, if any, should this user see on this screen" from the registry plus the
preference bag. No views, no Blade, no JS. `Tours::register()` idempotent by
tour id, like `PageChrome::add()`, for the same reason: a provider can boot
twice.

`Tour` uses `Foundation\Concerns\HasAuthorization` and
`Foundation\Concerns\HasVisibility` rather than declaring its own — see the
scoping section. The only scoping vocabulary this module writes for itself is
`zones()`, `key()`, `page()` and `sort()`, and each of those is a comparison
against a value `Foundation\Routing\Zone` already produces.

`TourState::match()` takes the zone, key and page **as arguments** rather than
calling `Zone::current()` itself. That is what makes it testable without a
request, and it is the only shape that survives the `livewire.update` trap: the
one caller that reads the route is the host's `mount()`.

Its own config prefix — `wire-core.tours.preferences` — rather than reusing
`wire-core.preferences`, because **the shipped default driver is `null`, which
stores nothing** (`config/wire-core.php:338`). A tour on the `null` driver runs
on every page load for ever. The tour prefix should default to `session` at
minimum, so the worst untouched-config behaviour is "once per session" rather
than "every time", and the docs should say `database` is what an application
actually wants.

**The gate.** `composer test:core` (core's floor is 96 % and this step is pure
PHP, so it should land at 100), `vendor/bin/pest --filter ModuleLayers` after
adding `'Tours' => 2`, `composer analyse`, `composer lint`.

### Step 2 — The host view and the step chrome — **DONE**

**Landed.** `resources/views/tours/host.blade.php`, `Tours\TourHost`, a view
composer and the `PageChrome` registration in `bootTours()`, six translation
keys in both locales, and nine hook names in the ledger (266 total) with a row
in both `theming.md` pages. 88 tests over the module, every new line covered;
`test:core` 3075, Integration 70, admin 120, panels 187, all green.

**The registration is unconditional, and that reversed the step's own plan.**
This step was written as "registered only when at least one tour exists". That
is unimplementable: provider order in Laravel is composer's discovery order, so
an application registering its tours in its own provider boots *after*
`wire-core`, and the check would answer "none" for exactly that application and
leave the chrome off every page for ever. `wire-module-users` records the same
reasoning for its user-menu entry. The question moved to render time, where it
is knowable — the view's `@if` — and an application with no tours pays one
`@include`, one empty `foreach` and nothing else. The preference store is never
touched until a tour actually claims a screen.

**A composer, not a view that resolves things.** `PageChrome` renders by name
with no data to pass, and a view reaching into the container is a view doing
PHP's job (Rendering Rule 1). `wire-module-auth` already uses `View::composer`
for the same reason, so this is the established answer rather than a new one.

**`TourHost` is the one caller that reads the route.** It is rendered by the
layout, which is the full-page render and nothing else — so `Zone::current()`
is valid there and invalid everywhere downstream, which is why
`TourState::match()` takes its three values as arguments.

**The breakpoint comes from `MobileSheet::px()`**, not a literal. A tour does
not *become* a sheet below it — it does not run at all — but it is the same
line, and two sources for it would drift.

**The highlight is our own element.** A ring positioned over the target's box,
carrying `tour-highlight`, so an application can restyle it. The target itself
is written to only with an inline `z-index` (and `position: relative` when it
was static), both restored on the way out — never a `data-wire` of ours on
somebody else's element.

**Acknowledgement is a DOM event for now.** `done(skipped)` dispatches
`wire-tour:done` with the tour id. Step 3 is what listens for it and writes to
the ledger; until then a finished tour comes back on reload, which is the
expected state between these two steps.

The original step description follows.

`resources/views/tours/host.blade.php`, registered with
`PageChrome::add('wire-core::tours.host')` from `WireCoreServiceProvider` —
and **only when at least one tour is registered**, so an application that uses
none renders nothing at all.

One Alpine component: current step index, resolve `[data-wire="…"]` (plus any
`where()` attribute), skip to the next resolvable step if it is not in the
document, `$float(anchor, panel, {placement, offset})` for positioning, the
cleanup function it returns on every step change, and the focus-trap partial
included in the panel's opening tag. Escape skips. The backdrop is a
non-interactive overlay with a cut-out; the anchored element is raised above it
rather than the overlay being clipped, which is one `z-index` instead of an SVG
mask.

New `@wireEl` names here: `tour-panel`, `tour-heading`, `tour-text`,
`tour-progress`, `tour-next`, `tour-back`, `tour-skip`, `tour-backdrop`,
`tour-highlight`. Recorded with `npm run hooks:names` and documented in **both**
`docs/start/theming.md` § Styling Hooks and `docs/cs/start/theming.md` §
Stylovací hooky, or `hooks:verify` H1/H4 fails.

**The gate.** `npm run hooks:verify`, `composer test:core`, and a first pass of
the driver from step 4.

### Step 3 — Acknowledgement — **DONE**

**Landed.** `Tours\TourAcknowledgement` and `Tours\TourReplay` with their views,
`TourState::claiming()` and `TourHost::replayable()`, the `USER_MENU`
registration, and a `tour_replay` string in both locales. 99 tests over the
module, all nine classes at 100 %; `test:core` 3086, Integration 70,
module-auth 260, admin 120, all green.

**`acknowledge()` takes no arguments, and that is a security property.** A
public Livewire method is called by the browser, whenever it likes, with
whatever it likes. The tour being acknowledged is the one the component was
mounted with, held server-side across the round trip — a method that took an id
would let any page acknowledge any tour for the person looking at it. Pinned by
a test that calls it *with* an id and asserts the other tour is untouched.

**Two components rather than one, because of `wire:ignore`.** The chrome needs
it, so a morph cannot strip the inline positioning Floating UI wrote onto the
panel — and a component cannot both be ignored by Livewire and call it. Splitting
them lets each have what it needs, and has the side benefit that the walkthrough
runs identically with nothing listening: without the acknowledgement element it
still opens, steps and closes; it just forgets afterwards.

**The replay does not redirect, and the first draft's version was an open
redirect.** The obvious implementation captures the page URL at mount and
redirects to it afterwards — and that property is *writable from the browser*,
because every public Livewire property is. "The user can only redirect
themselves" stops being true the moment somebody is handed a link that sets it.
Validating it would have worked; not having it is better. The page that needs
re-rendering is the one the browser is already on, so `replay()` dispatches
`wire-tour:forgotten` and the browser reloads itself. A test asserts the
component's only public property is the tour id.

**`claiming()` is a second resolver, not a flag.** The replay entry is for the
tour that has *been* seen, which is exactly the one `match()` has stopped
answering with. A boolean argument would have made every call site restate which
of the two questions it meant — "should I interrupt this person" and "should I
offer them a way back in" are not the same question.

**A tripwire in `wire-module-auth` fired, and it was asserting the wrong
thing.** `ProviderTest` pinned the user menu's exact contents to prove "Sign out"
sorts last. That made it a test of *who else* contributes to the menu, so it
failed the first time a third package added a row while catching nothing wrong.
It now asserts the position — `end($views)` — which is the contract it was
written for.

The original step description follows.

One Livewire method that writes `{tourId: version}` into the bag and nothing
else. Called on finish and on skip — the same call, because a user who skipped
has decided about this tour just as firmly as one who finished, and a tour that
reappears after a skip is the worst version of this feature.

A "replay" entry contributed to `PageChrome::USER_MENU` forgets one tour's key
and re-dispatches. That is the whole of the manual trigger.

**The gate.** `composer test:core`, plus a test that a guest never writes (the
guest driver is `session`; the tour must not throw for an unauthenticated user
on a public page).

### Step 4 — The browser driver — **DONE**

**Landed.** `workbench/scripts/verify-tour.mjs`, 18 checks, and three tours
registered in `WorkbenchServiceProvider::bootTours()` to drive it against.

**It found a real defect in step 2 immediately.** The progress counter read off
`steps`, so a tour with a step this screen does not have counted it and then
skipped it — somebody would watch "1 of 3" become "3 of 3". The chrome now
builds a **plan** at `start()` (the indices whose element was actually on the
page) and a `cursor` walks it, so the counter says what the tour will really
show. `seek()` still re-checks the document as it walks, because the page keeps
moving underneath the plan; a step that disappears between one click and the
next is stepped over rather than pinned to nothing.

This is the whole argument for the driver existing: every PHP test in steps 1–3
passed against the wrong counter, and none of them could have seen it.

**The workbench tours are behind a cookie, and that is not fussiness.** A tour
opens itself and draws a backdrop over the page, so registering one
unconditionally would break every other driver that visits that screen — eleven
of them use the invoices pages — and would break them by covering the control
they came to click, which reads as the feature under test being broken rather
than the workbench. `bootTours()` returns early unless `wire-tour-demo` is set,
and the driver sets it over CDP.

**Three traps worth keeping, all of which cost a wrong verdict before they were
understood:**

- **`??` precedence inside `eval_()`.** An interpolated
  `document.querySelector(…)?.innerText ?? ''` parses as
  `innerText ?? ('' .includes(…) && …)` when dropped into a larger expression —
  returning the text instead of a boolean, or failing outright with
  "Unexpected token '&&'". The first form reported a *passing* feature as a
  failure with the correct text sitting in the detail column. The helpers are
  parenthesised now.
- **A session cookie is HttpOnly, so `document.cookie` cannot clear it.** The
  page reports success, the session survives, and the tour stays acknowledged —
  which read as "a tour does not run on a wide viewport". `Network.clearBrowserCookies`
  is the one that works.
- **`request()->cookie()` answers null at provider boot**, with the cookie
  demonstrably sent. `$_COOKIE` is populated before PHP dispatches anything and
  is what a gate this early has to read. Measured, not theorised.

**What the 18 checks hold.** That the tour opens on a screen it claims and is
anchored to the element its step names (a relationship between two rectangles,
not coordinates, so the sidebar's width stays free to change); that the ring is
drawn over that element; that an unreachable step is neither shown nor counted;
that Next walks over it; that Escape ends the tour and hands the element its own
`z-index` back; that a finished tour does not return on a reload or after a
`wire:navigate` visit; that a zone-scoped tour runs in its zone and nowhere
else; that a permission-gated tour never runs; and that nothing starts below the
sheet breakpoint, including a tour already running when the viewport crosses
down.

The original step description follows.

`workbench/scripts/verify-tour.mjs`. This is the **only** gate over any of the
behaviour above: Pest sees the host view's markup and nothing about whether the
panel landed next to the anchor.

What it has to cover: the panel positions against the right element; a step
whose anchor is absent is skipped rather than hanging; Escape skips the tour;
finishing writes and the tour does not reappear on reload; `wire:navigate` to
another page does not resurrect a finished tour. Poll for state — never a fixed
sleep, per the workbench convention.

Two of the decisions above are only real if this driver holds them:

- **Nothing below the breakpoint.** Resize to 375 px and assert no tour starts,
  then resize down while one is running and assert it ends. A decision that no
  test asserts is a decision that survives until the first person who thinks a
  bottom sheet would be nice.
- **Scope.** Sign in as two users with different permissions and assert each
  gets their own tour and neither gets the other's. This is the one part of the
  feature where being wrong is worse than being broken — a sales walkthrough
  shown to somebody without the permission is a hint about a screen they cannot
  reach.

**The gate.** `npm run verify:drivers -- tour`, then the full
`npm run verify:drivers`.

### Step 5 — Docs and the boost mirror — **DONE**

**Landed.** Two class reference pages rather than one guide, in both locales —
`docs/core/tours.md` (`Tour`) and `docs/core/tour-step.md` (`TourStep`) — a row
for each in the core overview's page table and `Tours/` in its layer table, the
two pages mirrored into the boost corpus, and a `### Tours` section in the
`wire-core` guideline. `docs:check`, `docs:standard`, `docs:api`,
`docs:examples` and `boost:check-docs` green; `test:core` 3086, `test:boost` 255.

**The full driver sweep: 126 of 129 green.** The three reds were checked one by
one, not waved through. `density` passes alone (16/16) and was killed by the
sweep's timeout under load. `inactive-rows` and `grid-spans` fail alone too —
and fail identically with `bootTours()` switched off in the core provider, which
is the only way this work reaches a driver that does not set the tour cookie.
So neither is this feature's: `grid-spans` is an untracked driver for widget
work in progress, and `inactive-rows` reports a different count of inactive rows
from one run to the next (4, then 3), which reads as workbench data left behind
by an earlier aborted run rather than as a regression. `composer workbench:clean`
is the reset for that.

**Two reference pages, because the API gate only checks what it can bind.**
`verify-api-docs.php` treats a page as a class's reference when its H1 is the
class name and the class is imported, and then fails on any declared fluent
setter the page leaves out. A single guide would have documented both classes
and been checked for neither. As two pages, a method added to `Tour` or
`TourStep` without documentation breaks `docs:api`.

**Writing the docs found a second defect in the chrome, and the driver now
holds it.** The example wanted a step on `table-bulk-bar`, described as absent
until rows are selected. It is not absent: it is rendered and hidden with
`x-show`, like a great deal of this framework's markup. `querySelector` finds
such an element happily, so the step was neither skipped nor counted correctly,
and the panel would have been pinned to a box of zero size. "On the page" became
"showing": `shown()` requires `getClientRects().length > 0`, the same test the
focus trap uses. The workbench tour gained a step on `admin-sidebar-overlay`
(rendered on every page, hidden on a desktop), and the driver asserts the
counter never says 3. With the fix reverted it reads "Step 1 of 3" and fails —
checked, not assumed. 19 checks now.

**Two claims were removed rather than documented.** `TourStep::placement()`'s
docblock said Floating UI falls back to `bottom` for an unknown value, which was
never measured; it now says only what the code does (an empty value becomes
`bottom`, anything else passes through). And the first draft of the page said
`visible()` must not read the route because of the Livewire round trip — but
`visible()` is only ever evaluated during the page render. It says that instead,
and that it can run twice per render because the replay entry asks the same
question.

The original step description follows.

`docs/core/tours.md` + `docs/cs/core/tours.md`, structurally identical, under
`AI_DOCS_STANDARD.md`: mechanism before signatures, a complete typed fluent API
section for `Tour` and `TourStep`, an extended in-context example with
Torchlight `[tl! focus]` spotlights. The theming-hook names from step 2 in both
locales' theming pages.

`packages/boost/resources/boost/{docs,guidelines,skills}` is a new public
surface's obligation, verified by `composer boost:check-docs`.

**The gate.** `npm run docs:check`, `docs:standard`, `docs:api`,
`docs:examples`, `composer boost:check-docs`.

## Limitations, named now

- **No chrome, no tour.** `PageChrome` is rendered by `wire-admin`'s layout. An
  application rendering its own layout gets no tour until it adds the one
  `@foreach` the docs already describe for the media picker. This is the same
  limitation every chrome contributor has, not a new one — but a tour is the
  first one whose absence is silent rather than visibly broken.
- **Per tour, not per step.** See the version decision above.
- **A hidden anchor is a skipped step.** An element behind a collapsed sidebar
  or a closed dropdown is not in the layout, so the step is skipped. Making a
  tour *open* things to reach them means the tour driving other components'
  state, which is a much larger contract than pointing at them.
- **One tour at a time.** If two registered tours both match a screen — somebody
  holding both `admin.*` and `sales.*`, on a page both tours claim — the lowest
  `sort` wins and the other waits for the next visit. Queuing two walkthroughs
  back to back is worse for a user than showing one.

  Deliberately `sort` rather than "the most specific match wins". Specificity
  across three independent axes has no obvious ordering — is a zone constraint
  narrower than a permission one? — so it would be a rule nobody could predict
  from the outside. An integer is a rule an author can read off the page.
- **~~Nothing runs on a phone.~~ Revised: a phone docks the panel.** What
  follows was the first decision, kept because its reasoning is what the
  revision answers. The panel now docks to the bottom of the screen below the
  breakpoint, the highlight ring stays on the element, and every step scrolls
  its element into the room between the top bar and the panel, so a phone gets
  a tour that points rather than a stack of captions. What a phone does not show
  — the sidebar drawer — is skipped by the ordinary missing-element rule, and
  the counter says so. The same change let a step live on another page
  (`TourStep::on()`), carried there in the query string and resumed only for an
  unfinished tour, in its own zone, at a step that is really on that page; a
  step whose page's route refuses the person (`AuthorizesUrls`, answered by
  panels' `RouteAccess`) gets no address and is skipped. Each step shown is
  recorded too, so a tour left halfway reopens where it was left — which
  revises "seen is only ever written on finish or skip" below: *seen* still
  is, and *reached* is a separate, version-stamped entry those two clear. The
  first decision read: below the sheet breakpoint (639.98 px, the number
  the rest of the stack already uses) no tour starts, and one already running
  ends if the viewport crosses down. `floatingAnchor`'s `sheetOnMobile` would
  have made the panel a bottom sheet, but a sheet cannot point at anything — and
  a highlight cut-out on a 375 px screen, where the sidebar is a drawer and the
  toolbar has collapsed, would be pointing at controls that are not on the page.
  A tour that silently became a stack of captions is worse than no tour, so this
  is a decision rather than a gap to fill later.

  Consequence to keep in mind: a user whose first visit is on a phone has not
  seen the tour and has not acknowledged it, so it waits for them on a desktop.
  That is the wanted behaviour and it needs no extra state — "seen" is only ever
  written on finish or skip.
- **The route is read once, on the page's first render.** `Zone::current()` and
  its siblings answer `livewire.update` during a round trip, so a tour cannot be
  re-scoped mid-page. A screen that changes what it is showing without a
  navigation shows the tour it matched when it loaded, or none.
- **The default driver stores nothing.** Mitigated by the tour's own config
  prefix defaulting to `session`, but an application that wants "once, ever"
  must set `database` and run the migration. The docs page has to say so
  plainly.

## Open questions

1. **Is a zone plus a resource key enough to say where a tour runs?** The three
   `Zone` readers cover every *routed* page, which is every panel page. They say
   nothing about a Livewire component an application mounts on a route of its
   own, outside `wire.{key}.{page}` — there `Zone::currentKey()` is null and the
   only available constraint is the zone. If that turns out to matter,
   `visible(Closure)` from `HasVisibility` is already the escape hatch, and the
   question becomes whether it deserves something better.

Mobile and a framework-shipped tour were the other two, and both are settled:
no tour below the sheet breakpoint, and nothing registered in the box. See the
decisions table and the two sections above it.
