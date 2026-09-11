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
| Sign in with a passkey | `passkey.login` | `Features::passkeys()` |

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

In a hurry: [Switching One On, Start To Finish](#switching-one-on-start-to-finish)
is the config, the migration and the mail check, and each flow below it walks
through what the person in front of the screen actually does. The rest of this
section is what happens behind them.

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
  TOTP secret goes to Fortify's challenge, not to a code. The mailed challenge
  asks that question again for itself, rather than trusting that the pipeline
  already did: both branches write the same `login.id`, so a screen that checked
  only for a pending sign-in would let a TOTP user request a mailed code by
  posting to it directly, and an inbox would stand in for the app they set up.
- **The reset keeps the broker's token.** The mail carries a code; the code's row
  carries the token. Typing the code hands the request to Fortify's own
  `NewPasswordController` with the real token in it, so expiry, single use and
  `ResetsUserPasswords` never move. Six digits are a short-lived key to a token
  nobody can guess, not a replacement for one.
- **Verifying by code is what a signed link does.** `markEmailAsVerified()`, then
  `Illuminate\Auth\Events\Verified` — the same two lines Fortify's own controller
  runs, so anything listening hears both ways in. The link keeps working.
- **A code confirms the address it was mailed to, and no other.** The code is
  filed under the user's key so it survives somebody editing their address
  mid-flow, but surviving must not mean following: the address rides along on
  the code's payload and is compared before the flag is set. Otherwise
  requesting a code, changing the address, and typing the digits would confirm
  an address that was never sent anything.
- **A code cannot walk past a second factor.** The passwordless flow ends at
  Fortify's two-factor challenge for anyone who has one. An inbox is one factor.
  That hand-off leaves a pending sign-in behind on purpose, and the mailed
  challenge refuses it for the same reason it was written — otherwise the very
  next request would turn the refusal back into the code it refused.

**What is stored is a hash.** A code is minted, hashed the way Laravel hashes a
reset token, and filed under a *purpose* and an identifier — so a code mailed to
confirm an address cannot be typed into the sign-in challenge. Verifying consumes
it, whether it was right or one guess too many; expiry, the attempt counter on
the row and the resend window are what make six digits acceptable at all.

**The counter is incremented by the database, not by PHP.** That distinction is
the whole reason it works: the route throttle is keyed per IP, so the row's own
counter is the only bound left against guesses arriving from many addresses at
once — and a counter read, added to and written back would let a burst of
parallel guesses cost one attempt instead of one each. `codes.length` is free of
a ceiling too; the digits are drawn one at a time rather than as a single number
padded to width, which has no integer to overflow.

### Switching One On, Start To Finish

Three steps, and the third is the one people forget.

**1. Turn the flow on.** Nothing else in the config has to change; the settings
under the switches already have working defaults.

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

**2. Give the codes their table.** It is published rather than run from the
package, because it lives in your schema:

```bash
php artisan vendor:publish --tag=wire-module-auth::migrations
php artisan migrate
```

**3. Make sure mail actually leaves.** Every flow is a mail; a code that is never
delivered looks exactly like a wrong code, and nothing in the package can tell the
difference. In development the log driver is enough — the code is then in
`storage/logs/laravel.log`, which is also how you finish a flow without an inbox:

```dotenv
MAIL_MAILER=log
```

Then check what you actually have. `about` names each flow that is on, and names
the one state a switch cannot express — on, and unable to run:

```bash
php artisan about --only=wiremoduleauth
# One-time codes ..... sign-in, second factor
# One-time codes ..... second factor on, but Fortify two-factor is off
```

What each flow needs of your user model, beyond `codes.*`:

| Flow | Fortify feature | The user model must |
| --- | --- | --- |
| Sign in with a code | — | use `Notifiable` |
| A second factor by mail | `twoFactorAuthentication()` | use `Notifiable`; optionally implement `ReceivesLoginCodes` |
| Confirm an address by code | `emailVerification()` | use `Notifiable` and implement `MustVerifyEmail` |
| A new password from a code | `resetPasswords()` | be what Laravel's password broker already resets |

`Notifiable` is on Laravel's default `App\Models\User` already. A model without
it is never sent a code — no error, no mail — which is the first thing to check
when a flow does nothing at all.

**4. Say all of it on the model once.** This is every requirement above in one
class — the traits and interfaces are the whole of what the flows ask for:

```php
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NyonCode\WireModuleAuth\Contracts\ReceivesLoginCodes;

class User extends Authenticatable implements MustVerifyEmail, ReceivesLoginCodes   // [tl! focus]
{
    use HasFactory;
    use Notifiable;                 // a code can be sent at all          [tl! focus]
    use TwoFactorAuthenticatable;   // an authenticator app can win over one [tl! focus]

    protected $fillable = ['name', 'email', 'password', 'two_factor_by_mail'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_by_mail' => 'boolean',
        ];
    }

    /** Asked before every mailed second factor. Drop the interface to let config decide. */
    public function wantsLoginCode(): bool   // [tl! focus]
    {
        return $this->two_factor_by_mail;
    }
}
```

Only `Notifiable` is required for every flow; the rest are the features you
turned on.

### Signing In With A Code

One switch, and nothing else has to change:

```dotenv
WIRE_AUTH_CODE_LOGIN=true
```

What the person then does:

1. On the sign-in screen they click **Sign in with a code instead** — the link is
   drawn only where the flow is on, so it is also your proof the switch took.
2. They type their address on `/login/code` and press **Mail me a code**. The
   reply is the same whether or not the address has an account.
3. They land on `/login/code/challenge`, which names the address the code went
   to, and type the six digits. **Send it again** mails another after
   `resend_after` seconds.
4. They are signed in — through Fortify's own `LoginResponse`, so they land
   wherever a password sign-in lands.

The one branch worth knowing: somebody with a confirmed authenticator app is
*not* signed in at step 4. The code is accepted, the session stays shut, and they
are handed to Fortify's two-factor challenge — a code to an inbox is one factor,
and this must not be the way around the second.

### A Second Factor By Mail

For people with no authenticator app. `codes.second_factor` **and** Fortify's
`twoFactorAuthentication()` both have to be on:

1. They sign in on `/login` with the password they have always used.
2. Instead of the panel they get `/two-factor/code`, and a mail with a code.
   Their sign-in is held in Fortify's own `login.id` session key — the password
   *was* checked, so this is exactly the state Fortify's own challenge runs in.
3. The right code opens the session, fires Fortify's
   `ValidTwoFactorAuthenticationCodeProvided`, and lands them where Fortify lands
   a second factor. A wrong one fires `TwoFactorAuthenticationFailed` and says so
   on the field.

Anyone with a confirmed TOTP secret never sees this screen: they go to Fortify's
challenge instead, because an authenticator app is the stronger factor and they
set it up on purpose.

Both kinds of second factor fire Fortify's own events, so an audit trail that
wants to record which one was used listens once:

```php
namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function ($event): void {   // [tl! focus:start]
            // Mailed code or authenticator app: a user with no TOTP secret
            // answered the one this package sent.
            activity()->causedBy($event->user)->log(
                $event->user->two_factor_secret ? 'signed in with an app code' : 'signed in with a mailed code',
            );
        });

        Event::listen(TwoFactorAuthenticationFailed::class, fn ($event) => logger()->warning(
            'second factor refused', ['user' => $event->user->getKey()],
        ));                                                                                        // [tl! focus:end]
    }
}
```

### Confirming An Address By Code

Beside the signed link, never instead of it — for the mail client that rewrote
the URL, or a link opened on the wrong machine:

1. A signed-in, unconfirmed user is on Fortify's **Confirm your e-mail address**
   screen. With `codes.verify_email` on it also offers **Type a code instead**.
2. Pressing it mails a code and takes them to `/email/verify/code`.
3. The right code marks the address verified and fires `Verified` — the same two
   lines Fortify's own controller runs for a signed link, so a welcome mail or an
   audit entry listening for it hears both ways in.

Your user model has to implement `MustVerifyEmail`, or there is nothing to
confirm and both screens send the visitor home.

Confirming an address only *means* something where something is closed until it
happens, which is a middleware rather than a setting here:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth', 'verified'],   // [tl! focus]
],
```

### A New Password From A Code

The flow with the most moving parts and the least for you to do: turn
`codes.reset_password` on and the existing reset screens change shape.

1. The person asks for a reset on `/forgot-password`, exactly as before.
2. The mail carries a **code** instead of a link. Laravel's broker still minted
   its token; the code's row is what carries it.
3. They land on `/reset-password-code` with their address already filled in — the
   reset flow is the one that knows it, and it travels in the session rather than
   in the URL, so it stays out of your access log.
4. They type the code and the new password. The code is checked first, so a wrong
   one costs an attempt and nothing else; then Fortify's own `NewPasswordController`
   runs with the real token, and the password rules, the broker's verdict and
   `ResetsUserPasswords` are the ones you already had.

There is no token field on that screen, and that is deliberate: the token is what
the code stands for. A screen showing both would be a screen where the code is
decoration.

Two bindings are replaced while this flow is on — Laravel's
`ResetPassword::toMailUsing()` and Fortify's
`SuccessfulPasswordResetLinkRequestResponse`. If your application binds either
one, yours boots last and wins, and the codes then have no mail to travel in.

One binding has to be yours, and it is the one Fortify deliberately leaves unbound
— what a reset actually writes. `laravel/fortify`'s own installer publishes it;
an application that wired Fortify by hand supplies it, or **both** reset screens
fail on a container error rather than on anything about codes:

```php
namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResetsUserPasswords::class, ResetUserPassword::class);   // [tl! focus]
    }
}
```

### When No Code Arrives

In the order worth checking, because each one fails silently:

- **Is the flow routed at all?** `php artisan route:list --name=wire-auth` should
  list the screens for every switch that is on. Nothing there means the switch is
  off — or, for the second factor and the verification flow, that Fortify's
  matching feature is.
- **Does mail leave this application?** Anything else is guesswork until
  `Mail::raw('x', fn ($m) => $m->to('you@example.com')->subject('x'))` arrives.
- **Is a queue involved?** The code mail is deliberately not queued, but a
  `ShouldQueue` of your own from `OneTimeCodeNotification::toMailUsing()` needs a
  worker, and a code that arrives four minutes late is a code that has expired.
- **Was one just sent?** Inside `resend_after`, the resend button says so and
  mails nothing — by design, so a leaned-on button does not send five codes of
  which four are already dead.
- **Does the model use `Notifiable`?** Without it nothing is ever sent, and every
  screen still says the code is on its way.
- **Is the code simply wrong or expired?** They are the same reply on purpose.
  After `attempts` wrong guesses the code is thrown away, so the right digits stop
  working too — ask for a new one.

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

### Changing What The Code Screens Ask

The code screens are `AuthForms` like every other screen here, so the boxes are a
field you can replace rather than markup you would have to publish. `AuthForm::Code`
is one case for three screens — the passwordless challenge, the mailed second
factor and the address confirmation all render it — because an application that
makes its codes eight digits long means all three:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireModuleAuth\Forms\AuthForm;
use NyonCode\WireModuleAuth\Forms\AuthForms;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Eight boxes, in threes, to match `'length' => 8` in the config —
        // the field draws what the store mints, and the two have to agree.
        app(AuthForms::class)->extend(AuthForm::Code, fn (array $fields): array => [   // [tl! focus:start]
            OtpInput::make('code')
                ->label(__('Your code'))
                ->length(8)
                ->separator(3)
                ->numericOnly()
                ->autofocus(),
        ]);                                                                            // [tl! focus:end]

        // The address on the passwordless screen, where sign-in is by staff number.
        app(AuthForms::class)->extend(
            AuthForm::LoginCode,
            fn (array $fields): array => [...$fields, /* … */],
        );
    }
}
```

The rule every extension keeps is ADR 0036's: a field that cannot submit natively
is refused at render, by name, rather than posting nothing.

### Testing It In Your Application

The code exists in one place — the notification on its way out — so a test reads
it the way a person reads their mail:

```php
use Illuminate\Support\Facades\Notification;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;

it('signs in with a mailed code', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->post('/login/code', ['email' => 'ann@example.com'])
        ->assertRedirect(route('wire-auth.login-code.challenge'));

    Notification::assertSentTo($user, OneTimeCodeNotification::class);

    $code = Notification::sent($user, OneTimeCodeNotification::class)   // [tl! focus:start]
        ->first()->code->code;                                         // the digits, once

    $this->post('/login/code/challenge', ['code' => $code])
        ->assertRedirect(config('fortify.home'));                      // [tl! focus:end]

    expect(auth()->id())->toBe($user->getKey());
});
```

**The reset flow is the exception, and it is worth knowing why.** Its code is
minted *inside* the mail Laravel's broker sends, which is the one moment the
token exists — so `Notification::fake()` never builds that mail and never mints a
code, and a test written that way passes against a flow that did nothing. Read it
off the store instead:

```php
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

it('resets a password from a mailed code', function () {
    $issued = null;

    // A decorator over the real store: everything still hashes, expires and
    // counts attempts — this only keeps the digits the database deliberately
    // does not.
    // A closure with `use (&$issued)`, never an arrow function: `fn () =>`
    // captures by value, so the by-reference constructor parameter below would
    // bind to a copy and `$issued` would still be null at the assertion.
    app()->extend(OneTimeCodes::class, function (OneTimeCodes $codes) use (&$issued) {   // [tl! focus:start]
        return new class($codes, $issued) implements OneTimeCodes
        {
            public function __construct(private OneTimeCodes $codes, public ?OneTimeCode &$last) {}

            public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode
            {
                return $this->last = $this->codes->issue($purpose, $identifier, $payload);
            }

            public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode
            {
                return $this->codes->verify($purpose, $identifier, $code);
            }

            public function recentlyIssued(CodePurpose $purpose, string $identifier): bool
            {
                return $this->codes->recentlyIssued($purpose, $identifier);
            }

            public function invalidate(CodePurpose $purpose, string $identifier): void
            {
                $this->codes->invalidate($purpose, $identifier);
            }
        };
    });                                                                                                             // [tl! focus:end]

    User::factory()->create(['email' => 'ann@example.com']);

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $issued->code,
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertSessionHasNoErrors();
});
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
namespace App\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

class RedisOneTimeCodes implements OneTimeCodes
{
    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);   // [tl! focus:start]
        $expiresAt = now()->addMinutes(10);

        // The TTL is the expiry: nothing to sweep, and a code that outlives its
        // key cannot exist.
        Redis::setex($this->key($purpose, $identifier), 600, json_encode([
            'code' => Hash::make($code),
            'payload' => $payload,
            'attempts' => 0,
            'issued_at' => now()->timestamp,
        ]));

        return new OneTimeCode($purpose, $identifier, $code, $expiresAt, $payload);   // [tl! focus:end]
    }

    public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode
    {
        // Wrong, expired, never issued, one guess too many: all `null`, because
        // the difference is only useful to somebody guessing.
        // …
    }

    public function recentlyIssued(CodePurpose $purpose, string $identifier): bool
    {
        // …
    }

    public function invalidate(CodePurpose $purpose, string $identifier): void
    {
        Redis::del($this->key($purpose, $identifier));
    }

    private function key(CodePurpose $purpose, string $identifier): string
    {
        // The purpose is part of the address, not a label: a code mailed to
        // confirm an address must not open the sign-in challenge.
        return "wire-auth:{$purpose->value}:{$identifier}";
    }
}
```

```php
// app/Providers/AppServiceProvider.php, in register()
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

## Passkeys

A passkey is the platform's own credential — Touch ID, Windows Hello, a phone, a
security key — and signing in with one is a fingerprint instead of a password.
**Laravel ships all of it:** Fortify routes the ceremony through
`laravel/passkeys` behind `Features::passkeys()`, and `@laravel/passkeys` is the
browser client. What this framework adds is the two places a person meets it: a
button on the sign-in screen, and a card on the profile page that lists their
keys.

**Nothing here implements WebAuthn.** The challenge, the relying party, the
signature check, the credential rows and the session are the packages'; the
browser half is Laravel's own npm client, compiled into a `wire-core` bundle. A
hand-written ceremony would be a parallel implementation of a security protocol,
diverging on the first browser quirk either side learned about — so this is an
adapter, and a thin one: `wirePasskey` is a `busy` flag, a message, and where to
go afterwards.

**The button is drawn from the switch that routes the ceremony,** like every
other link on the sign-in screen. With `Features::passkeys()` off there is no
button, and with a browser that cannot do WebAuthn there is no button either —
absent beats present-and-broken, and the password form is on the same screen.

**The bundle ships per screen, not per page.** It compiles Laravel's client in,
so declaring it would put 12 kB into the `<head>` of every page of every
application for a feature most of them have off. The two surfaces that draw a
passkey control include `wire-core::partials.passkey-assets` instead, and
Livewire dedupes it to one tag.

### Switching Passkeys On

**1. The tables.** `laravel/passkeys` comes with Fortify; its migration is
published like any other:

```bash
php artisan vendor:publish --tag=passkeys-migrations
php artisan migrate
```

**2. The feature.** One line in Fortify's list, beside the ones you already have:

```php
// config/fortify.php
use Laravel\Fortify\Features;

'features' => [
    Features::resetPasswords(),
    Features::twoFactorAuthentication(['confirm' => true]),
    Features::passkeys(),   // [tl! focus]
],
```

**3. The model.** Two lines, and they are the ones whose absence is silent —
every route answers, and the key that gets registered belongs to nobody:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser   // [tl! focus]
{
    use PasskeyAuthenticatable;                             // [tl! focus]
}
```

The trait reads `name` and `email` off the model — authenticators show them in
the platform dialog and in the account picker — and falls back to the auth
identifier. `getPasskeyDisplayName()` and `getPasskeyUsername()` are where an
application that stores those elsewhere says so.

**4. The origin, if you are not on your production domain.** WebAuthn is bound to
one, and a relying party that disagrees with the address the page is served from
fails inside the browser before any of this runs:

```php
// config/passkeys.php
'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
'allowed_origins' => [config('app.url')],
```

**In local development, browse `localhost` rather than `127.0.0.1`.** WebAuthn's
secure-context exception is written for the name, and Laravel's client refuses
the address outright: *"Passkeys can't be used on 127.0.0.1. For local
development, use localhost."* Everywhere else the rule is plainer — passkeys
need HTTPS.

Nothing has to be installed with npm. The browser client is compiled into the
`wire-core` bundle the two screens emit.

### What The Person Sees

Signing in:

1. The sign-in screen shows **Sign in with a passkey** under the password button.
2. Pressing it opens the platform's own dialog — a fingerprint, a face, a PIN,
   a phone. Nothing on the page can style, rush or fake that dialog.
3. They are signed in, and land where Fortify lands a sign-in.

No address is typed first, and that is the point: a discoverable credential
already knows which account it belongs to. The e-mail field also carries the
`webauthn` autocomplete token, so browsers that support conditional UI offer
saved passkeys inside their own dropdown as soon as the field is focused — and
where they do not, the button is still there.

Managing them, on the profile page:

1. The **Passkeys** card lists what this account has, with the date each was
   added.
2. **Add a passkey** takes a name for the device — "MacBook", "work phone" — and
   opens the same platform dialog.
3. **Remove** deletes one through the package's own action, so `PasskeyDeleted`
   fires for anything listening.

The card is `wire-module-users`', beside the two-factor card, because it is the
signed-in person's own account: see [Teams and
Two-Factor](teams-and-two-factor.md#passkeys-on-the-profile-page).

### When A Passkey Does Not Work

- **No button on the sign-in screen.** Either `Features::passkeys()` is not in
  Fortify's list, or the browser cannot do WebAuthn — the control is bound to
  `x-show="supported"`, which is the client's own answer.
- **"Passkeys can't be used on 127.0.0.1."** Browse `localhost` instead, or serve
  over HTTPS.
- **The dialog opens and the sign-in fails afterwards.** The relying party and
  the origin are what to check: `passkeys.relying_party_id` must be the host the
  page is served from, and `passkeys.allowed_origins` must contain the scheme and
  port too.
- **The card says a trait is missing.** `PasskeyAuthenticatable` and
  `PasskeyUser` are not on the user model, so there is nowhere to store a key.
  This is the one failure the card reports itself, because every route answers
  without them.
- **Adding a passkey asks for a password first.** That is
  `passkeys.management_middleware` — `password.confirm` by default, which is the
  right default for an application and can be relaxed per installation.

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
