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
->filterAsDate(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
->filterAsDateRange(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
->filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null)
->filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null)
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like' (výchozí, částečná shoda), 'starts_with', 'ends_with'
->filterDebounce(int $ms)
->filterPlaceholder(?string $placeholder)
->filterUsing(Closure $fn)             // fn(Builder $query, mixed $value)
```

---

## Inline editace

**Editor určuje typ sloupce**, ne přepínač: použijte
[TextInputColumn](text-input.md), [SelectColumn](select.md),
[ToggleColumn](toggle.md) nebo [CheckboxColumn](checkbox.md). Obyčejný sloupec
žádný editor nevykreslí.

`editable()` je vypínač editoru u dedikovaného sloupce a zároveň serverová brána
pro zápis do toho sloupce:

```php
TextInputColumn::make('name')
    ->editable(fn () => auth()->user()->isAdmin())   // false vykreslí prostou hodnotu
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })
```

Pojmenování typu editoru — `editable(true, 'select', […])` — vyhodí výjimku:
žádná view ho nikdy nečetla, takže by tiše nedělalo nic. Použijte `SelectColumn`.

Argument `options` u `filterable()` / `filterAsSelect()` přijímá i PHP enum —
rozbalí se na `value => label` stejně jako u `SelectColumn`/`SelectFilter`.
Viz [Options z enumu](select.md#options-z-enumu).

### Jak fungují inline uložení

Uložení buňky (`updateTableCell`) **překreslí tabulku** a buňka si přitom ochrání vlastní stav.
Všechno odvozené od zapsané hodnoty — summary, rollup, badge počítaný ze stejného sloupce, pozice
řádku pod aktuálním řazením — je v okamžiku zápisu zastaralé a spravit to umí jen render.

Buňka morph přežije proto, že její root nese `wire:ignore.self`, takže jí Livewire nesahá na
atributy ani na Alpine stav. Právě proto se k ní ale nová hodnota nemůže dostat přes tento root:
doručuje se na **sync uzlu**, malém potomkovi, který morph aktualizuje a který si buňka hlídá.
Všechno tohle dělá jedna sdílená Alpine komponenta (`wireEditableCell`): text inputy, selecty
i toggly ji používají, takže se chovají konzistentně.

- **Optimistic + rollback.** Buňka hned ukáže novou hodnotu, pak zavolá server; když uložení selže
  (validace, oprávnění, chyba), vrátí se na poslední serverem potvrzenou hodnotu a zobrazí zprávu.
- **Optimistic locking.** Každý edit nese verzi řádku (`updated_at`, resolvovaný přes vlastní
  timestamp sloupec modelu, takže `const UPDATED_AT` je respektován). Když se řádek od načtení
  stránky změnil, uložení se odmítne jako konflikt: buňka načte aktuální hodnotu a zobrazí zprávu o
  konfliktu **přímo na buňce** (červený stav na text/select/toggle, bez toastu nebo nastavení
  `NotificationManager`) — dva lidé (nebo dva rychlé edity, které řádek bumpnou) se tak tiše
  nepřepíšou. Jakýkoli re-render — tick pollingu, zápis z modalu, příchozí změna z jiné relace —
  obnoví přes sync uzel hodnotu buňky *i* její verzi, takže další edit se porovnává proti tomu, co
  je opravdu v databázi. Volitelně lze pro konflikty vyvolat i (nápadnější) toast přes
  `Table::notifyEditConflicts()` — ten už vyžaduje zapojený notifikační systém (toast container);
  inline hláška funguje i bez něj.
- **Vypnutí renderu.** `Table::refreshAfterEdit(false)` se vrací k odpovědi bez HTML. Vyplatí se
  jen u tabulky, kde je dotaz za renderem drahý a na editované hodnotě nic na obrazovce nezávisí:
  buňka se z odpovědi sesynchronizuje pořád, okolí ne.
- **Serverová autorizace.** Klientský `disabled()` stav je jen kosmetika — per-record `disabled()`
  buňka (i oprávnění sloupce) se znovu vynutí na serveru v `updateTableCell`, takže forged request
  nemůže zapsat do zamčené buňky.
