---
order: 57
---

# Seskupení řádků

Seskupte řádky podle hodnoty sloupce: tabulka seřadí záznamy tak, aby skupiny
zůstaly souvislé, vykreslí hlavičkový řádek pro každou skupinu a přidá řádky
mezisoučtů za skupinu pro každý sloupec se [souhrnem](summaries.md) — navíc
k obvyklé patičce s celkovým součtem.

## Rychlý start

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->label('Invoice'),
            TextColumn::make('customer')->label('Customer'),
            TextColumn::make('total')
                ->suffix(' Kč')
                ->summaryDecimals(0)
                ->summarizeSum('Sum'),
        ])
        ->groupBy('customer');
}
```

```text
┌──────────────────────────────┐
│ Acme                         │   ← hlavička skupiny
├────────────┬─────────────────┤
│ INV-2      │        250 Kč   │
│ INV-4      │         25 Kč   │
│ Sum:       │        275 Kč   │   ← mezisoučet skupiny
├──────────────────────────────┤
│ Beta                         │
│ INV-1      │        100 Kč   │
│ INV-3      │         50 Kč   │
│ Sum:       │        150 Kč   │
├────────────┼─────────────────┤
│ Sum:       │        425 Kč   │   ← patička s celkovým součtem
└────────────┴─────────────────┘
```

## Konfigurace

| Metoda                          | Efekt                                              |
| ------------------------------- | --------------------------------------------------- |
| `groupBy(string $column)`       | Seskupit řádky podle přímého sloupce na modelu          |
| `groupLabel(string\|Closure)`   | Přizpůsobit popisek hlavičky skupiny                    |
| `groupSummaries(bool)`          | Přepnout řádky mezisoučtů za skupinu (výchozí zapnuto)         |

### Popisky skupin

Hlavička ve výchozím stavu zobrazuje surovou hodnotu skupiny. Řetězcový popisek
se stane prefixem; closura dostane hodnotu a první záznam skupiny:

```php
->groupBy('customer')->groupLabel('Customer')            // "Customer: Acme"
->groupBy('status')->groupLabel(fn ($value) => match ($value) {
    'paid' => '✓ Paid',
    'pending' => '⏳ Pending',
    default => ucfirst((string) $value),
})
```

Prázdné a `null` hodnoty skupiny se vykreslí jako `—`.

## Řazení

Seskupení předřadí vzestupné řazení na sloupci skupiny, takže jakékoli jiné
řazení — nakonfigurovaný `defaultSort()` nebo uživatelovo kliknutí na hlavičku —
se aplikuje **uvnitř** každé skupiny. Řazení podle samotného sloupce skupiny
převezme kontrolu úplně: uživatelův směr pak řídí pořadí skupin (a skupiny
zůstanou souvislé, protože řazení podle sloupce skupiny řadí skupiny z definice).

## Mezisoučty

Řádky mezisoučtů skupiny se objeví automaticky pro každý sloupec se souhrnem;
platí všechny [typy agregací a formátování](summaries.md). Mezisoučty se počítají
v paměti z řádků skupiny na aktuální stránce.

```php
TextColumn::make('total')
    ->summaryDecimals(0)
    ->summarizeSum('Sum')       // → řádek mezisoučtu skupiny + patička celkového součtu
    ->summarizeAvg('Average'),  // každý souhrn dostane svůj vlastní řádek mezisoučtu
```

Řádky mezisoučtů (při zachování hlaviček a patičky) vypnete pomocí
`->groupSummaries(false)`.

## Omezení

- **Jen přímé sloupce.** `groupBy('customer.name')` vyhodí chybu — seskupení musí
  seřadit dotaz podle sloupce skupiny, což cesta relace bez joinu nedokáže.
  Vystavte související hodnotu na dotazu (join + select alias) a seskupte podle
  aliasu.
- **Stránkování rozděluje skupiny.** Skupina překračující hranici stránky ukáže
  částečný mezisoučet na každé stránce. Pro striktní účetní reporty vypněte
  stránkování (`->paginated(false)`) nebo zvyšte `perPage()`.
- **Layout desktopové tabulky.** Hlavičky/mezisoučty skupin se vykreslují ve
  standardním layoutu tabulky; naskládaný mobilní kartový layout seskupení ignoruje.
- **Exporty** obsahují datové řádky a celkové součty, ne řádky mezisoučtů skupin.

## Související dokumentace

- [Souhrny](summaries.md) — typy agregací, rozsahy, formátování
- [Sloupce](columns/index.md)
- [Přehled tabulek](overview.md)
