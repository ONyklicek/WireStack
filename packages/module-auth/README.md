# Wire Module Auth

The signed-out screens for [Wire](https://github.com/nyoncode) — login, password
reset, e-mail verification and the two-factor challenge — over
[Laravel Fortify](https://laravel.com/docs/fortify).

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
