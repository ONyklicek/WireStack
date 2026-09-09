---
order: 40
summary: Login, password reset, e-mail verification and the two-factor challenge, in the panel's design language — the screens Laravel Fortify asks the application for.
---

# The Auth Module

The screens on the way in. Install it and an application has a sign-in page that
looks like the panel behind it, a way to reset a password, a way to confirm an
address, the two-factor challenge, and a **Sign out** entry in the user menu.

```bash
composer require nyoncode/wire-module-auth
php artisan wire-module-auth:install
```

It contains no authentication. Laravel Fortify does, and that division is the
whole design of this package.

## How It Works

**Fortify owns the security; this owns the markup.** The credential check, the
login throttle keyed on address and IP, the session regeneration that closes
fixation, the reset tokens and their expiry, the signed verification links, the
TOTP window and the recovery codes are a security surface with a maintained
owner. A panel that re-implemented them would own that surface and gain no
feature for it.

Fortify is *headless*: it registers the routes and the actions, and asks the
application for the views through seven callbacks — `Fortify::loginView()` and
its six siblings. Before this package a Wire application answered them itself,
or more often did not, and installed the whole panel with no way to sign in to
it. That gap is exactly seven views wide, and this package is those views.

**All seven are answered, unconditionally.** Which screens *exist* is Fortify's
question, not this package's: registration, password reset, e-mail verification
and two-factor are entries in `fortify.features`, and a view for a feature that
is off is never routed to. Gating the registrations here would be a second copy
of that list, able to disagree with the first.

**Application providers boot last, so yours wins.** Naming a view of your own in
an application provider replaces one of these without turning the rest off —
package providers boot before application ones, so the last
`Fortify::loginView()` is the application's.

**The links are drawn from the switches that create the routes.** The "Forgot
your password?" link appears only where `Features::resetPasswords()` is on, and
the registration link only where `Features::registration()` is. A link to a route
Fortify never registered is a 404 an application finds out about from a user.

**The frame is named, never shipped.** The screens render inside a layout this
package does not own. Two frames would be the same forty lines twice, diverging
on the first change to either — so `auto` asks three questions in order:

1. **Your own layout**, at `resources/views/components/layouts/auth.blade.php`,
   which the installer writes. It comes first because the shell's frame takes
   your stylesheet from a `head` slot — a package cannot know your Vite entry
   names — so rendering straight into the shell's frame gives a login page with
   the framework's markup and **none of your styles**, and no error on it.
2. **The shell's**, `wire-admin::auth-layout`: the head, the theme decision
   before the first paint, and a card, with none of the navigation. This is what
   an application that has the shell and never ran the installer gets — styling
   missing, everything else right.
3. **Nothing**, which raises `AuthFrameException` rather than rendering an empty
   component name.

**Sign out is a row in the chrome, not a line in your layout.** It is contributed
into `PageChrome::USER_MENU`, the region the shell renders inside the user
dropdown, and it posts to Fortify's own logout route. The users module puts the
profile link in the same region, sorted above it. Before that region existed,
both were markup every application wrote by hand into a layout slot.

**A login screen in front of an unguarded panel is decoration.** The installer
says which of the two an application has: it reads
`wire-panels.routes.middleware` and warns when `auth` is not in it. Routes you
write yourself get the same reminder, because nothing here can read which group
they are in.

## Configuration

```php
// config/wire-module-auth.php
'layout' => 'auto',    // 'auto' uses the shell's frame where the shell is installed [tl! focus]

'views' => true,       // answer Fortify's seven view callbacks

'user_menu' => true,   // put "Sign out" in the shell's user menu
```

`auto` finds the layout the installer wrote first, then the shell's. With
neither, rendering a screen raises `AuthFrameException` — a sentence naming this
key, rather than Blade's "unable to locate component" one layer down. An
application with a frame of its own names it, as a **component**, not a view:

```php
'layout' => 'layouts.guest',   // resources/views/components/layouts/guest.blade.php
```

What the installer writes is an ordinary layout of yours, and yours to edit:

```blade
{{-- resources/views/components/layouts/auth.blade.php --}}
@props(['title' => null])

<x-wire-admin::auth-layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])   {{-- [tl! focus] --}}
    </x-slot:head>

    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>

    {{ $slot }}
</x-wire-admin::auth-layout>
```

## What You Get

| Screen | Route | Present when |
| --- | --- | --- |
| Sign in | `login` | always |
| Create an account | `register` | `Features::registration()` |
| Request a reset link | `password.request` | `Features::resetPasswords()` |
| Set a new password | `password.reset` | `Features::resetPasswords()` |
| Confirm your address | `verification.notice` | `Features::emailVerification()` |
| Confirm your password | `password.confirm` | always |
| Two-factor challenge | `two-factor.login` | `Features::twoFactorAuthentication()` |

Plus **Sign out**, in the user menu.

## Turning The Features On

Fortify's own configuration decides which screens exist. This is the whole of
what an application writes to get a panel with registration closed, resets open
and a second factor:

```php
// config/fortify.php
use Laravel\Fortify\Features;

'features' => [
    // No self-service accounts: an admin panel takes its users from  [tl! focus:start]
    // the users module, not from a public form.
    // Features::registration(),

    Features::resetPasswords(),
    Features::emailVerification(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        // Fortify writes the secret when the QR code is generated, so
        // without this a user who opened the panel and walked away counts
        // as protected. Keep it on.
        'confirm' => true,
        'confirmPassword' => true,
    ]),                                                               // [tl! focus:end]
],
```

The two-factor *setup* — the QR code, the recovery codes, the card that turns it
on — belongs to the users module's profile page. See
[Teams and Two-Factor](teams-and-two-factor.md). This package owns only the
challenge on the way in.

## Replacing One Screen

Register your own view in an application provider. Application providers boot
after every package's, so yours is the one that stands:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only this one. The other six stay as the module registered  [tl! focus:start]
        // them, so replacing the login screen does not mean owning the
        // reset flow and the two-factor challenge as well.
        Fortify::loginView('auth.login');                           // [tl! focus:end]
    }
}
```

Or hand all seven back with `'views' => false`.

## Guarding The Panel

The routes `wire-panels` registers require a signed-in user by default:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],   // the default [tl! focus]
],
```

Routes you write yourself take the same group arguments — the macro registers
inside whatever group it is called in:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])   // [tl! focus]
    ->prefix('admin')
    ->group(function (): void {
        Route::wireResources();
    });
```

`auth` sends an unauthenticated visitor to the route named `login`, which is the
route this package's first screen answers.

## What It Does Not Do

**Authorization.** Being signed in is not being allowed. Every check in this
stack is `Gate::allows()`, and who may see which resource is
[Authorization](../start/authorization.md).

**The profile page.** The signed-in user's own account — their name, their
photo, their password, the two-factor card — belongs to
[the users module](users.md).

**A `DomainModule`.** `wire-module-*` names a ready-made part an installer
offers, and this is one; it is not a [module](../panels/modules.md) in the manifest sense.
A module names resources, dashboards and a navigation group, and there is no
admin page for authentication — only the pages on the way in.

## Related

- [Installation](../start/installation.md) — `wire:install`, which offers this beside the rest
- [The Users Module](users.md) — the profile page, and where two-factor is set up
- [Teams and Two-Factor](teams-and-two-factor.md) — the four lines that switch Fortify's 2FA on
- [The Admin Shell](../admin/overview.md) — the frame these screens render in, and the user menu they fill
- [Authorization](../start/authorization.md) — what a signed-in user is allowed to reach
