---
order: 50
summary: Ten levels of customization, from scoping a theme to the admin to publishing a view, and what each one costs you at upgrade time.
---

# Theming & Customization

Wire ships unstyled-but-sensible Tailwind markup with full dark-mode variants.
You customize the look at ten levels, from lightest to heaviest:

| Level | Reach | Effort |
|-------|-------|--------|
| [Scope](#scope) | Whether a theme touches your public site too | A second stylesheet |
| [Colors](#colors) | Accent and neutral palette everywhere | Tailwind config |
| [Semantic roles](#semantic-roles) | What `success`/`danger`/`warning`/`info` render as | `wire-core` config |
| [Shape](#shape) | Rounded corners, or square ones | `wire-core` config |
| [Density](#density) | How much room the interface gives itself | `wire-core` config, or a switch |
| [Styling hooks](#styling-hooks) | One kind of element, anywhere it appears | Your own CSS |
| [Render hooks](#render-hooks) | Adding something that is not there | A closure returning a view |
| [Icons](#icons) | Swap or add icons globally | `wire-core` config |
| [Per-component](#per-component-tweaks) | One field/column/action | Fluent API |
| [Override views](#overriding-views) | Any component's markup | Publish + edit Blade |

---

## Scope

Decide this before anything else, because it changes where every other level
lands. An admin panel usually wants to look different from the public site in
front of it — and the way to say so is **which stylesheet the admin loads**,
not a wrapper class or a selector.

The shell's layout does not guess an entry name; it renders whatever you put in
its `head` slot. So give the admin its own:

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/admin.css', 'resources/js/app.js'])
    </x-slot:head>

    {{ $slot }}
</x-wire-admin::layout>
```

```css
/* resources/css/admin.css — compiled for admin pages, and nowhere else */
@import "tailwindcss";
@custom-variant dark (&:where(.dark, .dark *));
@source "../../vendor/nyoncode";

@theme {
    --radius-sm: 0; --radius-md: 0; --radius-lg: 0;   /* square corners */
    --spacing: 0.2rem;                                /* tighter everywhere */
    --color-primary-500: var(--color-violet-500);
}
```

Your public pages keep loading `app.css` and see none of it. Nothing here is a
Wire feature — it is Tailwind 4 reading its own theme variables, which is why
every utility this framework writes follows without a single view being
published.

Two things to know:

- The second entry needs its own `@source "../../vendor/nyoncode"`. Without it
  Tailwind never scans the package views and the admin renders unstyled, with no
  error anywhere.
- Load one or the other on an admin page, not both.

> **Tailwind 3.** Radius and spacing are compiled values there, not variables, so
> a `@theme` block sets properties nothing reads: no error, no change. Colors and
> semantic roles work on both versions.

---

## Colors

Wire components are built on two Tailwind color scales: **`primary`** (accent —
buttons, focus rings, active states) and **`gray`** (surfaces, borders, text).
`primary` is **required** — without it, interactive elements render invisibly.

Define it in your Tailwind config as shown in
[Getting Started → Primary Color](getting-started.md#primary-color). In short:

```js
// tailwind.config.js (Tailwind 3)
const colors = require('tailwindcss/colors')

module.exports = {
    theme: {
        extend: {
            colors: { primary: colors.indigo },
        },
    },
}
```

To restyle neutrals (for example a warmer UI), point `gray` at another Tailwind
scale such as `colors.zinc` or `colors.slate` the same way.

> Because the palette is driven entirely by your Tailwind config, a custom theme
> is a config change — you do not edit package CSS.

---

## Semantic Roles

`success`, `danger`, `warning` and `info` are roles, not colours. What each one
renders as is a hue you choose, and every surface follows it — buttons, badges,
alerts, modal icons, toasts, table row tints and the audit timeline:

```php
// config/wire-core.php
'colors' => [
    'success' => 'teal',    // instead of the shipped emerald
    'danger' => 'rose',
    'warning' => 'amber',
    'info' => 'cyan',
],
```

The value is a **hue this framework already ships classes for**, never a class
name and never a CSS variable. A class named in a config file is not a file
Tailwind scans, so it would silently never compile; a variable would need
Tailwind 4 and a token your application remembers to define. A hue lands in a
`match` arm whose class strings are already literal, which is why this works on
Tailwind 3 and 4 alike.

A hue nobody recognises keeps the shipped default rather than rendering grey, so
a typo costs you the change and not the colour.

`primary` is deliberately not here: re-point `--color-primary-*` in your own
`@theme` block, as [Colors](#colors) shows.

---

## Shape

How the corners are cut. Two settings, `rounded` and `sharp`:

```php
// config/wire-core.php
'shape' => 'sharp',
```

Cards, inputs, buttons and the shell all go square together — 34 corners on a
users page, from one line.

### It is two rules, and the second is the interesting one

Zeroing the radius tokens squares everything Tailwind compiles to
`var(--radius-*)`. It squares **none** of the pills: `rounded-full` compiles to
`calc(infinity * 1px)` and reads no token at all. So badges and tags are squared
by name, through the [styling hooks](#styling-hooks) — and **avatars are
deliberately left round**.

A sharp theme that squares the faces reads as broken rather than sharp, and no
token can tell "round because it is a card" from "round because it is a face".
That distinction is why this framework has both a token layer and a hook layer,
and `wire-core::partials.shape` is where the two meet. If you want the avatars
square too, that is one more rule in your own stylesheet:

```css
[data-wire="admin-avatar"] { border-radius: 0; }
```

### No switch, on purpose

Unlike [density](#density), shape has no per-person toggle. Density is a working
preference — one person wants more rows on screen than another. Shape is
identity, and an admin that is round for one colleague and square for the next
is two products rather than one respected preference.

> **Tailwind 3.** Shape needs `--radius-*`, which Tailwind 3 does not emit. See
> [Scope](#scope).

---

## Density

How much room the interface gives itself. Two settings, `normal` and `compact`:

```php
// config/wire-core.php
'density' => 'compact',
```

That is the whole of the fixed half. A table row goes from 65px to 52px and the
fields of a create form from 550px to 446px, while the type stays exactly where
it was — font size reads its own tokens, so compact tightens the chrome and
leaves the words alone.

### It is four changes, not one

Worth knowing, because three of them are not obvious and you would spend an
afternoon rediscovering them:

- **`--spacing` drops.** Every padding, gap and size utility compiles to
  `calc(var(--spacing) * n)`, so this is what actually tightens a row.
- **The top bar and the icons are pinned back.** The same token drives `h-16`
  and `w-4`. Unpinned, compact takes the shell's top bar from 64px to 44px and
  an icon from 16px to 10px — 8px in the media manager, where it drags the
  icon-only buttons down with it. That is not density, it is a defect.
- **Form controls are addressed by name.** `@tailwindcss/forms` writes
  `padding: .5rem .75rem` on every text input, select and textarea as a literal
  in its base layer, so no token can reach the surface where density matters
  most.
- **A phone keeps its chrome.** Below `sm` the token stops at `header`, `aside`
  and `[role="dialog"]` — the top bar, the menu drawer, and every modal, sheet
  and slide-over. Unpinned, the menu handle, the bell, both switches and a
  notification row's verbs all came out 27px square, with the drawer at 202px
  instead of 288: a menu you have to aim at. Compact is a pointer's preference,
  so on a phone it tightens what you *read* and leaves what you *touch* alone.

A table has its own `->compact()`, and the two compose rather than replace each
other: an application-wide compact takes a row from 65px to 51px, a table that
also asks for `->compact()` goes to 40px. That is a deliberate "this table in
particular is dense", not a setting applied twice by mistake — but it is worth
knowing before you write both.

All of it lives in `wire-core::partials.density`, which the shell puts in the
head. **A custom layout has to include it**, the way it has to carry
`@wireStackScripts`:

```blade
@include('wire-core::partials.density')
```

### Letting people choose

The shell ships a switch beside the theme toggle — at the foot of the menu
drawer on a phone, where the top bar has no room for it — and the two settings compose
rather than fight: **your config is the default, a person's choice overrides
it.** Somebody who never touches the switch keeps what you shipped, including a
later change to it.

The choice is per browser, stored in `localStorage`, applied before the first
paint and re-applied after a `wire:navigate` — Livewire copies the fetched
document's `<html>` attributes over the live ones, so without that last part
every page visit would quietly undo the choice.

To drive it yourself:

```js
window.wireDensity.set('compact');   // choose
window.wireDensity.clear();          // back to the application's default
```

> **Tailwind 3.** Density needs `--spacing`, which Tailwind 3 does not emit — the
> rules set a property nothing reads, so the page is unchanged and nothing
> errors. See [Scope](#scope).

---

## Styling Hooks

The levels above move a value everywhere. This one restyles **one kind of
element** — the sidebar, the table toolbar, every badge — and it is what you
reach for instead of publishing a view.

Every meaningful element carries a stable name:

```html
<aside data-wire="admin-sidebar" class="…">
```

Write CSS against it in your own stylesheet. `@apply` works, dark variants and
pseudo-classes included:

```css
/* resources/css/admin.css */
[data-wire="admin-sidebar"] { @apply bg-gray-50 dark:bg-gray-950; }
[data-wire="table-row"]:hover { @apply bg-primary-50; }
[data-wire="badge"] { @apply font-mono tracking-tight; }
```

Nothing is published, so nothing forks. A future release may add markup around
the element and your rule keeps working — which is exactly what a published view
cannot promise.

### Finding a name

Open devtools and look at the element, the same way you would for any site. The
names are on the elements themselves. To read them in bulk:

```bash
grep -rho 'data-wire="[a-z-]*"' vendor/nyoncode | sort -u
```

### The anchors worth knowing

The framework carries a few hundred; these are the ones most themes start from:

| Name | Element |
|------|---------|
| `admin-sidebar`, `admin-topbar`, `admin-content` | The three regions of the shell |
| `admin-nav-item`, `admin-nav-label`, `admin-nav-badge-dot` | One entry in the menu |
| `table-search`, `table-toolbar`, `table-bulk-bar` | The chrome above a table |
| `form-field` | The wrapper every form field has |
| `badge`, `button`, `callout`, `dropdown`, `menu-item`, `section` | The shared components, anywhere they appear |

### What the name promises

- **A name is public API.** It may be added; it is not renamed or removed in a
  minor release.
- **Names describe the thing, never its appearance** — `table-toolbar`, never
  `table-grey-bar`. A name that describes how something looks would have to be
  renamed the first time it stopped looking that way.
- **The framework never writes a rule for one.** They are bare targets, so your
  rule has nothing to out-specify and never needs `!important`.
- **`data-wire` is not `data-testid`.** The test attribute exists so a test can
  find an element and has to stay free to change for testing reasons. Where both
  are present they carry the same name, deliberately — but only `data-wire` is a
  contract.

---

## Render Hooks

Every level so far changes how something *looks*. This one puts something on the
page that was not there — a badge after a page title, a note under the menu, a
button beside a table's search box.

```php
// A service provider.
use NyonCode\WireCore\Core\Plugin\RenderHook;

RenderHook::add('panels.page.header.end', fn (array $scope) => view('badges.beta', $scope));
```

The callback runs where that position sits in the markup and its result is
rendered there. Nothing is published, so nothing forks.

### The positions

| Position | Where |
|----------|-------|
| `admin.topbar.end` | After the last control in the shell's top bar |
| `admin.sidebar.end` | Below the menu, above the bottom of the sidebar |
| `panels.page.header.end` | After a page's title and description |
| `table.toolbar.end` | After the search box and filters, before the actions |

**Four is not a starter set to be completed.** A position exists because
something needed it; the framework does not add one for symmetry with a position
that already exists, and neither should a package built on it. If you need one
that is not here, that is worth saying — a name added is a name that can never
quietly go away.

Names read outside-in: `panels.page.header.end` is the panels package, its page
chrome, the header, at the end.

### What a callback may return

| Return | What happens |
|--------|--------------|
| `View` or `Htmlable` | Rendered as-is. This is the shape to use. |
| `string` | **Escaped.** Text goes in as text. |
| anything else, including `null` | Ignored — a callback with nothing to add says so by returning nothing. |

A bare string is escaped on purpose: markup has to be a view or an explicit
`HtmlString`, so nothing injects a tag from a value you did not write.

### Ordering and scope

Registration is the same one every lifecycle hook uses, so it takes the same two
options:

```php
RenderHook::add('table.toolbar.end', $render, priority: -10);       // runs first
RenderHook::add('table.toolbar.end', $render, for: 'invoices');     // this resource only
```

Callbacks run in priority order and their output concatenates in that order, so
two packages adding to one position keep a defined sequence.

A position nobody registered for costs an array lookup and renders an empty
string, which is why they can sit in markup that renders on every page.

---

## Icons

Icons resolve through the core `IconManager`, configured in
`config/wire-core.php`. The bundled Heroicons set is the **unprefixed default**;
additional sets are namespaced under a prefix and used together.

**Drop in SVGs** — point a directory at the icon paths and every SVG becomes an
icon named after its filename:

```php
// config/wire-core.php
'icons' => [
    'paths' => [
        resource_path('icons'), // resource_path('icons/cart.svg') => 'cart'
    ],
],
```

**Add an icon set** — register a set class under a prefix; its icons are then used
as `prefix:name` and render correctly even if they are stroke-based / non-20×20
(Lucide, Feather, Heroicons outline):

```php
'icons' => [
    'sets' => [
        'default' => NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class, // "pencil"
        'lucide'  => App\Wire\Icons\LucideIconSet::class,                      // "lucide:home"
    ],
],
```

**Swap the default style** — point `default_set` at another set's key to make it
the unprefixed base:

```php
'icons' => [
    'default_set' => 'lucide',  // bare names resolve against Lucide; "default:pencil" still works
    'sets' => [
        'lucide'  => App\Wire\Icons\LucideIconSet::class,
        'default' => NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class,
    ],
],
```

The bundled `DefaultIconSet` is the full Heroicons solid set. See
[Core → Foundation → Icons](../core/foundation/icons.md#icons) for the full API, the
`prefix:name` model, custom sets, and accessibility.

---

## Per-Component Tweaks

For a single field, column, or action, prefer the fluent API over overriding a
view. Every field supports arbitrary HTML attributes and extra classes:

```php
TextInput::make('sku')
    ->extraAttributes(['class' => 'font-mono tracking-wide', 'data-test' => 'sku'])
    ->size('lg');
```

`extraAttributes()` merges onto the component's outer element, so you can add
utility classes, `data-*` hooks, or ARIA attributes without touching markup. It
is on every component — fields, infolist entries, display components and
widgets — and takes a closure as readily as an array. When you need genuinely
different markup, build a [custom field](../forms/custom-fields.md) or a
[ViewField](../forms/fields/view-field.md).

---

## Overriding Views

When a tweak is structural — different layout, extra elements, a redesigned cell
— publish the package views and edit the Blade. Published views take precedence
over the package copies.

```bash
php artisan vendor:publish --tag=wire-core::views
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-table::views
php artisan vendor:publish --tag=wire-sortable::views
```

Each command copies that package's Blade files into
`resources/views/vendor/{package}/` — for example
`resources/views/vendor/wire-forms/components/text-input.blade.php`. Edit the
copy; delete it to fall back to the package default.

The same tag exists on every package that ships Blade — the panel layer, the
shell, and each installed module:

```bash
php artisan vendor:publish --tag=wire-panels::views
php artisan vendor:publish --tag=wire-admin::views

# One per installed module (wire-module-audit ships no views of its own)
php artisan vendor:publish --tag=wire-module-users::views
php artisan vendor:publish --tag=wire-module-auth::views
php artisan vendor:publish --tag=wire-module-settings::views
php artisan vendor:publish --tag=wire-module-notifications::views
php artisan vendor:publish --tag=wire-module-media::views
```

> **Publish only what you change.** Every overridden view is a file you now
> maintain across upgrades. For one-off markup, a custom field or `ViewField` is
> lower-maintenance than overriding a shared view. Re-check overridden views when
> you [upgrade](upgrade.md).

The shared field chrome (label, hint, required marker, helper text, error) lives
in `partials/field-wrapper-start.blade.php` and `field-wrapper-end.blade.php`;
override those to restyle every field's wrapper at once.

---

## Localization

All user-facing strings come from publishable translation files. The package
ships English (`en`) and Czech (`cs`).

```bash
php artisan vendor:publish --tag=wire-core::translations
php artisan vendor:publish --tag=wire-forms::translations
php artisan vendor:publish --tag=wire-table::translations
php artisan vendor:publish --tag=wire-sortable::translations
```

Files land in `lang/vendor/{package}/{locale}/`. Edit a published file to change
wording, or add a new locale directory to translate. Date and time formats for
form fields are configured separately in `config/wire-forms.php`
(`date_format`, `time_format`, `datetime_format`, `first_day_of_week`).

Every other package answers the same tag, and the modules are usually where an
application reaches first — they carry the wording on the screens its people
read every day:

```bash
php artisan vendor:publish --tag=wire-panels::translations
php artisan vendor:publish --tag=wire-admin::translations

# One per installed module
php artisan vendor:publish --tag=wire-module-users::translations
php artisan vendor:publish --tag=wire-module-auth::translations
php artisan vendor:publish --tag=wire-module-settings::translations
php artisan vendor:publish --tag=wire-module-notifications::translations
php artisan vendor:publish --tag=wire-module-audit::translations
php artisan vendor:publish --tag=wire-module-media::translations
```

An application's file is merged **over** the package's, key by key, so a file
holding only the lines you disagree with is a complete override — and the keys
you leave out keep tracking the package. A published copy of a whole file is the
easy start and the thing that quietly stops receiving new wording.

---

## See Also

- [Getting Started](getting-started.md) — Tailwind paths and primary color
- [Configuration](configuration.md) — all publishable config
- [Extending Forms](../forms/custom-fields.md) — custom fields when markup must differ
- [Upgrade](upgrade.md) — re-checking overrides after an update
