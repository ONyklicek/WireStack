# DateTimePicker

Jednotný date/time picker s režimy date, month, time a datetime.

```php
use NyonCode\WireForms\Components\DateTimePicker;
```

## Režimy

```php
// Jen datum
DateTimePicker::make('birth_date')->asDate()

// DateTime (výchozí)
DateTimePicker::make('event_at')
DateTimePicker::make('event_at')->asDateTime()

// Jen čas
DateTimePicker::make('alarm')->asTime()

// Jen měsíc + rok („YYYY-MM")
DateTimePicker::make('period')->asMonth()

// Explicitní setter režimu
DateTimePicker::make('x')->mode('date')      // 'date', 'month', 'time', 'datetime'
```

> `asMonth()` vždy renderuje nativní `<input type="month">` prohlížeče — vlastní kalendář
> nemá month-only mřížku — takže zůstane nativní i když předáš `->native(false)`.

## Omezení data

```php
DateTimePicker::make('start')
    ->minDate('2024-01-01')
    ->maxDate('2025-12-31')
    ->disabledDates(['2024-12-25', '2024-12-31'])
    ->firstDayOfWeek(1)           // pondělí
    ->closeOnDateSelection()
```

## Volby času

```php
DateTimePicker::make('meeting')
    ->withSeconds()
    ->hoursStep(1)
    ->minutesStep(15)
    ->secondsStep(30)
```

## Formát

```php
DateTimePicker::make('date')
    ->format('Y-m-d')             // formát uložení
    ->displayFormat('d.m.Y')      // formát zobrazení
    ->timezone('Europe/Prague')
```

> `format()` a `timezone()` jsou opt-in a uplatní se při uložení. Bez nich se
> hodnota uloží přesně tak, jak ji vyrobil widget — přidání na existující field je
> tedy vědomá změna toho, co skončí ve sloupci, nikdy tichá. `timezone()` převádí
> oběma směry a jen u `datetime`: samotné datum nebo čas jsou wall-clock hodnoty,
> které by převod poškodil.

> `displayFormat()` používá tokeny PHP `date()` a mění jen to, co vidí uživatel —
> uložená hodnota zůstává beze změny. Ctí ho vlastní picker; formát zobrazení
> nativního inputu patří prohlížeči a locale uživatele.

## Nativní picker

Výchozí je vlastní Alpine picker. Přepnutí na ovládání prohlížeče:

```php
DateTimePicker::make('date')
    ->native()                     // použít nativní picker prohlížeče
    ->native(false)                // zpět na vlastní picker (výchozí)
```

Jedinou výjimkou je [`asMonth()`](#rezimy), který je vždy nativní.

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `mode(string)` | string | Nastavit režim: `date`, `month`, `time`, `datetime` |
| `asDate()` | — | Alias pro `mode('date')` |
| `asTime()` | — | Alias pro `mode('time')` |
| `asMonth()` | — | Alias pro `mode('month')`; vždy nativní |
| `asDateTime()` | — | Alias pro `mode('datetime')` |
| `format(string)` | string | Formát uložení (Carbon kompatibilní) |
| `displayFormat(string)` | string | Formát zobrazení ukázaný uživateli |
| `minDate(string\|Closure)` | string | Minimální volitelné datum |
| `maxDate(string\|Closure)` | string | Maximální volitelné datum |
| `disabledDates(array\|Closure)` | array | Data, která nelze vybrat |
| `firstDayOfWeek(int)` | int | 0=neděle, 1=pondělí |
| `closeOnDateSelection()` | bool | Zavřít picker po výběru data |
| `withSeconds()` | bool | Zobrazit sloupec sekund v time pickeru |
| `hoursStep(int)` | int | Krok inkrementu hodin |
| `minutesStep(int)` | int | Krok inkrementu minut |
| `secondsStep(int)` | int | Krok inkrementu sekund |
| `timezone(string)` | string | Zobrazí hodnotu v této timezone a při uložení ji převede zpět do timezone aplikace; jen pro `datetime` |
| `native(bool $native = true)` | bool | Použít nativní ovládání prohlížeče místo vlastního pickeru (výchozí: `false`) |
| `disabled(bool\|Closure)` | bool | Znepřístupnit picker |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
