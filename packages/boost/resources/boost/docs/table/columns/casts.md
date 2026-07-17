---
order: 22
nav: false
---

# Enum & JSON Casts

When a model casts an attribute to a PHP enum or to `array`/`json`, the column reads the
**raw cast value** (an enum instance, an array) — not a string. Every column handles this for
you: the value is normalized through the canonical `EnumResolver` before it is rendered, so you
never hit an `Object of class … could not be converted to string` fatal or a stray `Array`.

## Backed & unit enums

```php
// app/Models/Order.php
protected $casts = [
    'status' => OrderStatus::class,   // backed enum: 'pending', 'paid', …
];
```

```php
// A plain column just works — without an explicit label the case name is headlined
// for display (`InReview` → "In Review"), the same text the value yields as a select option.
TextColumn::make('status')
```

To control the exact text, let the enum carry its own label by implementing the opt-in contract:

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }
}
```

```php
TextColumn::make('status')   // now renders "Awaiting payment", "Paid", …
```

> `formatStateUsing()` still receives the **raw enum instance**, so you can keep full control:
> `->formatStateUsing(fn (OrderStatus $s) => $s->getLabel())`.

## Self-coloring / self-icon enums (badges & icons)

`BadgeColumn` and `IconColumn` auto-resolve color and icon straight from the enum when it
implements `HasColor` / `HasIcon` — no `colors()` / `icons()` map needed:

```php
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): ?string  { return ucfirst($this->value); }

    public function getColor(): string|Color|null
    {
        return match ($this) {
            self::Pending => Color::Warning,
            self::Paid => Color::Success,
            self::Refunded => Color::Gray,
        };
    }

    public function getIcon(): string|Icon|null
    {
        return match ($this) {
            self::Pending => Icon::clock,
            self::Paid => Icon::checkCircle,
            self::Refunded => Icon::arrowUturnLeft,
        };
    }
}
```

```php
BadgeColumn::make('status')   // colored + iconed badge, label text — all from the enum
IconColumn::make('status')    // icon + color from the enum
```

An explicit `->colors([...])` / `->icons([...])` map still wins when present; the enum contracts
are the fallback. Map keys are matched against the enum's **scalar** value (`->value` / case name):

```php
BadgeColumn::make('status')->colors([
    'paid' => 'success',     // keyed by the backing value
    'pending' => 'warning',
])
```

## array / json casts

```php
protected $casts = ['meta' => 'array'];
```

```php
TextColumn::make('meta')   // renders compact JSON: {"k":"v"} — never the literal "Array"
```

## Where it applies

The same normalization runs everywhere a cast value is shown or written: text/badge/icon/select
columns, **exports** (CSV/Excel/PDF export the display label / compact JSON), **`groupBy()`**
headers and **summaries**, **filter indicator chips**, and **infolist entries**. See
[Foundation → Enums](../../core/foundation.md#enums) for the underlying `EnumResolver` and contracts.
