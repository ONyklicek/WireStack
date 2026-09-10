---
summary: Výchozí pole — text, e-mail, heslo, číslo, telefon nebo URL — s prefixy, maskami a pravidly, která každá varianta implikuje.
---

# TextInput

Textové vstupní pole s variantami pro email, heslo, číslo, tel a URL.

```php
use NyonCode\WireForms\Components\TextInput;
```

## Základní použití

```php
TextInput::make('name')
TextInput::make('email')->email()
TextInput::make('password')->password()
TextInput::make('phone')->tel()
TextInput::make('website')->url()
TextInput::make('quantity')->numeric()
TextInput::make('age')->integer()
```

## Varianty typu

| Metoda | HTML typ | Popis |
|--------|-----------|-------------|
| `email()` | `email` | Nápověda validace emailu |
| `password()` | `password` | Maskovaný input |
| `tel()` | `tel` | Telefonní číslo |
| `url()` | `url` | URL input |
| `numeric()` | `number` s inputmode | Číslo s desetinnou částí |
| `integer()` | `number` s inputmode | Jen celé číslo, step=1 |
| `search()` | `search` | Search input |
| `type(string)` | Vlastní | Nastavit HTML input typ přímo |

## Omezení

```php
TextInput::make('code')
    ->minLength(3)
    ->maxLength(10)
    ->minValue(0)
    ->maxValue(100)
    ->step('0.01')
    ->mask('999-999-999')
    ->inputMode('numeric')
    ->autocomplete('off')
```

### Dynamická maska

Vzor, který se mění spolu s hodnotou, se zapisuje jako Alpine výraz, který
vyhodnocuje `x-mask:dynamic`. Přepočítá se při každém stisku klávesy a má
přednost před `mask()`:

```php
TextInput::make('card')
    ->dynamicMask("$input.startsWith('34') ? '9999 999999 99999' : '9999 9999 9999 9999'")
```

Pro částku sáhněte raději po [MoneyInput](money-input.md) — nastaví stejný druh
masky podle měny a napsanou částku přečte zpět jako číslo.

## Prázdné hodnoty

Prohlížeč nemá jak odeslat „nic“. Vymazaný `<input>` dorazí jako prázdný řetězec
a co to má znamenat, závisí čistě na sloupci za ním:

```php
TextInput::make('price')->numeric()       // vymazáno -> null, bez ptaní
TextInput::make('note')                   // vymazáno -> '', což je hodnota
TextInput::make('reference')->nullable()  // vymazáno -> null, protože sis o to řekl
```

**Číselný input nulluje sám od sebe.** `''` není číslo: Postgres a MySQL ve
strict módu ho na číselném sloupci odmítnou a SQLite tiše uloží prázdný řetězec
vedle desetinných míst. Neexistuje čtení vymazaného pole `type=number`, ve kterém
by autor myslel „prázdný řetězec“, takže `numeric()`, `integer()` i přímé
`type('number')` zapíšou `null`. Nula zůstane nedotčená — `0` je číslo, ne prázdná
hodnota.

**Každému jinému typu se to musí říct.** Na textovém sloupci je `''` naprosto
platná hodnota a `NOT NULL` sloupec by `null` napsaný za autora odmítl, takže o něj
říká `->nullable()`. Je to stejná metoda se stejným významem jako
[`TextInputColumn::nullable()`](../../table/columns/text-input.md) v editovatelné
buňce tabulky.

Běží to cestou k záznamu, takže to platí všude, kam hodnota míří — u uloženého
formuláře i u [action modalu](../../core/actions/index.md), který svá data předává
callbacku. Toho, na co je navázaný prohlížeč, se to netýká: pole dál zobrazuje
prázdný input, ne `null`.

## Dekorátory

```php
TextInput::make('price')
    ->prefix('CZK')
    ->suffix('.00')
    ->prefixIcon('currency')
    ->suffixIcon('calculator')
```

## Affix a hint akce

Umístěte interaktivní `Action` před/za input nebo vedle hintu. Callback běží na
serveru se stejným reaktivním `$get` / `$set` kontextem jako [`afterStateUpdated()`](../reactive-fields.md#field-akce-a-tlacitka)
— použijte ho pro lookupy (ARES, ověření adresy), generování hodnoty z jiného pole nebo
inline akci:

```php
use NyonCode\WireCore\Actions\Action;

TextInput::make('company')
    ->suffixAction(
        Action::make('lookup')
            ->icon('heroicon-o-magnifying-glass')
            ->action(fn ($get, $set) => $set('company', lookupCompany($get('company')))),
    )
    ->hintAction(
        Action::make('help')->icon('heroicon-o-question-mark-circle'),
    );
```

`prefixAction()`, `suffixAction()` a `hintAction()` každá bere `Action` a sdílejí
state kontext pole. Pro samostatné tlačítko použijte pole [`Button`](button.md).

## Odhalitelné heslo

```php
TextInput::make('password')
    ->password()
    ->revealable()    // tlačítko přepnutí viditelnosti
```

## Datalist

```php
TextInput::make('city')
    ->datalist(['Prague', 'Brno', 'Ostrava'])
```

Předejte třídu PHP enumu pro použití popisků jeho case jako návrhů (stejné resolvování popisku jako
[možnosti `Select`](select.md#moznosti-z-enumu)):

```php
TextInput::make('city')->datalist(City::class)
```

## Live aktualizace

```php
TextInput::make('search')
    ->live()
    ->debounce(300)
```

## Validace

```php
TextInput::make('username')
    ->required()
    ->rules(['alpha_dash', 'min:3', 'max:30'])
    ->validationMessages(['required' => 'Username is required'])
```

## Běžné volby

```php
TextInput::make('bio')
    ->label('Short bio')
    ->helperText('Displayed on your profile')
    ->hint('Max 255 chars')
    ->placeholder('Tell us about yourself')
    ->disabled(fn () => $this->locked)
    ->readOnly(fn () => ! auth()->user()->canEdit())
    ->autofocus()
```

Kompletní seznam sdílených metod viz [Společné API pole](index.md#spolecne-api-pole).
