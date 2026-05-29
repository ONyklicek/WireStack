# ADR 0008: DateTimePicker Unification

## Status
Accepted

## Context
The original `wire-table` had three separate classes: `DatePicker`, `DateTimePicker`, `TimePicker`. They shared 90% of their code and differed only in which parts of the date/time were shown.

## Decision
**One unified `DateTimePicker` class** with a `mode()` selector.

```php
DateTimePicker::make('birth_date')->mode('date');
DateTimePicker::make('event_at')->mode('datetime');   // default
DateTimePicker::make('alarm')->mode('time');
```

Helper aliases for readability:
```php
DateTimePicker::make('birth_date')->asDate();
DateTimePicker::make('event_at')->asDateTime();
DateTimePicker::make('alarm')->asTime();
```

Default mode is `datetime`.

### Migration from old API
| Before | After |
|--------|-------|
| `DatePicker::make('x')` | `DateTimePicker::make('x')->asDate()` |
| `DateTimePicker::make('x')` | `DateTimePicker::make('x')` (unchanged) |
| `TimePicker::make('x')` | `DateTimePicker::make('x')->asTime()` |

All shared API (`minDate`, `maxDate`, `format`, `displayFormat`, `timezone`, `firstDayOfWeek`, `withoutSeconds`) works across all modes where applicable.

## Consequences
- **Good:** One class to maintain, document, and test.
- **Good:** Consistent with FormKit's unified approach.
- **Trade-off:** Slightly longer class name for date-only use. Mitigated by `asDate()` helper.
