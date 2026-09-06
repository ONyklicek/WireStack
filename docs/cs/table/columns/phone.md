---
summary: Uložené telefonní číslo zapsané tak, jak se čte, a odkázané pro vytáčení.
---

# PhoneColumn

Telefonní číslo, které člověk přečte a zařízení vytočí. Sáhněte po něm všude, kde
sloupec drží E.164 — tvar, který chce databáze, a který nikdo nečte.

```php
use NyonCode\WireTable\Columns\PhoneColumn;
```

## Jak to funguje

**Píše číslo stejnou gramatikou jako formulář.** Předvolba se hledá ve sdílené
tabulce `Foundation\ValueObjects\DialingCodes` a národní část se seskupuje po
trojicích — přesně to, co ukazuje
[PhoneInput](../../forms/fields/phone-input.md) při editaci. Obě plochy čtou
jednu tabulku, takže číslo nemůže být ve formuláři odsazené jinak než v řádku,
který ho vypisuje.

**Odkaz je smysl sloupce.** Buňka se vykreslí jako `tel:` a číslice — mezery,
které ho dělají čitelným, se zahodí, protože vytáčení chce číslice — takže
kliknutí na telefonu zavolá a na desktopu otevře softphone. Samotné formátování
je jen `TextColumn::make('phone')->formatStateUsing(…)`; tenhle sloupec existuje
kvůli odkazu. `notCallable()` zápis ponechá a odkaz zruší.

**Předvolbu, kterou tabulka nezná, nechá být.** Neformátované číslo je lepší než
číslo seskupené podle země, do které nepatří — a odkaz zůstává, protože neznámá
předvolba není neplatné číslo.

**Poslední osamocená číslice se přidá ke skupině před sebou** — `+1 212 555 1234`,
ne `+1 212 555 123 4`.

## Základní použití

```php
PhoneColumn::make('phone')
```

`+420123456789` ve sloupci se vykreslí jako `+420 123 456 789` s odkazem
`tel:+420123456789`.

## Číslo, na které se nevolá

```php
PhoneColumn::make('fax')
    ->notCallable()
```

## Se zbytkem API sloupce

```php
PhoneColumn::make('phone')
    ->label('Mobil')
    ->copyable()             // zkopíruje zapsané číslo do schránky
    ->searchable()
    ->toggleable()
```

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireTable\Columns\PhoneColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Concerns\WithTable;

class ListContacts extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Contact::class)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                PhoneColumn::make('phone')          // [tl! focus:start]
                    ->label('Mobil')
                    ->copyable(),
                PhoneColumn::make('fax')
                    ->notCallable()
                    ->toggleable(isToggledHiddenByDefault: true), // [tl! focus:end]
            ]);
    }
}
```

## API PhoneColumn

Telefonní část. Všechno ostatní — `->label()`, `->sortable()`, `->searchable()`,
`->copyable()`, `->toggleable()` — je sdílené API sloupce, zdokumentované ve
[Sloupce](index.md).

```php
->notCallable(bool $condition = true)   // ukáže číslo, zruší tel: odkaz
->isCallable(): bool
```

## Související

- [Sloupce](index.md) — sdílené API, které dědí každý sloupec
- [PhoneInput](../../forms/fields/phone-input.md) — pole, které totéž číslo edituje
- [TextColumn](text.md) — pro číslo, které nepotřebuje odkaz ani seskupení
