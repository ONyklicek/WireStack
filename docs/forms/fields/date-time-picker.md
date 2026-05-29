# DateTimePicker

Unified date/time picker with date, time, and datetime modes.

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

// Explicit mode setter
DateTimePicker::make('x')->mode('date')      // 'date', 'time', 'datetime'
```

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

## Native Picker

```php
DateTimePicker::make('date')
    ->native()                     // use browser native picker
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `mode(string)` | string | Set mode: `date`, `time`, `datetime` |
| `asDate()` | — | Alias for `mode('date')` |
| `asTime()` | — | Alias for `mode('time')` |
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
| `timezone(string)` | string | Timezone used for display |
| `native()` | bool | Use the browser-native picker |
| `disabled(bool\|Closure)` | bool | Disable the picker |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
