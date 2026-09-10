<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-dark.png">
  <img src="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-light.png" alt="WireStack" width="1200">
</picture>

# Wire Module Auth

The signed-out screens for [Wire](https://github.com/nyoncode) — login, password
reset, e-mail verification and the two-factor challenge — over
[Laravel Fortify](https://laravel.com/docs/fortify), plus the one-time codes
Fortify has no flow for.

```bash
composer require nyoncode/wire-module-auth
php artisan wire-module-auth:install
```

## What it owns

Seven views and one menu entry. That is the whole package, and the size is the
point.

| Screen | Fortify route | Present when |
| --- | --- | --- |
| Sign in | `login` | always |
| Create an account | `register` | `Features::registration()` |
| Request a reset link | `password.request` | `Features::resetPasswords()` |
| Set a new password | `password.reset` | `Features::resetPasswords()` |
| Confirm your address | `verification.notice` | `Features::emailVerification()` |
| Confirm your password | `password.confirm` | always |
| Two-factor challenge | `two-factor.login` | `Features::twoFactorAuthentication()` |

Plus **Sign out**, contributed into the shell's user menu through
`PageChrome::USER_MENU` — the entry every application used to hand-write into a
layout slot.

## One-time codes

A six-digit code, mailed, where Fortify has no flow of its own. Four of them, and
**every one is off until it is switched on** — an installation that says nothing
gets no new routes, no new mail and no new table:

| Flow | Switch | Screen |
| --- | --- | --- |
| Sign in with a code, no password | `codes.login` | `wire-auth.login-code` |
| A second factor by mail | `codes.second_factor` | `wire-auth.second-factor` |
| Confirm an address by code | `codes.verify_email` | `wire-auth.verify-email-code` |
| A new password from a code | `codes.reset_password` | `wire-auth.reset-code` |

Everything Fortify has an answer for keeps it: the mailed second factor is a
subclass of Fortify's own login pipe bound to the contract Fortify resolves (so
an authenticator app still wins), the reset keeps the broker's token — the code's
row carries it — and confirming by code ends in `markEmailAsVerified()` and the
`Verified` event, exactly as the signed link does. A code cannot walk past a
second factor.

The codes themselves are hashed, scoped to a purpose, expiring, attempt-counted
and single-use, behind `Contracts\OneTimeCodes` — bind your own to keep them
somewhere else. The whole of it is `docs/modules/auth.md` § One-Time Codes, and
the reasoning is ADR 0037.

## What it does not own

**Authentication.** Fortify does, and this package contains none of it: the
credential check, the login throttle keyed on address and IP, the session
regeneration that closes fixation, the reset tokens and their expiry, the signed
verification links, the TOTP window and the recovery codes. Re-implementing any
of it would buy this framework nothing and cost it every CVE.

Fortify is headless — it ships the routes and the actions and asks the
application for the markup, through seven view callbacks. Before this package a
Wire application answered them itself, or more often did not and installed the
whole panel with no way to sign in to it. That gap is exactly seven views wide,
and this is those views.

**A frame.** The screens render inside a layout they do not ship. `auto` looks
for three things in order: the layout the installer writes at
`resources/views/components/layouts/auth.blade.php` — which is where your
`@vite` line lives, and why it comes first — then the shell's
`wire-admin::auth-layout`, then nothing, which raises rather than rendering an
empty component name. Or name your own:

```php
// config/wire-module-auth.php
'layout' => 'layouts.guest',
```

**A guard.** A login screen in front of an unguarded panel is decoration. The
installer says which of the two an application has — it reads
`wire-panels.routes.middleware` and warns when `auth` is not in it.

## Changing a form

The inputs are `wire-forms` fields declared in PHP, rendered for the browser's
own POST — so adding one to a screen is a closure in a provider, not a published
view you then maintain forever:

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleAuth\Forms\AuthForm;
use NyonCode\WireModuleAuth\Forms\AuthForms;

app(AuthForms::class)->extend(
    AuthForm::Register,
    fn (array $fields): array => [...$fields, TextInput::make('company')->required()],
);
```

What the request then does with the value stays Fortify's —
`Fortify::createUsersUsing()` and its siblings. A field that binds only through
Livewire is refused at render rather than posting nothing (ADR 0036).

## Replacing one screen

Register your own in an application provider. Application providers boot after
every package's, so yours wins:

```php
// app/Providers/FortifyServiceProvider.php
public function boot(): void
{
    Fortify::loginView('auth.login');
}
```

Or turn the lot off with `'views' => false` and answer Fortify yourself.

## Why it is not a module

`wire-module-*` names a ready-made part an installer offers, and this is one. It
is **not** a `DomainModule`: a module is a manifest of resources, dashboards and
a navigation group (ADR 0029), and there is no admin page for authentication —
only the pages on the way in.
