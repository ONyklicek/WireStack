---
order: 10
summary: The optional admin shell — what installing it writes, what it reads, and the one Tailwind line without which the sidebar renders as nothing at all.
---

# The Admin Shell

Everything an admin needs was already in the framework except the frame around
it: what is registered ([`Catalog`](../panels/navigation.md#catalog-api)), how it groups and
orders ([`Workspace`](../panels/navigation.md)), where each key's
page lives (`ResolvesPageUrls`), the pages themselves, the command palette, the
toasts. What no package shipped was a layout with a sidebar in it, so every
application wrote that part again.

`wire-admin` is that part, and nothing else.

## Installation

```bash
composer require nyoncode/wire-admin
php artisan wire-admin:install
```

The installer does the three things `composer require` cannot, and says what it did to each:

| Step | What it writes |
| --- | --- |
| Publishes `App\Providers\WireAdminServiceProvider` | The one line that names the layout, `config('livewire.component_layout')` |
| Writes `resources/views/components/layouts/admin.blade.php` | Your layout, naming the shell component and filling its slots |
| Registers the provider in `bootstrap/providers.php` | So the line above runs |
| Adds one `@source` line to `resources/css/app.css` | So Tailwind compiles the classes the shell's views use |
| Publishes translations | The handful of strings the sidebar shows |

Nothing is overwritten, so a second run is safe: a layout you have edited is left alone and reported as already there. An application that keeps its providers somewhere else - Laravel 10, or its own convention - gets the line to add rather than a silent success, and the rest of the install still completes.

### The One Line Tailwind Needs

That third row is the step nobody thinks of and everybody hits. Tailwind 4 finds classes by scanning your source, and it skips whatever `.gitignore` skips - which is `vendor/`, which is where every view in `wire-admin`, `wire-panels` and the modules lives. Without the line, those classes are never compiled: **the sidebar renders with no width, no colour and no spacing, and nothing anywhere reports an error.** The markup is correct and the stylesheet is silent about it.

The installer writes it for you, pointing at the vendor directory rather than at one package so a module you install tomorrow is covered without another edit:

```css
@import "tailwindcss"; /* [tl! focus:2] */
@source "../../vendor/nyoncode";
```

### If You Write Your Own Layout

The shell's layout carries two things besides the markup, and a layout of your
own has to carry them too:

```blade
@include('wire-core::partials.density')   {{-- [tl! focus:1] --}}
@include('wire-core::partials.shape')
```

They are the rules behind [Theming → Density](../start/theming.md#density) and
[Shape](../start/theming.md#shape). Without them the setting still renders its
attribute on `<html>` and nothing acts on it — a page that says it is compact
and is not, which is the same silent shape as the missing `@source` line above.

The attributes themselves come from the layout too:

```blade
<html data-density="{{ \NyonCode\WireCore\Foundation\Enums\Density::configured()->value }}"
      data-shape="{{ \NyonCode\WireCore\Foundation\Enums\Shape::configured()->value }}">
```

If your stylesheet is not `resources/css/app.css`, or does not import Tailwind, the installer says so and gives you the line rather than writing it into a file Tailwind never reads. Add it below the import, with the path adjusted to where the stylesheet lives.

Then route your pages, and they render inside the shell:

```php
// routes/web.php
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

**Installing the package is still not adopting it.** No provider inside `wire-admin` sets `livewire.component_layout` - running the installer is the application asking, which is a different act from having the package on disk. Skip the command and you still have the components, with your own frame free to use the sidebar alone.

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

## What It Does Not Do

| Not this | Because |
| --- | --- |
| A `Panel` object with a fluent API | The risk ADR 0020 named. A class holding shell configuration is what the registries below would eventually have to know about |
| A URL scheme of its own | A zone is a route group's `name()`; the router owns URLs |
| A registration path | It renders what `Catalog` already holds, and learns nothing about what kind of thing an entry is |
| Auth, tenancy or branding config | Slots, and your own middleware |

## In This Section

| Page | What it covers |
| --- | --- |
| [The Layout](layout.md) | The slots, the auth frame, and the signed-in user's corner |
| [The Sidebar](sidebar.md) | The menu component, the collapsed rail, and how a zone reaches it |
| [Branding And Theme](branding.md) | The logo, the mark, the three-state theme switch, and publishing the views |

## Related

- [Panels](../panels/overview.md) — the layer whose pages render inside this shell
- [Navigation](../panels/navigation.md) — what the menu is made of
- [Modules](../panels/modules.md) — the areas an installed package contributes to it
- [Global Search](../core/global-search.md) — the palette the frame mounts
