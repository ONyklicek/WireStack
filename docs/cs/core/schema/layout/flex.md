---
order: 10
---

# Flex

Uspořádá dětské komponenty vedle sebe na jedné vodorovné (flexbox) ose,
na malých obrazovkách je skládá svisle. Použijte ho, když chcete řadu ovládacích
prvků nebo panelů, které rostou a sdílejí prostor, místo pevného sloupcového gridu.

> Nezaměňovat s table
> [`SplitColumn`](../../../table/columns/split.md), který dělí prostor *uvnitř
> jedné buňky tabulky*.

```php
use NyonCode\WireCore\Foundation\Schema\Flex;
```

## Použití

```php
Flex::make()->schema([
    TextInput::make('first_name'),
    TextInput::make('last_name'),
])
```

Ve výchozím stavu potomci rostou a sdílejí řádek rovnoměrně a řádek se stane
vodorovným na breakpointu `md`, pod ním se skládá svisle.

## Řízení layoutu

```php
Flex::make()
    ->from('lg')          // vodorovně od breakpointu lg místo md
    ->justify('between')  // rozdělit potomky podél hlavní osy
    ->align('center')     // zarovnání na příčné ose
    ->gap(6)              // mezera mezi potomky (Tailwind gap škála 0–12)
    ->grow(false)         // zachovat přirozené šířky místo vyplnění řádku
    ->wrap()              // povolit potomkům zalomit na více řádků
    ->schema([...])
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `from(string)` | Breakpoint, na kterém se potomci uspořádají vodorovně: `sm`, `md` (výchozí) nebo `lg` |
| `justify(string)` | Rozdělení na hlavní ose: `start`, `end`, `center`, `between`, `around`, `evenly` |
| `align(string)` | Zarovnání na příčné ose: `start`, `end`, `center`, `stretch`, `baseline` |
| `gap(int)` | Mezera mezi potomky na Tailwind gap škále (0–12, výchozí 4) |
| `grow(bool)` | Zda potomci rostou a vyplňují řádek rovnoměrně (výchozí `true`) |
| `wrap(bool)` | Povolit potomkům zalomit na více řádků (výchozí `false`) |

## Související dokumentace

- [Grid](grid.md)
- [Section](section.md)
