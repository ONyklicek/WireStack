---
order: 40
summary: The two forms of a logo, the three-state theme switch decided before the first paint, and publishing the views when configuration is not enough.
---

# Branding And Theme

Two decisions are made before the page paints — which logo and which theme — and
both are made in the head for the same reason: read either one a frame later and
the user watches it change.

## The Logo In The Rail

The brand takes two forms and both are in the document; which one shows is
`data-rail` on `<html>`, decided before the page paints along with everything
else. `logo` is drawn in the wide menu and `mark` in the rail, because a wordmark
scaled into 64 pixels is unreadable rather than small; give only a `logo` and the
rail falls back to the application's initial in a rounded square.

```php
// config/wire-admin.php
'brand' => [
    'name' => 'Acme',
    'logo' => 'images/logo.svg',        // the wide menu
    'mark' => 'images/mark.svg',        // the rail — falls back to the initial
    'logo_dark' => 'images/logo-dark.svg',
],
```

Two things this used to get wrong, both of them only visible on screen. The forms
were picked between by `x-show`, which runs *after* the first paint — so a
collapsed menu drew the wordmark, clipped to 31 pixels by the header's own
overflow, and replaced it with the square one frame later, on every load and
every `wire:navigate`. And the mark sat hard against the column's left edge,
16 pixels off the axis every icon below it sits on, because centring a 32-pixel
anchor inside itself leaves it wherever the header put it.

## The Theme Switch

Three states, not a toggle: **day, system, night**. The third is the one that
carries its weight — a two-way switch forces a choice the moment somebody touches
it and then keeps it for ever, so a laptop that dims itself in the evening stops
being followed the first time anybody presses the button. `system` has to be
somewhere you can go back to.

The vocabulary is `NyonCode\WireCore\Foundation\Enums\Theme`, so a second
surface — a settings page, another shell — renders the same three choices with
the same labels and icons without re-deciding what they are called.

Which theme applies is decided **before the page paints**, by a small script in
the head, because reading it later is the flash every dark-mode implementation is
judged by. `system` keeps listening while the page is open, so the theme changes
with the operating system rather than staying at whatever it was on load.

## Changing How It Looks

```bash
php artisan vendor:publish --tag=wire-admin::views
```

The published views are ordinary Blade. That is deliberately cruder than a
configuration API — it is the crudeness that keeps a shell from becoming a panel
framework by accretion.

## Related

- [The Sidebar](sidebar.md) — where the two logo forms are drawn
- [Theming](../start/theming.md) — the colour and icon vocabulary underneath
- [Configuration](../start/configuration.md) — `wire-admin.brand` in full
