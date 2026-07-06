---
order: 22
nav: false
---

# Enum a JSON casty

Když model přetypuje atribut na PHP enum nebo na `array`/`json`, sloupec čte
**surovou cast hodnotu** (instanci enumu, pole) — ne řetězec. Každý sloupec to za vás
zvládne: hodnota se normalizuje přes kanonický `EnumResolver`, než se vykreslí, takže
nikdy nenarazíte na fatal `Object of class … could not be converted to string` ani na zbloudilé `Array`.

## Backed a unit enumy

```php
// app/Models/Order.php
protected $casts = [
    'status' => OrderStatus::class,   // backed enum: 'pending', 'paid', …
];
```

```php
// Prostý sloupec prostě funguje — bez explicitního labelu se z názvu case udělá headline
// pro zobrazení (`InReview` → "In Review"), stejný text, jaký hodnota vydá jako select option.
TextColumn::make('status')
```

Pro řízení přesného textu nechte enum nést vlastní label implementací opt-in kontraktu:

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
TextColumn::make('status')   // nyní vykreslí "Awaiting payment", "Paid", …
```

> `formatStateUsing()` stále dostane **surovou instanci enumu**, takže si můžete udržet plnou kontrolu:
> `->formatStateUsing(fn (OrderStatus $s) => $s->getLabel())`.

## Self-coloring / self-icon enumy (badge a ikony)

`BadgeColumn` a `IconColumn` auto-resolvují barvu a ikonu rovnou z enumu, když
implementuje `HasColor` / `HasIcon` — mapa `colors()` / `icons()` není potřeba:

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
BadgeColumn::make('status')   // barevný + ikonovaný badge, text labelu — vše z enumu
IconColumn::make('status')    // ikona + barva z enumu
```

Explicitní mapa `->colors([...])` / `->icons([...])` stále vyhraje, když je přítomna;
enum kontrakty jsou fallback. Klíče mapy se párují proti **skalární** hodnotě enumu (`->value` / název case):

```php
BadgeColumn::make('status')->colors([
    'paid' => 'success',     // klíčováno backing hodnotou
    'pending' => 'warning',
])
```

## array / json casty

```php
protected $casts = ['meta' => 'array'];
```

```php
TextColumn::make('meta')   // vykreslí kompaktní JSON: {"k":"v"} — nikdy doslovné "Array"
```

## Kde to platí

Stejná normalizace běží všude, kde se cast hodnota zobrazuje nebo zapisuje: text/badge/icon/select
sloupce, **exporty** (CSV/Excel/PDF exportují label zobrazení / kompaktní JSON), **`groupBy()`**
hlavičky a **souhrny**, **indikátorové chipy filtrů** a **infolist entries**. Podkladový
`EnumResolver` a kontrakty viz [Foundation → Enumy](../../core/foundation.md#enums).
