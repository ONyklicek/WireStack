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
serveru se stejným reaktivním `$get` / `$set` kontextem jako [`afterStateUpdated()`](../reactive-fields.md#field-actions-and-buttons)
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

Předejte třídu PHP enumu pro použití labelů jeho case jako návrhů (stejné resolvování labelu jako
[options `Select`](select.md#enum-options)):

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

Kompletní seznam sdílených metod viz [Společné API pole](index.md#common-field-api).
