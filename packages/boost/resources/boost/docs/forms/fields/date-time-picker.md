# DateTimePicker

Unified date/time picker with date, month, time, and datetime modes.

```php
use NyonCode\WireForms\Components\DateTimePicker;
```

## Modes

```php
// Date only
DateTimePicker::make('birth_date')->asDate()

// DateTime (default)
DateTimePicker::make('event_at')
DateTimePicker::make('event_at')->asDateTime()

// Time only
DateTimePicker::make('alarm')->asTime()

// Month + year only ("YYYY-MM")
DateTimePicker::make('period')->asMonth()

// Explicit mode setter
DateTimePicker::make('x')->mode('date')      // 'date', 'month', 'time', 'datetime'
```

> `asMonth()` always renders the browser-native `<input type="month">` — the custom
> calendar has no month-only grid — so it stays native even if you pass `->native(false)`.

## Date Constraints

```php
DateTimePicker::make('start')
    ->minDate('2024-01-01')
    ->maxDate('2025-12-31')
    ->disabledDates(['2024-12-25', '2024-12-31'])
    ->firstDayOfWeek(1)           // Monday
    ->closeOnDateSelection()
```

## Time Options

```php
DateTimePicker::make('meeting')
    ->withSeconds()
    ->hoursStep(1)
    ->minutesStep(15)
    ->secondsStep(30)
```

## Format

```php
DateTimePicker::make('date')
    ->format('Y-m-d')             // storage format
    ->displayFormat('d.m.Y')      // display format
    ->timezone('Europe/Prague')
```

> `format()` and `timezone()` are opt-in and apply on save. Left unset, the value
> is stored exactly as the widget produced it — so adding them to an existing field
> is a deliberate change of what lands in the column, never a silent one.
> `timezone()` converts in both directions and only for `datetime`: a bare date or
> time is a wall-clock value that a conversion would corrupt.

> `displayFormat()` uses PHP `date()` tokens and only changes what the user sees —
> the stored value is untouched. It is honoured by the custom picker; a native
> input's display format belongs to the browser and the user's locale.

## Native Picker

The custom Alpine picker is the default. Opt out to the browser's own control:

```php
DateTimePicker::make('date')
    ->native()                     // use the browser-native picker
    ->native(false)                // back to the custom picker (default)
```

The only exception is [`asMonth()`](#modes), which is always native.

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `mode(string)` | string | Set mode: `date`, `month`, `time`, `datetime` |
| `asDate()` | — | Alias for `mode('date')` |
| `asTime()` | — | Alias for `mode('time')` |
| `asMonth()` | — | Alias for `mode('month')`; always native |
| `asDateTime()` | — | Alias for `mode('datetime')` |
| `format(string)` | string | Storage format (Carbon compatible) |
| `displayFormat(string)` | string | Display format shown to the user |
| `minDate(string\|Closure)` | string | Minimum selectable date |
| `maxDate(string\|Closure)` | string | Maximum selectable date |
| `disabledDates(array\|Closure)` | array | Dates that cannot be selected |
| `firstDayOfWeek(int)` | int | 0=Sunday, 1=Monday |
| `closeOnDateSelection()` | bool | Close picker after a date is selected |
| `withSeconds()` | bool | Show seconds column in time picker |
| `hoursStep(int)` | int | Hour increment step |
| `minutesStep(int)` | int | Minute increment step |
| `secondsStep(int)` | int | Second increment step |
| `timezone(string)` | string | Show the value in this timezone and convert back to the app timezone on save; `datetime` only |
| `native(bool $native = true)` | bool | Use the browser-native control instead of the custom picker (default: `false`) |
| `disabled(bool\|Closure)` | bool | Disable the picker |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
