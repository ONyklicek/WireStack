# DateTimePicker

Unified date/time picker with mode selector. Replaces the old `DatePicker`, `DateTimePicker`, and `TimePicker` classes (see [ADR 0008](../../decisions/0008-datetimepicker-unification.md)).

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

| Method | Description |
|--------|-------------|
| `mode(string)` | Set mode: `date`, `time`, `datetime` |
| `asDate()` | Alias for `mode('date')` |
| `asTime()` | Alias for `mode('time')` |
| `asDateTime()` | Alias for `mode('datetime')` |
| `format(string)` | Storage format (Carbon compatible) |
| `displayFormat(string)` | Display format |
| `minDate(string)` | Minimum selectable date |
| `maxDate(string)` | Maximum selectable date |
| `disabledDates(array)` | Dates that cannot be selected |
| `firstDayOfWeek(int)` | 0=Sunday, 1=Monday |
| `closeOnDateSelection()` | Close picker after date selection |
| `withSeconds()` | Show seconds in time picker |
| `hoursStep(int)` | Hour increment step |
| `minutesStep(int)` | Minute increment step |
| `secondsStep(int)` | Second increment step |
| `timezone(string)` | Timezone for display |
| `native()` | Use browser native picker |
