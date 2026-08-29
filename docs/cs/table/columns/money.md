---
order: 23
nav: false
---

# MoneyColumn

Částka podaná tak, jak se čísla čtou: u pravé hrany, v číslicích stejné šířky,
na jednom řádku.

```php
use NyonCode\WireTable\Columns\MoneyColumn;
```

## Jak to funguje

Formátování tomuhle sloupci nepatří. `money()` žije ve sdíleném concernu
`FormatsState`, takže `TextColumn::make('total')->money()` vrátí přesně stejný
řetězec — a infolist `TextEntry` naformátuje stejnou hodnotu stejně. Co
samostatný typ mění, jsou **výchozí hodnoty sloupce**, a u peněz jsou tři z nich
špatně hned na začátku.

**Zarovnává doprava.** Částky se porovnávají po sloupci dolů a to funguje jen
proti pevné pravé hraně. V tomhle frameworku nese zarovnání ještě druhý význam:
stacked mobilní karta z něj odvozuje *metriku* — to jedno číslo, které telefon
ukáže v hlavičce karty — jako poslední doprava zarovnaný sloupec. Peněžní
sloupec se jí stane, aniž by mu to někdo řekl, takže správný desktop vyřeší
i telefon.

**Má tabulární číslice.** Proporcionální číslice dávají `1` a `7` různou šířku,
takže desetinné čárky plavou i pod pravou hranou. `tabular-nums` je stejný
slovník, jaký už používají souhrnné patičky a mobilní karta.

**Nezalamuje se.** Částka rozlomená na dva řádky přestává být jedno číslo.

Všechny tři jsou výchozí hodnoty, ne pravidla — viz [Přepsání výchozích hodnot](#prepsani-vychozich-hodnot).

## Základní použití

```php
MoneyColumn::make('total')                    // 1 234,50 CZK
MoneyColumn::make('total')->currency('EUR')   // 1 234,50 EUR
MoneyColumn::make('total')->withoutCurrency() // 1 234,50
```

## Přesnost a oddělovače

Výchozí nastavení je česká konvence — dvě desetinná místa, čárka, úzká mezera —
s jednou zděděnou výjimkou, o které je dobré vědět:

```php
MoneyColumn::make('total')->currency('CZK')   // 1 234,50 CZK   ← haléře
MoneyColumn::make('total')->currency('Kč')    // 1 235 Kč       ← celé koruny
```

Přesnost se řídí tím, **jak je měna napsaná**, ne tím, co to za měnu je. Takové
pravidlo nemá cenu vymýšlet a drží se jen proto, že na něm už tabulky stojí.
Místo spoléhání na něj to řekněte:

```php
MoneyColumn::make('total')
    ->money('EUR', decimals: 2, decimalSeparator: '.', thousandsSeparator: ',')
// 1,234.50 EUR
```

Vynechaný argument nikdy nepřepíše dřív nastavenou hodnotu, takže se dá řetězit
bez obav:

```php
MoneyColumn::make('total')
    ->money('EUR', 2, '.', ',')
    ->currency('USD')      // oddělovače zůstávají, mění se jen měna
// 1,234.50 USD
```

## Umístění měny

Umístění je vlastnost konvence měny, ne čísla, takže se říká a nehádá — žádná
tabulka locale za tím není a odvozovat ji z třípísmenného kódu by bylo špatně
častěji než dobře:

```php
MoneyColumn::make('total')
    ->money('$', 2, '.', ',')
    ->currencyBefore()
// $ 1,234.50
```

## V tabulce

Peněžní sloupec je obyčejný sloupec: řadí se, sčítá se a nese číslo na telefon.

```php
public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->sortable(),
            TextColumn::make('customer.name')->label('Zákazník'),
            BadgeColumn::make('state'),
            MoneyColumn::make('total')                    // [tl! focus]
                ->currency('EUR')                         // [tl! focus]
                ->sortable()                              // [tl! focus]
                ->summarizeSum('Celkem'),                 // [tl! focus]
        ])
        ->stackedOnMobile();   // částka je metrikou karty — viz níže
}
```

Pod stacking breakpointem se každý řádek stane kartou a `total` přistane
v hlavičce vedle čísla faktury místo v mřížce popisek/hodnota dole — protože je
to poslední doprava zarovnaný sloupec. Nikde se to nedeklaruje dvakrát; viz
[Naskládané na mobilu](../advanced.md#naskladane-na-mobilu).

## Přepsání výchozích hodnot

Každá výchozí hodnota je jen výchozí:

```php
MoneyColumn::make('total')
    ->alignment('left')     // …a přestává být metrikou karty
    ->textSize('lg')        // velikost, řez, barva a font rodina zůstávají
```

Zrušení pravého zarovnání zruší i odvození mobilní metriky, a to je ten smysl:
odvození se řídí zarovnáním, ne třídou.

## Formátování bez toho sloupce

Kterýkoli `TextColumn` — a kterýkoli infolist `TextEntry` — bere stejné volání:

```php
TextColumn::make('total')->money('EUR', 2, '.', ',')->alignRight()
```

`MoneyColumn` použijte, když ta hodnota jsou peníze; `->money()`, když je sloupec
hlavně něco jiného a shodou okolností číselný.

## MoneyColumn API

```php
->currency(?string $currency)                  // měna, ve které se renderuje
->withoutCurrency()                            // holá naformátovaná částka
```

Zděděno z `TextColumn` / `FormatsState`:

```php
->money(?string $currency = 'CZK', ?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null)
->currencyBefore(bool $before = true)
->usesCurrencyBefore(): bool
->getMoneyDecimals(): int
->isMoney(): bool
->getCurrency(): ?string
```
