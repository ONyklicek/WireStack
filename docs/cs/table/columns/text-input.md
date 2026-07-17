---
order: 23
nav: false
---

# TextInputColumn

Inline textový input — validuje a ukládá při blur (nebo enter).

```php
use NyonCode\WireTable\Columns\TextInputColumn;
```

## Základní použití

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
```

## Typy inputu

`type()` nastaví HTML typ inputu přímo; zkratky níže pokrývají běžné případy
a některé navíc nastaví i rozumný `step`.

```php
TextInputColumn::make('quantity')->numeric()      // type="number"
TextInputColumn::make('quantity')->integer()      // type="number", step="1"
TextInputColumn::make('rate')->decimal()          // type="number", step="0.01"
TextInputColumn::make('rate')->decimal(3)         // type="number", step="0.001"

TextInputColumn::make('email')->email()           // type="email"
TextInputColumn::make('phone')->tel()             // type="tel"
TextInputColumn::make('website')->url()           // type="url"
TextInputColumn::make('secret')->password()       // type="password"

TextInputColumn::make('code')->type('search')     // jakýkoli typ, který potřebuješ
```

## Formátovaná čísla

`money()` zobrazí hodnotu se separátory a při uložení ji parsuje zpět na číslo.
Input zůstává `type="text"` — nativní number input by formátovaný řetězec odmítl.

```php
TextInputColumn::make('total')
    ->money()                       // 1234567.5  ->  "1 234 568"
    ->money(2)                      // 1234567.5  ->  "1 234 567,50"
    ->money(2, ',', '.')            // 1234567.5  ->  "1,234,567.50"

TextInputColumn::make('total')->czk(2)   // alias: money() s českým formátem
```

Zadání `1 234,50` uloží `1234.5`: oddělovač tisíců se odstraní, desetinný
oddělovač se změní na tečku a jakýkoli další znak se zahodí.

## Validace

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])   // nahradí seznam pravidel
    ->rule('alpha_dash')                         // přidá jedno pravidlo
    ->required()                                 // zkratka: přidá 'required'
    ->validationMessages(['max' => 'Příliš dlouhé!'])
    ->validationAttribute('celé jméno')          // název použitý ve zprávách
```

Pravidla mohou být i Closure, která dostane záznam:

```php
TextInputColumn::make('discount')
    ->rules(fn ($record) => $record->is_vip ? ['numeric', 'max:50'] : ['numeric', 'max:10'])
```

> `required(false)` pravidlo `required` **neodstraní** — jen nic nepřidá.
> Buď volání vynech, nebo seznam řiď přes `rules()`.

Validace běží normálně při uložení. Pro validaci během psaní:

```php
TextInputColumn::make('name')->liveValidation()            // 500ms debounce
TextInputColumn::make('name')->liveValidation(debounce: 150)
```

### Nativní omezení

Vykreslí se jako atributy inputu, takže je vynucuje i prohlížeč. Nenahrazují
`rules()` — podvržený požadavek prohlížeč obejde.

```php
TextInputColumn::make('quantity')
    ->min('0')->max('9999')      // meze pro číslo/datum
    ->step('0.5')
    ->minLength(3)->maxLength(255)
    ->pattern('[A-Z]{3}-[0-9]+')
```

## Ukládání

```php
TextInputColumn::make('name')
    ->saveOnBlur()               // uložit při ztrátě fokusu
    ->saveOnEnter()              // uložit při Enteru
```

`saveUsing()` nahradí ukládání úplně — callback dostane záznam, novou hodnotu
a sloupec a je zodpovědný za uložení:

```php
TextInputColumn::make('name')
    ->saveUsing(fn ($record, $value, $column) => $record->forceFill(['name' => $value])->saveQuietly())
```

Na úspěšné uložení zareaguješ přes `afterStateUpdated()`:

```php
TextInputColumn::make('quantity')
    ->afterStateUpdated(fn ($record, $value) => $record->order->recalculateTotal())
```

## Transformace hodnot

`beforeSave()` běží jako poslední, až po vestavěných transformacích:

```php
TextInputColumn::make('code')
    ->trim()                     // odstraní okolní bílé znaky
    ->nullable()                 // uloží '' jako null
    ->uppercase()                // nebo ->lowercase()
    ->beforeSave(fn ($value, $record) => str_replace(' ', '-', $value))
```

Save pipeline běží v tomto pořadí: **trim → nullable → parsování čísla
(`money()`) → uppercase/lowercase → `beforeSave()`**.

Opačným směrem: `afterLoad()` tvaruje hodnotu vkládanou *do* inputu
a `displayFormat()` tvaruje read-only text zobrazený, když buňka není editovatelná:

```php
TextInputColumn::make('phone')
    ->afterLoad(fn ($value, $record) => $value ? '+420 '.$value : '')
    ->displayFormat(fn ($value, $record) => $value ?: '—')
```

## Vzhled inputu

```php
TextInputColumn::make('price')
    ->inputPrefix('$')
    ->inputSuffix('.00')
    ->helperText('Cena bez DPH')
    ->inputClass('font-mono text-right')
    ->autocomplete('off')
    ->autofocus()
```

## Řízení přístupu

```php
TextInputColumn::make('name')
    ->disabled(fn ($record) => $record->is_locked)   // bool nebo Closure
    ->readonly()                                     // bool nebo Closure
    ->editPermission('orders.edit')
```

`editPermission()` se vynucuje na serveru při každém uložení, ne jen v UI.
Vyhodnocuje se proti přihlášenému uživateli přes `hasPermissionTo()` nebo `can()`
a uživatel s rolí `Super Admin` projde vždy.

## API TextInputColumn

```php
// Typ
->type(string $type)                       // syrový HTML typ inputu
->numeric()                                // type="number"
->integer()                                // type="number", step="1"
->decimal(int $places = 2)                 // type="number", step odvozený z $places
->email() / ->tel() / ->url() / ->password()

// Formátovaná čísla
->money(int $decimals = 0, string $thousandsSeparator = ' ', string $decimalSeparator = ',')
->czk(int $decimals = 0)                   // alias pro money() s českým formátem

// Validace
->rules(array|Closure $rules)              // Laravel pravidla; Closure dostane záznam
->rule(string $rule)                       // přidá jedno pravidlo
->required(bool $required = true)          // přidá 'required'; false nedělá nic
->validationMessages(array $messages)
->validationAttribute(string $attribute)
->liveValidation(bool $live = true, int $debounce = 500)

// Nativní omezení inputu
->min(?string $min) / ->max(?string $max)
->minLength(?int $length) / ->maxLength(?int $length)
->pattern(?string $pattern)
->step(?string $step)

// Ukládání
->saveOnBlur(bool $save = true)
->saveOnEnter(bool $save = true)
->saveUsing(?Closure $callback)            // fn($record, $value, $column) — nahradí ukládání
->afterStateUpdated(?Closure $callback)    // fn($record, $value) — po úspěšném uložení
->editableUsing(Closure $callback)         // zděděné z Column

// Transformace hodnot
->trim(bool $trim = true)
->nullable(bool $nullable = true)          // '' se stane null
->uppercase(bool $uppercase = true) / ->lowercase(bool $lowercase = true)
->beforeSave(Closure $formatter)           // fn($value, $record) — běží poslední před uložením
->afterLoad(Closure $formatter)            // fn($value, $record) — tvaruje hodnotu v inputu
->displayFormat(Closure $formatter)        // fn($value, $record) — read-only zobrazení

// Vzhled
->inputPrefix(?string $prefix) / ->inputSuffix(?string $suffix)
->helperText(?string $text)
->inputClass(?string $class)
->autocomplete(?string $autocomplete)
->autofocus(bool $autofocus = true)

// Řízení přístupu
->disabled(bool|Closure $disabled = true)
->readonly(bool|Closure $readonly = true)
->editPermission(?string $permission)      // vynuceno na serveru při uložení
```
