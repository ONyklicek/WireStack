# ADR 0032: The Authentication Surface — Fortify Owns It, a Package Owns Its Screens

## Status

ACCEPTED — 2026-09-06, implemented the same day. Requested by the repo owner
("přestav i modul auth aby dával smysl").

Follows [ADR 0028](0028-optional-panel-shell.md), which put an *auth frame* in
the shell and deliberately stopped there, and
[ADR 0029](0029-modules-as-installable-packages.md), whose additive rule this
package has to live under. It adds no registration system: the seam it uses —
Fortify's seven view callbacks — has existed since Fortify shipped.

## Context

"Auth" was not a thing in this repository. It was three unconnected fragments,
and the measurement is what turned a vague "doesn't make sense" into a decision:

1. **A frame with no consumer.** `wire-admin` ships
   `<x-wire-admin::auth-layout>` — the head, the theme decision, a card. Nothing
   in the repository renders inside it. ADR 0028 §5 was explicit that it was a
   frame and not a layer, which was right; what it left unnamed was who supplies
   what goes *in* it.
2. **Two-factor, but only its second half.** `wire-module-users` owns the
   profile card that turns 2FA on, over Fortify's actions. The *challenge* — the
   screen between a correct password and the panel — belonged to nobody.
3. **A sign-out that every application wrote by hand.** `wire-module-users`
   shipped a `sign_out` translation for a route it did not own; the workbench's
   layout wrote the form, reaching into that package's translation keys from a
   file that package cannot see. The shell's `userMenu` slot made that the only
   available answer.

And under all three, the finding that decided the shape:

4. **`wire-panels.routes.middleware` defaulted to `['web']`.** An application
   that turned self-registering routes on served every resource's create, edit
   and delete screen to anybody who knew the URL. Nothing looks wrong while it
   does — no error, no warning, and `php artisan route:list` reports exactly
   what was asked for.
5. **`wire:install` offered eleven parts and no way to sign in.** Over a clean
   Laravel the one-command setup produced an admin shell, resources, a users
   module — and no login screen.

## Decision

### 1. Fortify owns authentication. This repository owns none of it.

The credential check, the login throttle keyed on address and IP, the session
regeneration that closes fixation, the reset tokens and their expiry, the signed
verification links, the TOTP window and the recovery codes stay with Laravel
Fortify. Writing them here would mean owning that surface and every CVE in it,
with no framework advantage to show.

This is the same ruling ADR 0028 §5 made, restated because the package that
follows makes it easy to drift: a package named `wire-module-auth` that answers
view callbacks is one refactor away from a package that authenticates.

### 2. `nyoncode/wire-module-auth` answers Fortify's seven view callbacks

Fortify is headless: it registers the routes and asks the application for the
markup. The gap is exactly seven views wide, and the package is those seven,
rendered in the panel's design language.

All seven are registered unconditionally. Which screens exist is
`fortify.features`' question, and a view for a feature that is off is never
routed to — gating the registrations would be a second copy of that list, able to
disagree with the first. Registration happens in **booted**, so an application
provider (which boots after every package's) can replace any one of them by
naming its own, and keep the other six.

**Rejected: Livewire screens with their own POST handling.** It reads as the
more modern option and it is the one that quietly acquires the security surface
— a component that calls `Auth::attempt()` owns throttling and fixation whether
or not it meant to. A plain form posting to Fortify's route owns neither.

**Rejected: hard-requiring `wire-admin`.** It would put a module *above* the
shell and make the documented graph a cycle of exceptions. The frame is named in
config instead, defaulting to the shell's where the shell is installed.

**Rejected: shipping a fallback frame.** It would be the same forty lines the
shell already has, diverging on the first change to either. Without a shell and
without a named layout the package raises `AuthFrameException`, which names the
config key — a sentence, rather than Blade's "unable to locate component" one
layer down.

**And the installer writes a layout, because naming the shell's frame directly is
not enough.** That frame takes the stylesheet from a `head` slot — a package
cannot know an application's Vite entry names, and this repository has refused to
guess them since ADR 0028 — so rendering straight into it produces a login page
carrying the framework's markup and **none of the application's styles**, with no
error anywhere on it. It is the same shape `wire-admin:install` already writes
for the signed-in layout: an application-owned view naming the package's frame
and filling its slots. `auto` looks for it first, the shell's second, and raises
third.

### 3. It is not a `DomainModule`, and the name is not a promise that it is

`wire-module-*` names a ready-made part an installer offers. A `Module` is a
manifest of resources, dashboards and a navigation group (ADR 0029 §1), and this
package declares none of those: there is no admin page for authentication, only
the pages on the way in. Registering an empty manifest to match the name would
put an id in the plugin list that declares nothing.

### 4. `PageChrome` grows a `USER_MENU` region, and `add()` grows a sort

The shell draws the user dropdown and leaves its *contents* to a slot, so both
entries that belong there — the profile link and the way out — were markup every
application wrote by hand. The region inverts that the same way `TOPBAR` did: the
package that owns the profile page contributes the link, the package that owns
authentication contributes the sign-out, and the shell names neither.

The sort is what registration order cannot answer. Provider order in a Laravel
application is composer's discovery order; it is not a contract, and this is the
first region two packages contribute to at once. "Sign out" above "Profile" reads
as a bug, and neither package can see the other to avoid it. Lower sorts first,
equal sorts in registration order — so a region with one contributor never has to
think about it.

**Registered on config alone, never on whether the shell has booted.** A check
for the shell at boot answers false for a shell that boots afterwards, and the
entry would be missing from a menu that exists — installed, silent, empty, which
is the failure ADR 0029 §2 spent a decision on. Whether there is a menu to draw a
row for is asked at render.

### 5. `menu-item` moves down to `wire-core`

Two packages outside the shell now need a row of the user menu, and neither
depends on `wire-admin`. The markup moved to `<x-wire::menu-item>`;
`<x-wire-admin::menu-item>` stays as a delegate, because an application's own
layout slot names it. An adapter, not a second copy — the rule
`architecture/plans/canonical-ownership-consolidation.md` already holds
everything else to.

### 6. The panel's routes require a signed-in user by default

`wire-panels.routes.middleware` becomes `['web', 'auth']`. It is the one default
in that file that is a safety decision rather than a convenience: what the key
registers is a resource's create, edit and delete screens.

Without a package answering Laravel's `login` route, `auth` redirects to a route
that does not exist. That is a loud failure and the right one; the alternative is
a quiet open door. An application whose panel is deliberately public takes `auth`
out — the default is what an application gets for saying nothing, not a rule.

The installer reports the state rather than enforcing it: routes written by hand
live in a group nothing here can read, and guessing would be worse than saying
so.

## Consequences

### Positive

- `wire:install` over a clean Laravel now produces an application someone can
  sign in to, which is the difference between a framework and a demo.
- The two-factor story has both halves: setup on the profile page (users
  module), challenge on the way in (this one).
- The user menu is filled by the packages that own its entries, so an
  application upgrading gets both without editing its layout — and the workbench
  lost fifteen lines that were a template for copying the wrong thing.
- A login screen in front of an unguarded panel is now a warning at install time
  rather than a discovery in production.

### Negative / risks

- **The middleware default is a behaviour change.** An application that enabled
  `wire-panels.routes.enabled` without publishing the config starts redirecting
  to `login`. It belongs in the upgrade notes, and it is the safe direction: the
  failure is visible, the previous one was not.
- **A second package now depends on Fortify.** `wire-module-users` detects it and
  works without it; this one requires it. The asymmetry is deliberate — there,
  two-factor is optional; here, Fortify is the engine — but it is a difference
  two neighbouring packages have to keep explaining.
- **Seven views are seven pieces of markup to keep current** with Fortify's
  request fields. They are covered by tests that go through Fortify's own routes
  rather than rendering the views directly, which is what makes a field rename
  fail loudly.
