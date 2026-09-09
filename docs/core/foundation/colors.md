---
order: 30
summary: "The one resolver every coloured surface goes through — the vocabulary, the fallbacks, and why a badge and a button agree."
---

# Colors

A colour in this framework is a **semantic name** — `success`, `danger`, a
Tailwind palette name — resolved to utility classes by one owner. A badge, a
button, a toast and a chart item all ask it, which is why they cannot drift, and
why a new surface gets the whole palette for free.

## Colors

`->color()` accepts the **complete Tailwind palette** on every surface. Two vocabularies
resolve through the same canonical map:

<div class="wire-swatches"><p class="wire-swatch-group">Semantic roles</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: var(--primary)"></span><span class="wire-swatch__name">primary</span><span class="wire-swatch__alias">your accent</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6b7280"></span><span class="wire-swatch__name">gray</span><span class="wire-swatch__alias">secondary</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #10b981"></span><span class="wire-swatch__name">success</span><span class="wire-swatch__alias">emerald</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">danger</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f59e0b"></span><span class="wire-swatch__name">warning</span><span class="wire-swatch__alias">amber</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">info</span></div></div><p class="wire-swatch-group">Raw hue families</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #3b82f6"></span><span class="wire-swatch__name">blue</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #22c55e"></span><span class="wire-swatch__name">green</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">red</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #eab308"></span><span class="wire-swatch__name">yellow</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">cyan</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #64748b"></span><span class="wire-swatch__name">slate</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #71717a"></span><span class="wire-swatch__name">zinc</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #737373"></span><span class="wire-swatch__name">neutral</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #78716c"></span><span class="wire-swatch__name">stone</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f97316"></span><span class="wire-swatch__name">orange</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #84cc16"></span><span class="wire-swatch__name">lime</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #14b8a6"></span><span class="wire-swatch__name">teal</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #0ea5e9"></span><span class="wire-swatch__name">sky</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6366f1"></span><span class="wire-swatch__name">indigo</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #8b5cf6"></span><span class="wire-swatch__name">violet</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #a855f7"></span><span class="wire-swatch__name">purple</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #d946ef"></span><span class="wire-swatch__name">fuchsia</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ec4899"></span><span class="wire-swatch__name">pink</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f43f5e"></span><span class="wire-swatch__name">rose</span></div></div><p class="wire-swatch-group">Achromatic (adaptive)</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ffffff; box-shadow: inset 0 0 0 1px #d1d5db"></span><span class="wire-swatch__name">white</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #000000"></span><span class="wire-swatch__name">black</span></div></div></div>

**Semantic roles** — fixed brand hues that carry meaning:

| Name | Resolves to |
|------|-------------|
| `primary` | Brand primary |
| `success` (alias `emerald`) | Emerald |
| `danger` | Red |
| `warning` (alias `amber`) | Amber |
| `info` | Cyan |
| `gray` (alias `secondary`) | Neutral gray |

**Raw hue families** — every Tailwind color, for finer control:

`blue`, `green`, `red`, `yellow`, `cyan`, `slate`, `zinc`, `neutral`, `stone`,
`orange`, `lime`, `teal`, `sky`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`,
`rose`.

> **Literal hues are not aliases.** `blue`, `green` and `yellow` are their own
> literal Tailwind hues — `blue` is distinct from the re-themeable brand `primary`,
> `green` from `success`/`emerald`, and `yellow` from `warning`/`amber`. `red` and
> `cyan` render the same hue as `danger`/`info` but stay available by name.

**Achromatic endpoints** — `white` and `black`. Tailwind has no numeric
`white`/`black` scale, so these resolve **adaptively**: `black` is a dark ink/fill in
light mode and flips to white in dark mode, `white` is the inverse — so they stay
readable on both themes.

```php
Action::make('delete')->color('danger');   // semantic role
Action::make('archive')->color('teal');     // raw hue
BadgeColumn::make('status')->colors([
    'active' => 'success',
    'pending' => 'warning',
    'inactive' => 'danger',
]);
```

The type-safe `Foundation\Colors\Color` enum has a case for every one of these
(`Color::Danger`, `Color::Teal`, …). Each color resolves to Tailwind utility classes
for bg, text, border, ring, and hover variants — the same value renders identically
on a badge, a solid/outlined/link button, a modal, a choice card, and a chart bar.

### Canonical color resolvers (`HasColor`)

`Foundation\Concerns\HasColor` is the **door** every surface asks through. Ask it
rather than re-encoding a `match` map, and a colour resolves the same way
everywhere.

The rules themselves live in five classes under `Foundation\Colors`, one per
surface — `ButtonPalette`, `TintPalette`, `TextPalette`, `NoticePalette` and
`ChartPalette` — because a decision about an alert is a different decision from
one about a chart. **Keep calling `HasColor`**: the palettes are where a rule is
found and changed, not a second set of names to call.

**A role is not a hue.** `success`, `danger`, `warning` and `info` render as
whatever `wire-core.colors` points them at, resolved once by
`Foundation\Colors\SemanticPalette` at the top of every resolver — see
[Theming → Semantic roles](../../start/theming.md#semantic-roles). The literal
hues stay first-class: `green` is literal green, distinct from a re-pointable
`success`, and the adaptive `white`/`black` endpoints resolve to themselves.

| Resolver | Surface |
|----------|---------|
| `getSolidColorClasses()` | filled button (bg + text + hover + focus + dark) |
| `getOutlinedColorClasses()` | outlined button |
| `getGhostColorClasses()` | dropdown / menu item |
| `getIconButtonColorClasses()` | icon-only button |
| `getLinkColorClasses()` | text/link button (underline on hover) |
| `getSolidBgClass()` / `getSoftBgClass()` | bare fill only (toggle on/off track, count badge) |
| `getBadgeColorClasses()` | soft "pill" badge (bg + text) |
| `getTextColorClasses()` | foreground-only text tint |
| `getChoiceColorClasses()` | radio/segmented/card selected state bundle |
| `getModalSubmitButtonClasses()` | modal confirm/submit button |
| `getModalIconBgClass()` / `getModalIconTextClass()` | modal icon chip |
| `getGradientFillClasses()` / `getFillTextClasses()` | bar-chart fill + accent (literal chart hues) |
| `getOutlinedClasses()` | the outlined vocabulary, callable outside a component |
| `getRowTintClasses()` / `getRowHoverClasses()` | a clickable table row, at rest and under the pointer |
| `getSoftTintClasses()` | the resting fill with no hover — a diff cell, a soft block |
| `getAccentBgClass()` | the bright `-500` accent — a live dot, a progress bar, a filled star |

When adding a color or surface, extend the palette that owns it once —
downstream columns, badges, actions, and toggles pick it up automatically. Keep utility
names compatible with the lowest supported Tailwind version (see
[ADR 0005](https://github.com/ONyklicek/WireStack/blob/main/architecture/decisions/0005-tailwind-4-support.md)); use only
standard hue names, never version-specific ones.

### Canonical sizing & typography resolvers

Sibling single-source resolvers, used the same way as `HasColor` — extend once,
every surface picks it up, and class strings stay literal for Tailwind's JIT
scanner.

| Resolver | Surface |
|----------|---------|
| `HasSize::getBadgeSizeClasses($size)` | soft "pill"/badge padding + font size |
| `HasSize::getButtonSizeClasses($size, $iconOnly)` | button padding scale (action buttons, action-group triggers, `ButtonColumn`); `$iconOnly` returns square padding |
| `HasFontWeight::getFontWeightClasses($weight)` | `font-*` weight utility (table columns, infolist entries); unknown weight → `font-normal` |
| `Foundation\Concerns\HasModalProperties::getMaxWidthClass($width, $responsive)` | modal `max-w-*` (centered dialogs gate at `sm:`; slide-overs pass `responsive: false`) |

### Type-safe value enums

Every fluent setter that takes a string token **also accepts a canonical enum** from
`Foundation\Enums\` — `->size('lg')` and `->size(Size::Lg)` are interchangeable, and the
string form stays fully supported. Each enum is the single owner of its vocabulary
(`values()` + `resolve()`), so a token resolves to the same utility on every surface, and
unknown tokens fall back to a sensible default instead of emitting an unscannable class.

| Enum | Tokens | Setters that accept it |
|------|--------|------------------------|
| `Colors\Color` | semantic roles + every raw hue (see [Colors](#colors)) | `->color()` everywhere |
| `Enums\Breakpoint` | `sm` `md` `lg` `xl` `2xl` | column `->visibleFrom()` / `->hiddenFrom()` / `->mobileBreakpoint()`, `Table::stackedOnMobile()`, `->mobileBreakpoint()` on sheets/modals, `Grid` per-breakpoint `columns` keys |
| `Enums\Size` | `xs` `sm` `md` `lg` `xl` | `->size()` (+ `->sm()`/`->md()`/… shortcuts) on actions, buttons, badge/icon columns |
| `Enums\FontWeight` | `thin` `extralight` `light` `normal` `medium` `semibold` `bold` `extrabold` `black` | column `->weight()`, infolist `TextEntry::weight()` |
| `Enums\Alignment` | `left` `center` `right` | column `->alignment()`, `Table::actionsAlignment()` |
| `Enums\IconPosition` | `before` `after` | `->icon($icon, $position)` on actions, buttons, fields |
| `Enums\Placement` | `bottom-start` `bottom-end` `top-start` `top-end` | `ActionGroup::dropdownPosition()` |
| `Enums\ModalWidth` | `sm` `md` `lg` `xl` `2xl` … `7xl` `full` | `->width()` / `->modalWidth()` on modals, slide-overs, action modals |

```php
use NyonCode\WireCore\Foundation\Enums\{Alignment, Breakpoint, ModalWidth, Size};

TextColumn::make('email')->visibleFrom(Breakpoint::Md)->alignment(Alignment::Right);
Action::make('edit')->size(Size::Lg)->modalWidth(ModalWidth::TwoXl);
```

The `Breakpoint`, `Alignment` and `Placement` enums additionally own the **literal** Tailwind
classes their tokens map to (`Breakpoint::Md->tableCellClass()`, `Alignment::Right->textClass()`,
`Placement::TopEnd->originClass()`), so the class map has one owner and Blade consumes a scannable
utility instead of interpolating `text-{$align}`.

## Related

- [Icons](icons.md) — the other half of a surface's vocabulary
- [Enums](enums.md) — an enum naming its own colour
- [Theming](../../start/theming.md) — changing the palette an application uses
- [BadgeColumn](../../table/columns/badge.md) — the resolution ladder in a real surface
