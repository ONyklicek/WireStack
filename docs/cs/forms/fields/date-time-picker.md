# DateTimePicker

Jednotný date/time picker s režimy date, time a datetime.

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

// Explicitní setter režimu
DateTimePicker::make('x')->mode('date')      // 'date', 'time', 'datetime'
```

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

## Nativní picker

```php
DateTimePicker::make('date')
    ->native()                     // použít nativní picker prohlížeče
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `mode(string)` | string | Nastavit režim: `date`, `time`, `datetime` |
| `asDate()` | — | Alias pro `mode('date')` |
| `asTime()` | — | Alias pro `mode('time')` |
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
| `timezone(string)` | string | Timezone použitá pro zobrazení |
| `native()` | bool | Použít nativní picker prohlížeče |
| `disabled(bool\|Closure)` | bool | Znepřístupnit picker |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).
