# Wire Admin

The optional admin shell for [Wire](https://github.com/nyoncode): a layout and a
sidebar over everything an application already registered.

```bash
composer require nyoncode/wire-admin
```

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>
    <x-slot:user><x-app-user-menu /></x-slot:user>

    {{ $slot }}
</x-wire-admin::layout>
```

```php
config()->set('livewire.component_layout', 'components.layouts.admin');
```

Every full-page component — the pages `Route::wireResources()` registers
included — now renders inside the shell, with the menu beside it.

## Why a package of its own

Three ADRs (0020, 0026, 0027) held that the owner layer holds no shell, and this
does not change that: `wire-panels` still ships pages and routing and no chrome.
A composer boundary is the only opt-in nobody can acquire by accident — an
application that wants the pages and its own frame simply does not install this.

Installing it is still not adopting it. Nothing here sets
`livewire.component_layout`; a page renders inside the shell only once your own
layout says so, and the sidebar works alone inside a frame you wrote.

## What it reads

| It needs | It asks |
| --- | --- |
| what is registered | `Catalog`, through `Workspace` |
| how it groups, orders and reads | `Workspace::navigation()` |
| where a key's page lives | `ResolvesPageUrls` — nothing at all until a package owns routing |
| which zone, which entry is active | the current route name, read once while the page renders |

No registry, no URL scheme, no `Panel` object. An entry this zone does not route
keeps its row and loses its link, which is the honest picture of a half-routed
application; `:linked-only="true"` drops those rows instead.

## Documentation

Full docs: [`docs/core/admin-shell.md`](../../docs/core/admin-shell.md)
([česky](../../docs/cs/core/admin-shell.md)).

## License

MIT.
