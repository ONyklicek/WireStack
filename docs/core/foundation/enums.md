---
order: 40
summary: "The contracts an enum implements to name its own label, colour and icon — so a state is described once and every surface reads it."
---

# Enums

A status enum knows what it is called, what colour it is and which icon it
carries. Saying that on the enum, once, is what keeps a badge column, an infolist
entry and a select from each holding their own map of the same three facts — and
those maps are what drift the first time a state is added.

## Enums

PHP enums cannot be stringified with `(string) $enum`, yet Eloquent enum casts hand the raw
instance to every display and state surface. `EnumResolver` is the single canonical owner that
normalizes such values; downstream packages (table, forms, infolists, exports) delegate to it
instead of re-encoding `(string) $enum` or local `match` maps.

```php
use NyonCode\WireCore\Foundation\Support\EnumResolver;

EnumResolver::scalar($value);   // backed enum → ->value, unit enum → case name, else passthrough
EnumResolver::label($value);    // getLabel() → label() method → headline(case name); non-enum passthrough
EnumResolver::display($value);  // label() + array/JSON → compact JSON; (string)-safe everywhere
EnumResolver::color($value);    // HasColor → getColor(), else null
EnumResolver::icon($value);     // HasIcon  → getIcon(),  else null
EnumResolver::isEnum($value);   // bool — is this an enum instance?

EnumResolver::isEnumClass($value);       // bool — is this an enum class-string?
EnumResolver::options(Status::class);    // [value => label] map from the enum's cases
EnumResolver::normalizeOptions($value);  // enum class → options() map; arrays pass through
```

Use `scalar()` for map keys, comparisons and copy values; `display()` (or `label()`) wherever a
value is shown. Non-enum values always pass through untouched, so the helpers are safe to call on
anything.

`options()` powers the Filament-style enum-as-options shorthand: any option-based surface —
form `Select` / `Radio` / `CheckboxList` (via the shared `WireForms\Concerns\HasOptions` trait),
table `SelectColumn` and `SelectFilter`, plus the generic `Column::editable()` / `filterable()` /
`filterAsSelect()` — accepts `->options(Status::class)` and delegates the expansion here. Each case
keys by `scalar()` and labels through the same canonical `label()` resolution, so an option reads
identically to the matching display cell. A single-value form field whose options come from an enum
also gains an automatic `in:` validation rule (see [Forms → Select](../../forms/fields/select.md#enum-options)).

### What a file is, as a family

A surface that shows a file has to answer one question before it can draw
anything: is there a picture, and if not, what is this? Six places used to answer
it on their own and all six answered the same impoverished thing — an image, or
one grey document icon — so a catalogue, a price list, a contract and a print
archive rendered as four identical grey rectangles.

`FileKind` is the single owner of that answer. Its cases are **families**, not
formats, and each carries a hue from the shared palette and an icon from the
shared set, so nothing new enters either vocabulary.

```php
use NyonCode\WireCore\Foundation\Enums\FileKind;

FileKind::for('application/pdf');                  // FileKind::Document // [tl! focus:5]
FileKind::for(null, 'cenik-q1.xlsx');              // Spreadsheet — the name, when there is no mime type
FileKind::for('application/octet-stream', 'a.zip');// Archive — the name, when the mime type means nothing
FileKind::for('text/plain', 'export.csv');         // Spreadsheet — what a person opening it would say

FileKind::extensionOf('cenik.ods');                // 'ODS' — the wordmark, from the file's own name
FileKind::extensionOf('report.final version');     // null — not an extension, so don't print one
```

The mime type decides, because it is read from the stored file rather than from
what a browser claimed about it. The file's name is consulted in exactly two
cases, both real: a **null** mime type — rows written before it was recorded —
and one of the handful that are true of almost anything (`application/octet-stream`,
`text/plain`) and so decide nothing.

**The family is not the wordmark.** `XLSX` and `ODS` are both `Spreadsheet` and
must not both read "XLSX", so the letters on a tile come from the file's own name.
A name with no usable extension falls back to the family's label.

| Case | Colour | Case | Colour |
|------|--------|------|--------|
| `Image` | violet | `Presentation` | orange |
| `Video` | pink | `Archive` | yellow |
| `Audio` | teal | `Code` | slate |
| `Document` | blue | `Other` | gray |
| `Spreadsheet` | green | | |

The vocabulary is **closed**. One an application could rewrite is one no package
could rely on — `Spreadsheet` has to mean a spreadsheet — so anything unmatched is
`Other`, which renders as the file's own extension on a neutral ground.

### Opt-in enum contracts

An enum used as a cast may implement any of these to drive richer rendering. They live under
`Foundation\Contracts\Enum\` and are **distinct** from the builder-facing `Foundation\Contracts\HasLabel`
/ `HasIcon` (which carry fluent setters for components).

| Contract | Method | Effect |
|----------|--------|--------|
| `Enum\HasLabel` | `getLabel(): ?string` | Display surfaces render this label instead of the default headline of the case name |
| `Enum\HasColor` | `getColor(): string\|Color\|null` | `BadgeColumn` / `IconColumn` / `IconEntry` auto-resolve the color |
| `Enum\HasIcon` | `getIcon(): string\|Icon\|null` | The same surfaces auto-resolve the icon |

```php
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function getLabel(): ?string        { return ucfirst($this->value); }
    public function getColor(): string|Color|null
    {
        return $this === self::Paid ? Color::Success : Color::Warning;
    }
}
```

See [Table → Enum & JSON Casts](../../table/columns/casts.md) for column-level usage.

## Related

- [Colors](colors.md) and [Icons](icons.md) — the vocabularies an enum names
- [BadgeColumn](../../table/columns/badge.md) — the resolution order an enum takes part in
- [Enum & JSON Casts](../../table/columns/casts.md) — what a column does with a cast value
- [Workflow And Transitions](../actions/workflow.md) — enums as the states of a record
