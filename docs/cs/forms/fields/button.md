# Button

Interaktivní tlačítko stylované design-systémem, které spouští closuru na serveru — podporovaná
alternativa k ručnímu psaní `<button>` uvnitř pole [Html](html.md) (které vykresluje raw markup
a obchází paletu).

```php
use NyonCode\WireForms\Components\Button;
```

## Základní použití

Closura `action()` běží s reaktivními accessory `$get` / `$set` / `$state` formuláře, takže
tlačítko může číst a zapisovat jiná pole:

```php
Button::make('generate_slug')
    ->label('Generate slug')
    ->icon('heroicon-o-sparkles')
    ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title'))))
```

## Vzhled

Prezentace je delegována na interní `Action`, takže tlačítko sdílí přesně stejné stylování a paletu
barev jako akce tabulky a modalu:

```php
Button::make('verify')
    ->label('Verify now')
    ->icon('heroicon-o-check', 'after')  // pozice ikony: 'before' (výchozí) nebo 'after'
    ->color('success')                   // libovolná barva palety
    ->size('lg')                         // 'xs' | 'sm' | 'md' | 'lg'
    ->outlined()
```

| Metoda | Účel |
|--------|---------|
| `label(string\|Closure)` | Text tlačítka |
| `icon(string, ?position)` | Přední (`'before'`) nebo koncová (`'after'`) ikona |
| `color(string\|Color\|Closure)` | Barva palety |
| `size(string)` | `xs` / `sm` / `md` / `lg` |
| `outlined(bool)` | Outlined místo solid |
| `action(Closure)` | Server callback, dostane `$get` / `$set` / `$state` / `$component` |

## Poznámky

- Tlačítko dispatchuje přes stejný endpoint `callFieldAction()` jako field
  [affix akce](../reactive-fields.md#field-actions-and-buttons); funguje v samostatném
  `WithForms` formuláři i uvnitř action modalu tabulky.
- Button není součástí stavu formuláře a nepřidává žádnou validaci — je to spouštěč, ne input.
- Respektuje `disabled()` a `visible()` jako jakékoli jiné pole.

Sdílené metody viz [Společné API pole](index.md#common-field-api).
