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

**The fields are `wire-forms`', the posting is the browser's.** Every input on
these screens comes from a schema declared in PHP and rendered in native-submit
mode: `name` and `value=old(…)` where a panel form would carry `wire:model`, the
errors read from the shared `$errors` bag Fortify already fills. So they are the
same fields, chrome and Alpine as the forms behind the door — the password reveal
toggle included — and signing in still works with JavaScript off, because nothing
on the critical path needs it. What it buys is [the section on
fields](#the-fields): a screen gains one without a published view.

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

'codes' => [           // one-time codes, all four off by default
    'login' => false,
    'second_factor' => false,
    'verify_email' => false,
    'reset_password' => false,
],
```

Each key takes an environment override — `WIRE_AUTH_LAYOUT`, `WIRE_AUTH_VIEWS`
and `WIRE_AUTH_USER_MENU` — so a deployment can hand the screens back, or point
them at another frame, without a second config file.

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
| Sign in with a code | `wire-auth.login-code` | `codes.login` |
| Enter your code | `wire-auth.login-code.challenge` | `codes.login` |
| A second factor by mail | `wire-auth.second-factor` | `codes.second_factor` |
| Confirm an address by code | `wire-auth.verify-email-code` | `codes.verify_email` |
| A new password from a code | `wire-auth.reset-code` | `codes.reset_password` |

Plus **Sign out**, in the user menu.

The last five are [one-time codes](#one-time-codes), and every one of them is off
until it is switched on: an installation that says nothing has no extra lines in
`route:list`.

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

## One-Time Codes

A six-digit code, mailed, typed into the same boxes as the two-factor challenge.
Four things it can stand in for, **each off until you switch it on**:

| Flow | Switch | What changes |
| --- | --- | --- |
| Sign in with a code, no password | `codes.login` | a second way in, linked from the sign-in screen |
| A second factor by mail | `codes.second_factor` | a correct password stops at a code, for people with no authenticator app |
| Confirm an address by code | `codes.verify_email` | a button on the "confirm your address" screen, beside the link that still works |
| Reset a password by code | `codes.reset_password` | the reset mail carries a code instead of a link |

**Fortify keeps every part of this it has an answer for.** It has none for a
mailed code — there is no passwordless flow in it, and its second factor verifies
TOTP against a stored secret — so the codes are the one piece of authentication
this package owns. Everything around them is still Fortify's, and the seams are
worth knowing because they are what makes the rest of your installation keep
working:

- **The second factor is a binding, not a second pipeline.** Fortify assembles
  its login pipeline from the container and resolves
  `RedirectsIfTwoFactorAuthenticatable` out of it; this package binds a subclass
  of Fortify's own class to that contract. The credential check, the `Failed`
  event, the login throttle and the `login.id` session key are inherited
  untouched, and **an authenticator app always wins** — a user with a confirmed
  TOTP secret goes to Fortify's challenge, not to a code.
- **The reset keeps the broker's token.** The mail carries a code; the code's row
  carries the token. Typing the code hands the request to Fortify's own
  `NewPasswordController` with the real token in it, so expiry, single use and
  `ResetsUserPasswords` never move. Six digits are a short-lived key to a token
  nobody can guess, not a replacement for one.
- **Verifying by code is what a signed link does.** `markEmailAsVerified()`, then
  `Illuminate\Auth\Events\Verified` — the same two lines Fortify's own controller
  runs, so anything listening hears both ways in. The link keeps working.
- **A code cannot walk past a second factor.** The passwordless flow ends at
  Fortify's two-factor challenge for anyone who has one. An inbox is one factor.

**What is stored is a hash.** A code is minted, hashed the way Laravel hashes a
reset token, and filed under a *purpose* and an identifier — so a code mailed to
confirm an address cannot be typed into the sign-in challenge. Verifying consumes
it, whether it was right or one guess too many; expiry, the attempt counter on
the row and the resend window are what make six digits acceptable at all.

### Turning One On

```php
// config/wire-module-auth.php
'codes' => [
    'login' => false,
    'second_factor' => true,    // needs Fortify's two-factor feature on [tl! focus]
    'verify_email' => false,
    'reset_password' => true,   // [tl! focus]

    'length' => 6,
    'expires' => 10,            // minutes
    'attempts' => 5,            // wrong guesses before the code is thrown away
    'resend_after' => 60,       // seconds a "send it again" button waits
    'throttle' => '6,1',        // its own limiter, not the login one
    'table' => 'wire_auth_one_time_codes',
],
```

Every key takes an environment override — `WIRE_AUTH_CODE_LOGIN`,
`WIRE_AUTH_CODE_SECOND_FACTOR`, `WIRE_AUTH_CODE_VERIFY_EMAIL`,
`WIRE_AUTH_CODE_RESET_PASSWORD`, and one per setting below them.

The codes need their table, which the installer publishes:

```bash
php artisan vendor:publish --tag=wire-module-auth::migrations
php artisan migrate
```

**The mailed second factor needs `Features::twoFactorAuthentication()` on.** The
pipe that sends the code *is* the contract Fortify only puts in its login
pipeline when that feature is enabled, so with the feature off no code is ever
sent — and nothing on the sign-in screen looks wrong. `php artisan about` says so
out loud rather than reporting the flow as off, and so does the installer.

### Who Gets A Mailed Second Factor

Config answers for everybody by default: every user without a confirmed
authenticator app. A user model that wants to decide per account implements one
method, and a package writing a column onto your `users` table is exactly what
this avoids:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireModuleAuth\Contracts\ReceivesLoginCodes;

class User extends Authenticatable implements ReceivesLoginCodes
{
    public function wantsLoginCode(): bool      // [tl! focus:start]
    {
        // A column you own, a role, a policy about staff accounts — whatever
        // the answer lives in. False here signs in with a password alone.
        return $this->two_factor_by_mail;
    }                                           // [tl! focus:end]
}
```

### The Mail

One wording per flow, in `wire-module-auth::messages.code_mail.*`, so "here is
your sign-in code" and "confirm this address" are different sentences. Change
them the way you change any other string on these screens — [The
Wording](#the-wording) — or take the whole message over:

```php
namespace App\Providers;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        OneTimeCodeNotification::toMailUsing(                       // [tl! focus:start]
            fn (mixed $notifiable, OneTimeCode $code): MailMessage => (new MailMessage)
                ->subject(__('Your code for :app', ['app' => config('app.name')]))
                ->markdown('mail.code', [
                    'code' => $code->code,           // the digits, readable here and nowhere else
                    'purpose' => $code->purpose,     // CodePurpose::Login, SecondFactor, …
                    'expiresAt' => $code->expiresAt,
                ]),
        );                                                          // [tl! focus:end]
    }
}
```

The notification is deliberately **not** queued: a code is worth nothing after
ten minutes, and a queue that is not running turns "my code never arrived" into a
bug report about the sign-in screen. An application with a working queue returns
its own `ShouldQueue` notification from that callback.

### A Store Of Your Own

The codes live behind one interface. Bind your own to keep them in Redis with a
TTL, or to hand them to a gateway that also sends an SMS — the four flows never
learn the difference:

```php
namespace NyonCode\WireModuleAuth\Contracts;

use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

interface OneTimeCodes
{
    /** @param array<string, mixed> $payload */
    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode;   // [tl! focus:start]

    public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode;        // [tl! focus:end]

    public function recentlyIssued(CodePurpose $purpose, string $identifier): bool;

    public function invalidate(CodePurpose $purpose, string $identifier): void;
}
```

Four rules an implementation has to keep, because the flows lean on them rather
than re-checking: the plain code exists only in what `issue()` returns, a code is
scoped to its purpose *and* identifier, verifying consumes, and an expired code is
indistinguishable from a wrong one — `null` for every kind of no.

```php
// config/app.php or a provider
$this->app->bind(
    NyonCode\WireModuleAuth\Contracts\OneTimeCodes::class,
    App\Auth\RedisOneTimeCodes::class,   // [tl! focus]
);
```

The value the two methods hand back is a `OneTimeCode`: `purpose`, `identifier`,
`code` (empty on the way out of `verify()`), `expiresAt`, and `payload(string
$key)` for whatever the flow carried — the reset flow's broker token rides there.

### Asking In Code

`Support\Codes` answers what this installation has, which is what the screens ask
before drawing a link to a route that may not exist:

```php
use NyonCode\WireModuleAuth\Support\Codes;

Codes::login();                   // sign in with a code, no password
Codes::secondFactor();            // and Fortify's two-factor feature is on
Codes::verifyEmail();             // and Fortify routes verification
Codes::resetPassword();           // and Fortify routes resets
Codes::any();                     // any of the four
Codes::secondFactorIsStranded();  // switched on, and Fortify's feature is off
Codes::wantedBy($user);           // this user is mailed a second factor
Codes::usesAuthenticatorApp($user);
Codes::identifierFor($user);      // or an address, normalised
```

## Customizing The Screens

Five rungs, cheapest first, and each keeps the ones below it: changing a word
does not mean owning a form, adding a field does not mean owning the markup
around it, and owning one screen does not mean owning the other six.

| To change | Reach for | What you then own |
| --- | --- | --- |
| A word, a heading, a language | `lang/vendor/wire-module-auth/` | the keys you wrote |
| The page around the card | `wire-module-auth.layout` | your own layout |
| The fields on a form | `AuthForms::extend()` | the closure you wrote |
| The markup around them | the published views | the views you kept |
| A whole screen | `Fortify::loginView()` | that one screen |

### The Wording

Every string on these screens is a key in `wire-module-auth::messages` — the
labels, the headings, the sentence under each heading, the links, "Sign out".
Laravel merges an application's file **over** the package's, key by key, so a
file holding only what you disagree with is the whole change:

```php
// lang/vendor/wire-module-auth/en/messages.php
return [
    'sign_in_heading' => 'Staff sign-in',
    'sign_in_description' => 'Accounts are issued by the office; there is no sign-up.',
    'remember_me' => 'Keep me signed in on this device',
];
```

The installer publishes both shipped locales in full, and the tag does the same
on its own. A complete copy is the easy start and a bad habit: a key you did not
change is a key that has stopped tracking the package, so trim the file to the
lines you meant. A locale the package does not ship — it ships `en` and `cs` — is
a directory beside them rather than a fork, and a key left out of it falls back
to `fallback_locale`:

```bash
php artisan vendor:publish --tag=wire-module-auth::translations
# lang/vendor/wire-module-auth/{en,cs}/messages.php — add de/, pl/, … beside them
```

### The Frame

The layout the screens render inside is one config key, and it is the change to
make before any other: it is what puts your stylesheet, your brand and your
background on all seven at once, without touching a single view. See
[Configuration](#configuration) above.

### The Fields

**The inputs are not markup any more.** Every field on these screens is a
`wire-forms` field declared in PHP, in `Forms\AuthForms`, rendered in
native-submit mode — carrying `name` and `value=old(…)` instead of `wire:model`,
so the browser posts the form exactly as it did when the markup was written by
hand. Which means adding a field to a sign-in screen is a closure in a provider
rather than a published view you then own forever:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleAuth\Forms\AuthForm;
use NyonCode\WireModuleAuth\Forms\AuthForms;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(AuthForms::class)->extend(                    // [tl! focus:start]
            AuthForm::Register,
            // The fields as declared, in; what should render, out. Append to
            // them, replace one, drop one, or return an array of your own.
            fn (array $fields): array => [
                ...$fields,
                TextInput::make('company')
                    ->label(__('Company'))
                    ->required()
                    ->autocomplete('organization'),
            ],
        );                                                // [tl! focus:end]
    }
}
```

Callbacks run in registration order, each on what the last returned, and an
application's provider boots after every package's — so this composes with a
module that adds a field of its own rather than racing it.

| `AuthForm` case | Screen | What it ships with |
| --- | --- | --- |
| `Login` | Sign in | `email` (whatever `fortify.username` names), `password`, `remember` |
| `Register` | Create an account | `name`, `email`, `password`, `password_confirmation` |
| `ForgotPassword` | Request a reset link | `email` |
| `ResetPassword` | Set a new password | `token` (hidden), `email`, `password`, `password_confirmation` |
| `ConfirmPassword` | Confirm your password | `password` |
| `TwoFactorCode` | Two-factor challenge | `code`, as six boxes |
| `TwoFactorRecovery` | Two-factor challenge | `recovery_code` |

Two cases for the last screen because it is one form posting to one URL with a
different field depending on what the person has to hand — and replacing the code
input is no reason to inherit whatever you did to the recovery one.

The identity field follows `fortify.username`: it takes that name, and it is a
`type="email"` input only where that name is `email`. A browser's format rule on
a field holding staff numbers rejects every value the application configured it
for, in the browser's own words, before anything is posted.

**Putting the input on the page is half of a new field.** What the request does
with the value is Fortify's action and always was:
`Fortify::createUsersUsing()` is where a column read at sign-up is written, and
adding a field here without telling that action about it is an input whose value
is validated by nobody and stored nowhere.

**A field the browser could not post is refused at render**, by name, with
`FormConfigurationException`. Anything needing a round-trip mid-form binds
through Livewire alone and carries no `name` — `live()` fields, a `Select`
searching on the server, `FileUpload`, `Repeater` — so on this side of the door
it would render an input that submits an empty value with no error anywhere. An
exception naming the field is the cheap version of that discovery. See
[the forms overview](../forms/overview.md#rendering).

The forms are also readable from your own views, which is what makes a
replacement screen cheap — see [below](#a-screen-of-your-own-in-the-same-frame):

```php
app(AuthForms::class)->login();               // a wire-forms Form, ready to echo
app(AuthForms::class)->resetPassword($token, $email); // what the mailed link carried
```

### The Markup

```bash
php artisan vendor:publish --tag=wire-module-auth::views
```

Nine views land in `resources/views/vendor/wire-module-auth/`, where Laravel looks
before the package's own. One is worth reading before you edit anything else:
**`screen.blade.php`** — the frame call, the heading, the sentence under it,
Fortify's session status and the error summary. All seven screens render through
it, so a change here is a change to every one of them.

What is *not* in these views any more is the fields: each screen echoes a form
object and owns only the chrome around it — the `<form>`, the `@csrf`, the submit
button, the links. So publishing is for changing that chrome, and
[the section above](#the-fields) is for changing the inputs.

**Publishing copies all nine, and a copy stops tracking the package.** A fix
released upstream reaches the package's view and not yours, with no error on the
page — last release's markup, rendering fine. So delete the files you did not
come to edit, and keep the ones you did.

### A Screen Of Your Own, In The Same Frame

`<x-wire-module-auth::screen>` is the seam between a screen and its frame, and
your own views may render inside it. Doing so keeps the layout resolution, the
heading, Fortify's session status ("a new link is on its way") and the error
summary that reports a failed sign-in where a user can see it — a login failure
is reported against `email` whichever field was wrong, so a message drawn only
under its own input is a message under the wrong one.

The fields can come from the same place the shipped screen takes them, so a view
of your own is the chrome and nothing else — or you build a
[`Form`](../forms/overview.md) yourself and call `->nativeSubmit()` on it:

```blade
{{-- resources/views/auth/login.blade.php --}}
<x-wire-module-auth::screen                                     {{-- [tl! focus:start] --}}
    :title="__('Sign in')"
    :heading="__('Staff sign-in')"
    :description="__('Use the address the office issued you.')"
>                                                               {{-- [tl! focus:end] --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        {{-- The same fields the shipped screen renders, extensions included. --}}
        {{ app(\NyonCode\WireModuleAuth\Forms\AuthForms::class)->login() }}

        <x-wire::button type="submit" class="w-full">{{ __('Sign in') }}</x-wire::button>
    </form>
</x-wire-module-auth::screen>
```

Then name it, the way the section below names any replacement:
`Fortify::loginView('auth.login')`.

What a screen may link to is Fortify's answer, not yours, and
`NyonCode\WireModuleAuth\Support\Screens` is where to ask it:

```php
use NyonCode\WireModuleAuth\Support\Screens;

Screens::canRegister();        // Features::registration()
Screens::canResetPassword();   // Features::resetPasswords()
Screens::mustVerifyEmail();    // Features::emailVerification()
Screens::hasTwoFactor();       // Features::twoFactorAuthentication()
Screens::canSignOut();         // Route::has('logout') — logout is not a feature
```

Ask before drawing the link. A "Forgot your password?" under a form whose route
Fortify never registered is a 404 an application hears about from a user.

### Replacing One Screen

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

### The Way Out

`'user_menu' => false` takes "Sign out" out of the shell's user menu — for an
application that fills [the menu's slot](../admin/layout.md#who-is-signed-in) by
hand, or one whose way out is somewhere else entirely. Your own rows arrive the
way this package's does, and the sort is what keeps a region nobody owns in a
sensible order: the users module contributes its profile link at `10`, this
package its sign-out at `100`.

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Foundation\View\PageChrome;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(PageChrome::class)->add(   // [tl! focus:start]
            'menu.support',                         // resources/views/menu/support.blade.php
            PageChrome::USER_MENU,
            sort: 50,                               // after Profile (10), before Sign out (100)
        );                                          // [tl! focus:end]
    }
}
```

Put `<x-wire::menu-item>` in that view — the component the sign-out row itself
uses — and it looks like the menu it sits in without depending on the shell that
draws it.

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
