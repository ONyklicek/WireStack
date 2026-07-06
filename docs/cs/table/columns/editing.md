---
order: 28
nav: false
---

# Editace a filtry na úrovni sloupce

<a id="column-level-filtering"></a>
## Filtrování na úrovni sloupce

Kromě dedikovaných tříd Filter může mít jakýkoli sloupec inline filtr ve své hlavičce.

```php
// Select filtr v hlavičce sloupce
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

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

```php
->filterable(bool $filterable = true, string $type = 'text', array|string $options = [])
->isFilterable(): bool
->filterAsSelect(array|string $options, ?string $placeholder = null)  // pole nebo třída enumu
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
jako dedikovaný `SelectColumn`/`SelectFilter`. Viz [Enum Options](#enum-options).
