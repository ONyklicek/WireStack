---
order: 60
---

# Theming & Customization

Wire ships unstyled-but-sensible Tailwind markup with full dark-mode variants.
You customize the look at four levels, from lightest to heaviest:

| Level | Reach | Effort |
|-------|-------|--------|
| [Colors](#colors) | Accent and neutral palette everywhere | Tailwind config |
| [Icons](#icons) | Swap or add icons globally | `wire-core` config |
| [Per-component](#per-component-tweaks) | One field/column/action | Fluent API |
| [Override views](#overriding-views) | Any component's markup | Publish + edit Blade |

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
[Core → Foundation → Icons](core/foundation.md#icons) for the full API, the
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

`extraAttributes()` merges onto the rendered control, so you can add utility
classes, `data-*` hooks, or ARIA attributes without touching markup. When you
need genuinely different markup, build a [custom field](forms/custom-fields.md)
or a [ViewField](forms/fields/view-field.md).

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

---

## See Also

- [Getting Started](getting-started.md) — Tailwind paths and primary color
- [Configuration](configuration.md) — all publishable config
- [Extending Forms](forms/custom-fields.md) — custom fields when markup must differ
- [Upgrade](upgrade.md) — re-checking overrides after an update
