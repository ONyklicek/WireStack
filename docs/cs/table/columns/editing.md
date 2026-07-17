---
order: 28
nav: false
---

# Editace a filtry na úrovni sloupce

<a id="column-level-filtering"></a>
## Filtrování na úrovni sloupce

Kromě dedikovaných tříd Filter může mít jakýkoli sloupec inline filtr ve své hlavičce.

```php
// Select filtr v hlavičce sloupce (jedna hodnota)
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

// Multi-select filtr (více hodnot → odpovídá kterékoli, whereIn). Renderuje stejný
// searchable combobox jako wire-forms Select — vyhledávání je defaultně zapnuté.
BadgeColumn::make('role')
    ->filterAsMultiSelect([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ], 'Libovolná role')
    ->filterSearchable(false)               // vypnutí vyhledávání pro krátký seznam

// Boolean filtr
BooleanColumn::make('is_active')
    ->filterable()
    ->filterAsBoolean()

// Filtr rozsahu data
TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()

// Filtr rozsahu čísel
TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)        // min, max, volitelný krok

// Vlastní logika filtru
TextColumn::make('name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) => $query->where('name', 'like', "%{$value}%"))
    ->filterDebounce(500)

// Filtr s operátorem
TextColumn::make('age')
    ->filterable()
    ->filterOperator('>=')
```

### API filtru na úrovni sloupce

Filtr hlavičky sloupce je umístění kanonického `Filter` — pomocné metody
`filterAs*()` jsou tenké factory nad `TextFilter` / `SelectFilter` / `DateFilter` /
`NumberRangeFilter` / `TernaryFilter`, nebo předej hotový přes `->filter()`. Sdílený
engine, chipy a query-string persistenci viz [Filtry na úrovni sloupce](../filters/column-level.md).

```php
->filterable(bool $filterable = true, string $type = 'text', array|string $options = [])
->isFilterable(): bool
->filter(Filter $filter)                                                   // připoj hotový kanonický filtr
->getFilter(): ?Filter
->filterAsSelect(array|string $options, ?string $placeholder = null)       // jedna hodnota; searchable combobox
->filterAsMultiSelect(array|string $options, ?string $placeholder = null)  // více hodnot (whereIn); searchable combobox
->filterSearchable(bool $condition = true)                                 // přepnutí vyhledávání (defaultně zapnuté)
->filterAsDate(?string $minDate = null, ?string $maxDate = null)
->filterAsDateRange(?string $minDate = null, ?string $maxDate = null)
->filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null)
->filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null)
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like' (výchozí, částečná shoda), 'starts_with', 'ends_with'
->filterDebounce(int $ms)
->filterPlaceholder(?string $placeholder)
->filterUsing(Closure $fn)             // fn(Builder $query, mixed $value)
```

---

## Inline editace

Sloupce mohou také použít generické API `editable()` (kromě dedikovaných TextInputColumn/SelectColumn/ToggleColumn):

```php
TextColumn::make('name')
    ->editable()                              // typ výchozí 'text'
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })

TextColumn::make('category')
    // editable(enabled, type, options) — 'text' | 'select' | 'toggle'
    ->editable(true, 'select', ['a' => 'Category A', 'b' => 'Category B'])
    ->editableRules(fn ($record) => ['required', 'in:a,b'])
```

Argument `options` u `editable(type: 'select', …)` i `filterable()` /
`filterAsSelect()` přijímá i třídu PHP enumu — rozvine se na `value => label` přesně
jako dedikovaný `SelectColumn`/`SelectFilter`. Viz [Enum Options](select.md#options-z-enumu).

### Jak fungují inline uložení

Uložení buňky (`updateTableCell`) záměrně **nepřekresluje tabulku** — DOM morph by resetoval
Alpine stav všech editovatelných buněk. Místo toho každá buňka přepne svůj vzhled **optimisticky**
a sesynchronizuje se serverem přes jednu sdílenou Alpine komponentu (`wireEditableCell`): text
inputy, selecty i toggly ji používají, takže se chovají konzistentně.

- **Optimistic + rollback.** Buňka hned ukáže novou hodnotu, pak zavolá server; když uložení selže
  (validace, oprávnění, chyba), vrátí se na poslední serverem potvrzenou hodnotu a zobrazí zprávu.
- **Optimistic locking.** Každý edit nese verzi řádku (`updated_at`). Když se řádek od načtení
  stránky změnil, uložení se odmítne jako konflikt: buňka načte aktuální hodnotu a zobrazí zprávu o
  konfliktu **přímo na buňce** (červený stav na text/select/toggle, bez toastu nebo nastavení
  `NotificationManager`) — dva lidé (nebo dva rychlé edity, které řádek bumpnou) se tak tiše
  nepřepíšou. Polling verzi buněk obnoví v dalším cyklu. Volitelně lze pro konflikty vyvolat i
  (nápadnější) toast přes `Table::notifyEditConflicts()` — ten už vyžaduje zapojený notifikační
  systém (toast container); inline hláška funguje i bez něj.
- **Serverová autorizace.** Klientský `disabled()` stav je jen kosmetika — per-record `disabled()`
  buňka (i oprávnění sloupce) se znovu vynutí na serveru v `updateTableCell`, takže forged request
  nemůže zapsat do zamčené buňky.
