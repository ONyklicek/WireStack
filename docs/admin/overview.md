---
order: 92
summary: The optional admin shell — a layout and a sidebar over everything already registered, shipped as its own package so installing it is the only way to get it.
---

# The Admin Shell

Everything an admin needs was already in the framework except the frame around
it: what is registered ([`Catalog`](resources.md#catalog-api)), how it groups and
orders ([`Workspace`](resources.md#navigation-and-workspace)), where each key's
page lives (`ResolvesPageUrls`), the pages themselves, the command palette, the
toasts. What no package shipped was a layout with a sidebar in it, so every
application wrote that part again.

`wire-admin` is that part, and nothing else.

```bash
composer require nyoncode/wire-admin
```

## How It Works

**It is a separate package, and that is the opt-in.** Nothing requires it; it
requires `wire-panels` and everything under it. An application that installs
`wire-panels` for its pages and its routing macro keeps getting exactly that,
with no chrome to turn off — because `composer require` is the switch.

**Installing it does not adopt it.** No provider sets `livewire.component_layout`.
The shell is a Blade component, so a page renders inside it only once your own
layout view says so — which also lets you keep your frame and use just the
sidebar.

**It reads the seams that already existed and adds no state.** The menu comes
from `Workspace`, every link from `ResolvesPageUrls` (which answers nothing at
all until a package owns routing), and the active entry from the current route
name. There is no registry here, no URL scheme and no `Panel` object.

**The zone and the active entry are read once, while the page renders.** Not per
render: inside a Livewire update `Route::currentRouteName()` is `livewire.update`,
so anything derived from it would be right on the first paint and wrong forever
after, while looking perfect. The layout renders on a full page load, which is
what makes reading it there correct rather than lucky.

## Basic Usage

Write the layout your pages name, and fill its slots:

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:head>                                    {{-- [tl! focus:start] --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </x-slot:head>

    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>

    <x-slot:user>
        <x-app-user-menu />
    </x-slot:user>                                   {{-- [tl! focus:end] --}}

    {{ $slot }}
</x-wire-admin::layout>
```

Then name it once, where your application configures Livewire:

```php
// A service provider's boot(), or config/livewire.php.
// The key is `component_layout`, not `layout` — Livewire 4 reads the former,
// and the latter fails as "No hint path defined for [layouts]".
config()->set('livewire.component_layout', 'components.layouts.admin');
```

Every full-page component — including the pages `Route::wireResources()`
registers — now renders inside the shell, with the menu beside it.

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

## The Sidebar On Its Own

An application with its own frame uses the menu without the layout:

```blade
<x-wire-admin::sidebar />
<x-wire-admin::sidebar :linked-only="true" />
```

`linkedOnly` decides what happens to a registered entry this zone does not route.
By default it stays in the menu as a row without a link — visible, not clickable
— which is the honest picture of a half-routed application. With `linkedOnly` it
is left out instead.

Both forms accept an explicit `zone` and `activeKey` when a host has already
resolved them:

```blade
<x-wire-admin::sidebar :zone="$zone" :active-key="$activeKey" />
```

## Zones

A [zone](resources.md#zones) is a route group's name prefix, and the shell needs
nothing declared to follow one: the same markup links into `admin` on an admin
page and into `business` on a business one, because the zone comes from the route
being rendered. The palette does the same, which is why its trigger lives in the
frame rather than on a page.

## Changing How It Looks

```bash
php artisan vendor:publish --tag=wire-admin::views
```

The published views are ordinary Blade. That is deliberately cruder than a
configuration API — it is the crudeness that keeps a shell from becoming a panel
framework by accretion.

## What It Does Not Do

| Not this | Because |
| --- | --- |
| A `Panel` object with a fluent API | The risk ADR 0020 named. A class holding shell configuration is what the registries below would eventually have to know about |
| A URL scheme of its own | A zone is a route group's `name()`; the router owns URLs |
| A registration path | It renders what `Catalog` already holds, and learns nothing about what kind of thing an entry is |
| Auth, tenancy or branding config | Slots, and your own middleware |

## Related

- [Resources](resources.md) — what the menu is made of, and how zones work
- [Domain Modules](modules.md) — the areas an installed package contributes to it
- [Global Search](global-search.md) — the palette the frame mounts
