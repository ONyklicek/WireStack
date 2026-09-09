---
order: 20
summary: The Blade component your layout names, the slots it exposes, the auth frame beside it, and where the signed-in user goes.
---

# The Layout

The shell is a Blade component, not a configuration object. Your layout view names
it and fills its slots — which is why brand, auth and chrome are markup you write
rather than a class you configure.

## Slots

| Slot | Where it lands | Typical content |
| --- | --- | --- |
| `head` | in `<head>`, before Livewire's own tags | `@vite(...)`, meta tags, a font |
| `brand` | left of the top bar | an application name, a logo |
| `topbar` | the top bar, after the palette trigger | a tenant switcher, breadcrumbs |
| `user` | the end of the top bar | an account menu, a sign-out form |
| *(default)* | the `<main>` element | the page |

Slots rather than configuration is deliberate: a class holding brand, colours and
auth is what pulls a panel builder into being, and everything one would carry is
markup you can already write.

## Signing In

The shell has an auth frame and **no authentication**: `<x-wire-admin::auth-layout>` is a centered card with the same head — the theme decision, the assets, the interaction layer — and no menu, no palette and no bell, because none of them mean anything before a user exists.

```blade
{{-- resources/views/auth/login.blade.php, rendered by Fortify or Breeze --}}
<x-wire-admin::auth-layout :title="__('Sign in')">
    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>
    <x-slot:footer><a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a></x-slot:footer>

    <form method="POST" action="{{ route('login') }}">@csrf
        {{-- your fields --}}
    </form>
</x-wire-admin::auth-layout>
```

Laravel owns the rest, and deliberately: **Fortify** (headless) or **Breeze** (scaffolding) already carry login throttling, password-reset tokens and their expiry, email verification, two-factor and session fixation. A panel that reimplements those owns a security surface without gaining a feature, so this package ships the card and not one credential. Where you *do* want the two-factor setup screen inside the panel, [the users module](../modules/teams-and-two-factor.md) supplies the card and drives Fortify's own actions — a screen over the owner, not a second owner.

What connects the two is middleware you already write:

```php
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

Per [zone](../panels/routing.md#zones) if an application has several — `can:admin` on one group, `can:business` on another — and per page through `RoutePage::make($page)->permission('users.manage')`, which becomes Laravel's own `can:` middleware. Gate answers all of it, so Spatie's roles and `nyoncode/laravel-permission-extended`'s wildcards work without this package knowing they exist.

## Who Is Signed In

The shell shows the signed-in user in the corner, with a menu. It ships **no
profile page and no sign-out route**, because it owns no auth — those arrive in
the `user-menu` slot, and `<x-wire-admin::menu-item>` is there so what you put in
it looks like the menu it sits in:

```blade
<x-slot:user-menu> <!-- [tl! focus:8] -->
    <x-wire-admin::menu-item :href="route('profile')" icon="outline:user-circle" wire:navigate>
        Profile
    </x-wire-admin::menu-item>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-wire-admin::menu-item type="submit" icon="outline:arrow-right-start-on-rectangle">
            Sign out
        </x-wire-admin::menu-item>
    </form>
</x-slot:user-menu>
```

The [users module](../modules/users.md) ships a profile page for the signed-in user,
if you want one rather than writing it.

## Related

- [The Sidebar](sidebar.md) — the menu this layout puts beside the page
- [Branding And Theme](branding.md) — the logo and the theme decision in the head
- [The Auth Module](../modules/auth.md) — ready-made screens for the frame above
- [The Users Module](../modules/users.md) — a profile page for the corner menu
