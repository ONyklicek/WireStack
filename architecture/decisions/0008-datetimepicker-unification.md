# ADR 0008: DateTimePicker Unification

## Status
Accepted — amended (see *Amendment: TimePicker as a second time UI*)

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

## Amendment: TimePicker as a second time UI

`TimePicker` exists again as a class, and — unlike the rest of this ADR — it is
**not** merely a renaming of an existing mode. It carries its own way of picking
a time.

```php
TimePicker::make('opens_at')             // slot list, 30-minute interval
DateTimePicker::make('opens_at')->asTime()  // hour/minute steppers
```

Both produce the same value in the same format. They differ in the panel:
`TimePicker` renders a scrollable list of times at a fixed interval (the
[Flux UI](https://fluxui.dev/components/time-picker) pattern), `asTime()` renders
the original steppers.

### What this costs

**This is a knowing exception to the rule the rest of this ADR exists to state.**
There are now two implementations of "pick a time" in one package: two Blade
views, two Alpine components, two sets of bounds logic, two browser drivers. That
is the exact duplication the original decision removed, and it can drift — a fix
to one clock is not a fix to the other.

It was accepted deliberately, with the trade-off on the table. Recorded so that a
later reader does not mistake it for an oversight and "consolidate" it back
without knowing it was asked for.

### What is shared anyway

Everything that is not the panel. `TimePicker extends DateTimePicker` and
inherits the state format (`H:i` / `H:i:s`), `withSeconds()`, the bounds
(`minDate()` / `maxDate()`, read as times), `displayFormat()`, the native
`<input type="time">` fallback, the mobile sheet, and hydration/dehydration. It
is an `instanceof DateTimePicker`, so the save pipeline and state hydrator see it
unchanged. The native branch is a shared partial
(`wire-forms::partials.date-time-native-input`) precisely because a duplicated
copy of it is the half that would silently drift.

Typing into the trigger is shared the same way. Both panels sit behind a box
showing a *formatted* value, so both need the inverse of that formatter to read a
typed string back, and that parser lives once in
`wire-forms::partials.date-time-typing`. What each view keeps is `applyTyped()` —
what a parsed set of numbers *means* here, a calendar day plus a clock or a clock
alone. The split is the rule this ADR states, applied to the one seam the
amendment above leaves open: a date parser that drifts by one format token stores
the wrong day without saying so.

The slot interval is the **inherited `minutesStep()`**, not a new `interval()`
setter — one concept keeps one name — defaulted to 30 in `TimePicker` because a
list at the picker-wide default of one minute would be 1440 rows long, and
floored at 1 because the view walks the day in these strides and a 0 would not
terminate.

### The mode is locked

`mode()` rejects anything but `time`, and with it the inherited `asDate()` /
`asMonth()` / `asDateTime()` aliases that route through it, throwing
`FormConfigurationException::fixedPickerMode()`. Here that is load-bearing rather
than cosmetic: `TimePicker`'s view renders a slot list and nothing else, so a
field that reached `date` mode would render a picker with no calendar in it.

**Unchanged:** `DateTimePicker::asTime()` keeps its steppers and is still the API
when the mode has to vary. Nothing is deprecated, and the migration table above
still holds — `TimePicker::make('x')` now works again as well.

**Not done:** `DatePicker` has no equivalent, and the `datetime` mode's time half
keeps its steppers rather than gaining a slot list.
