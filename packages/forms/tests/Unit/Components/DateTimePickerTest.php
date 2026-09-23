<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireCore\Foundation\Enums\NativeControlMode;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Validation\Rules\DateWithinBounds;

test('default mode is datetime', function () {
    $field = DateTimePicker::make('created_at');

    expect($field->getMode())->toBe('datetime');
});

test('asDate sets mode to date', function () {
    $field = DateTimePicker::make('birth_date')->asDate();

    expect($field->getMode())->toBe('date')
        ->and($field->getNativeInputType())->toBe('date');
});

test('asTime sets mode to time', function () {
    $field = DateTimePicker::make('start')->asTime();

    expect($field->getMode())->toBe('time')
        ->and($field->getNativeInputType())->toBe('time');
});

test('asDateTime sets mode to datetime', function () {
    $field = DateTimePicker::make('event')->asDateTime();

    expect($field->getMode())->toBe('datetime')
        ->and($field->getNativeInputType())->toBe('datetime-local');
});

test('mode can be set directly', function () {
    $field = DateTimePicker::make('ts')->mode('date');

    expect($field->getMode())->toBe('date');
});

test('custom format overrides default', function () {
    $field = DateTimePicker::make('date')->format('d/m/Y');

    expect($field->getFormat())->toBe('d/m/Y');
});

test('state type is a mode-appropriate date string, not a Carbon (regression)', function () {
    expect(DateTimePicker::make('d')->asDate()->getStateType())->toBe('date:Y-m-d')
        ->and(DateTimePicker::make('d')->asTime()->getStateType())->toBe('date:H:i')
        ->and(DateTimePicker::make('d')->asDateTime()->getStateType())->toBe('date:Y-m-d\TH:i');
});

test('hydrator returns a formatted string for date: types (regression)', function () {
    $hydrator = new StateHydrator;

    // Carbon, raw string, and timestamp all reduce to a serializable string.
    expect($hydrator->hydrateValue(Carbon::parse('2026-06-23 14:30:00'), 'date:Y-m-d'))->toBe('2026-06-23')
        ->and($hydrator->hydrateValue('2026-06-23 14:30:00', 'date:Y-m-d\TH:i'))->toBe('2026-06-23T14:30')
        ->and($hydrator->hydrateValue(null, 'date:Y-m-d'))->toBeNull();
});

test('minDate and maxDate', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate('2024-01-01')
        ->maxDate('2024-12-31');

    expect($field->getMinDate())->toBe('2024-01-01')
        ->and($field->getMaxDate())->toBe('2024-12-31');
});

test('minDate and maxDate support closures', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate(fn () => '2024-06-01');

    expect($field->getMinDate())->toBe('2024-06-01');
});

test('bounds are reshaped to the mode the widget compares in', function () {
    // A native <input type="datetime-local"> drops a min it cannot read, and
    // the custom picker compares bounds as plain strings — either way the shape
    // has to match the widget, not whatever the owner found convenient.
    expect(DateTimePicker::make('d')->asDate()->minDate('10.07.2026')->getMinDate())->toBe('2026-07-10')
        ->and(DateTimePicker::make('d')->asMonth()->minDate('2026-07-10')->getMinDate())->toBe('2026-07')
        ->and(DateTimePicker::make('d')->asTime()->minDate('2026-07-10 08:30')->getMinDate())->toBe('08:30')
        ->and(DateTimePicker::make('d')->asTime()->withSeconds()->minDate('08:30:45')->getMinDate())->toBe('08:30:45')
        ->and(DateTimePicker::make('d')->asDateTime()->minDate('2026-07-10')->getMinDate())->toBe('2026-07-10T00:00')
        ->and(DateTimePicker::make('d')->asDateTime()->withSeconds()->minDate('2026-07-10 08:30')->getMinDate())
        ->toBe('2026-07-10T08:30:00');
});

test('bounds accept a Carbon instance', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate(Carbon::parse('2026-07-10 08:30'))
        ->maxDate(fn () => Carbon::parse('2026-07-20 17:00'));

    expect($field->getMinDate())->toBe('2026-07-10')
        ->and($field->getMaxDate())->toBe('2026-07-20');
});

test('bounds accept a relative date', function () {
    Carbon::setTestNow('2026-07-27 12:00:00');

    $field = DateTimePicker::make('date')->asDate()->minDate('today')->maxDate('+2 days');

    expect($field->getMinDate())->toBe('2026-07-27')
        ->and($field->getMaxDate())->toBe('2026-07-29');

    Carbon::setTestNow();
});

test('a day-granular maxDate leaves the whole day selectable on a datetime picker', function () {
    $field = DateTimePicker::make('date')->asDateTime()->maxDate('2026-07-20');

    expect($field->getMaxDate())->toBe('2026-07-20T23:59');
});

test('a maxDate that names a time keeps it', function () {
    $field = DateTimePicker::make('date')->asDateTime()->maxDate('2026-07-20 17:30');

    expect($field->getMaxDate())->toBe('2026-07-20T17:30');
});

test('disabled dates are reshaped to the calendar cells they must match', function () {
    $field = DateTimePicker::make('date')->disabledDates(['10.07.2026', Carbon::parse('2026-07-11')]);

    expect($field->getDisabledDates())->toBe(['2026-07-10', '2026-07-11']);
});

test('native mode', function () {
    $field = DateTimePicker::make('date')->native();

    expect($field->isNative())->toBeTrue();
});

test('defaults to the custom picker', function () {
    expect(DateTimePicker::make('date')->isNative())->toBeFalse();
});

test('native can be turned back off', function () {
    expect(DateTimePicker::make('date')->native()->native(false)->isNative())->toBeFalse();
});

// The custom calendar has no month-only grid, so month mode is the browser's
// <input type="month"> or nothing — it outranks an explicit ->native(false).
test('month mode forces the native control even over an explicit native(false)', function () {
    expect(DateTimePicker::make('date')->asMonth()->isNative())->toBeTrue()
        ->and(DateTimePicker::make('date')->asMonth()->native(false)->isNative())->toBeTrue();
});

test('other modes do not force the native control', function () {
    expect(DateTimePicker::make('date')->asDate()->isNative())->toBeFalse()
        ->and(DateTimePicker::make('date')->asTime()->isNative())->toBeFalse()
        ->and(DateTimePicker::make('date')->asDateTime()->isNative())->toBeFalse();
});

test('firstDayOfWeek defaults to config', function () {
    $field = DateTimePicker::make('date');

    expect($field->getFirstDayOfWeek())->toBeInt();
});

test('withSeconds', function () {
    $field = DateTimePicker::make('time')->asTime()->withSeconds();

    expect($field->hasSeconds())->toBeTrue();
});

test('time steps', function () {
    $field = DateTimePicker::make('time')
        ->hoursStep(2)
        ->minutesStep(15)
        ->secondsStep(30);

    expect($field->getHoursStep())->toBe(2)
        ->and($field->getMinutesStep())->toBe(15)
        ->and($field->getSecondsStep())->toBe(30);
});

test('timezone', function () {
    $field = DateTimePicker::make('event')->timezone('Europe/Prague');

    expect($field->getTimezone())->toBe('Europe/Prague');
});

test('disabled dates', function () {
    $field = DateTimePicker::make('date')->disabledDates(['2024-12-25', '2024-12-26']);

    expect($field->getDisabledDates())->toBe(['2024-12-25', '2024-12-26']);
});

test('close on date selection', function () {
    $field = DateTimePicker::make('date')->closeOnDateSelection();

    expect($field->shouldCloseOnDateSelection())->toBeTrue();
});

function renderPickerView(DateTimePicker $field): string
{
    // The field wrapper reads $errors from the view bag, which only a real
    // request/view context provides.
    return view($field->render()->name(), ['field' => $field])
        ->withErrors(new MessageBag)
        ->render();
}

// Regression: displayFormat() was a dead setter — nothing read it, so the input
// always showed the raw state (2026-03-09T14:05). The custom picker now formats
// it; the native input cannot (the browser owns that, per the user's locale).
test('the custom picker receives displayFormat', function () {
    $field = DateTimePicker::make('event_at')->displayFormat('j. n. Y H:i');

    expect($field->getDisplayFormat())->toBe('j. n. Y H:i')
        ->and(renderPickerView($field))->toContain("displayFormat: 'j. n. Y H:i'");
});

// A browser ignores a min it cannot read, without a word in the console — a
// datetime-local input needs 'Y-m-d\TH:i', a month input 'Y-m'.
test('a native input is given bounds in the shape its type accepts', function () {
    expect(renderPickerView(DateTimePicker::make('d')->native()->minDate('10.07.2026')->maxDate('2026-07-20')))
        ->toContain('min="2026-07-10T00:00"')
        ->toContain('max="2026-07-20T23:59"')
        ->and(renderPickerView(DateTimePicker::make('d')->asDate()->native()->minDate('10.07.2026')))
        ->toContain('min="2026-07-10"')
        ->and(renderPickerView(DateTimePicker::make('d')->asMonth()->minDate('2026-07-10')))
        ->toContain('min="2026-07"');
});

test('the custom picker gets the calendar half and the clock half of each bound', function () {
    $html = renderPickerView(DateTimePicker::make('d')->minDate('2026-07-10 08:30')->maxDate('2026-07-20 17:00'));

    expect($html)->toContain("minDay: '2026-07-10'")
        ->toContain("maxDay: '2026-07-20'")
        ->toContain("minTime: '08:30:00'")
        ->toContain("maxTime: '17:00:00'");
});

test('a date-only picker is given no clock bounds to enforce', function () {
    $html = renderPickerView(DateTimePicker::make('d')->asDate()->minDate('2026-07-10 08:30'));

    expect($html)->toContain("minDay: '2026-07-10'")
        ->toContain('minTime: null');
});

test('a native input is not given a displayFormat to honour', function () {
    $field = DateTimePicker::make('event_at')->native()->displayFormat('j. n. Y H:i');

    expect(renderPickerView($field))->not->toContain('displayFormat:');
});

// closeOnDateSelection() was a dead setter: the picker closed only when the field
// had no time part, and the flag was never read.
test('the picker is told whether to close on date selection', function () {
    expect(renderPickerView(DateTimePicker::make('d')->closeOnDateSelection()))
        ->toContain('closeOnDateSelection: true')
        ->and(renderPickerView(DateTimePicker::make('d')))
        ->toContain('closeOnDateSelection: false');
});

// ─── Format resolution (what the input and the parser agree on) ───────

test('each mode resolves its own storage format', function (string $mode, string $expected) {
    // Pinned rather than relying on whatever the config happens to hold: the
    // suite runs in random order, so a neighbouring test's config() write would
    // otherwise decide the outcome. Month is the one format with no config key.
    config()->set('wire-forms.date_format', 'Y-m-d');
    config()->set('wire-forms.time_format', 'H:i');
    config()->set('wire-forms.datetime_format', 'Y-m-d H:i');

    expect(DateTimePicker::make('at')->mode($mode)->getFormat())->toBe($expected);
})->with([
    'date' => ['date', 'Y-m-d'],
    'month' => ['month', 'Y-m'],
    'time' => ['time', 'H:i'],
    'datetime' => ['datetime', 'Y-m-d H:i'],
]);

test('seconds extend the time formats, in both modes that carry a clock', function () {
    config()->set('wire-forms.time_format', 'H:i');

    expect(DateTimePicker::make('at')->asTime()->withSeconds()->getFormat())->toBe('H:i:s')
        ->and(DateTimePicker::make('at')->asTime()->getFormat())->toBe('H:i')
        // getStateType() is the serialisable shape Livewire round-trips, and it
        // uses the T separator so a native datetime-local input accepts it.
        ->and(DateTimePicker::make('at')->asDateTime()->withSeconds()->getStateType())->toBe('date:Y-m-d\TH:i:s')
        ->and(DateTimePicker::make('at')->asDateTime()->getStateType())->toBe('date:Y-m-d\TH:i')
        ->and(DateTimePicker::make('at')->asTime()->withSeconds()->getStateType())->toBe('date:H:i:s')
        ->and(DateTimePicker::make('at')->asMonth()->getStateType())->toBe('date:Y-m');
});

test('an explicit format wins over the mode default', function () {
    expect(DateTimePicker::make('at')->asDate()->format('d.m.Y')->getFormat())->toBe('d.m.Y');
});

test('the configured formats are used when set', function () {
    config()->set('wire-forms.date_format', 'd/m/Y');
    config()->set('wire-forms.time_format', 'H.i');
    config()->set('wire-forms.datetime_format', 'd/m/Y H.i');

    expect(DateTimePicker::make('at')->asDate()->getFormat())->toBe('d/m/Y')
        ->and(DateTimePicker::make('at')->asTime()->getFormat())->toBe('H.i')
        ->and(DateTimePicker::make('at')->asTime()->withSeconds()->getFormat())->toBe('H.i:s')
        ->and(DateTimePicker::make('at')->asDateTime()->getFormat())->toBe('d/m/Y H.i');
});

// ─── First day of the week ───────────────────────────────────────────

test('the first day of the week comes from config, and can be set per field', function () {
    config()->set('wire-forms.first_day_of_week', 0);   // Sunday

    expect(DateTimePicker::make('at')->getFirstDayOfWeek())->toBe(0)
        // An explicit value wins, and 0 is a real answer rather than "unset".
        ->and(DateTimePicker::make('at')->firstDayOfWeek(1)->getFirstDayOfWeek())->toBe(1)
        ->and(DateTimePicker::make('at')->firstDayOfWeek(6)->getFirstDayOfWeek())->toBe(6)
        // null clears it again, back to the configured value.
        ->and(DateTimePicker::make('at')->firstDayOfWeek(3)->firstDayOfWeek(null)->getFirstDayOfWeek())->toBe(0);
});

// ─── Typing into the trigger ─────────────────────────────────────────

// Regression: the trigger carried an unconditional `readonly`, so the calendar
// and the steppers were the only route to a value — setting a date three years
// out meant 36 clicks on the month arrow — and `readOnly()` was a no-op, because
// nothing in the view ever read it while the panel stayed fully interactive.

test('the trigger accepts typing by default', function () {
    $html = renderPickerView(DateTimePicker::make('event_at'));

    expect($html)->not->toContain('readonly')
        ->toContain('@input="onTyped($event.target.value)"')
        ->toContain('@blur="commitTyped()"');
});

test('typeable(false) hands the value back to the picker alone', function () {
    $field = DateTimePicker::make('event_at')->typeable(false);

    expect($field->acceptsTypedInput())->toBeFalse()
        ->and(renderPickerView($field))
        ->toContain('readonly')
        ->not->toContain('onTyped(');
});

// readOnly() outranks typeable(): it means the value is not the user's to
// change, and a keyboard is a way to change it.
test('readOnly closes the keyboard route and the panel with it', function () {
    $field = DateTimePicker::make('event_at')->readOnly();
    $html = renderPickerView($field);

    expect($field->acceptsTypedInput())->toBeFalse()
        ->and($html)->toContain('readonly')
        ->not->toContain('onTyped(')
        // Nothing may open the panel — leaving it reachable was the half that
        // made readOnly() decorative, since the calendar wrote to the state
        // regardless.
        ->not->toContain('@click="open = true"')
        ->not->toContain('@keydown.down.prevent="open = true"')
        // The chevron is the other way in, and a disabled button is not a way in.
        ->toContain('data-testid="form-datetime-event_at-toggle"')
        ->toMatch('/form-datetime-event_at-toggle.*?\sdisabled\s/s');
});

test('a disabled picker is not typeable either', function () {
    expect(DateTimePicker::make('event_at')->disabled()->acceptsTypedInput())->toBeFalse();
});

test('typeable accepts a closure, like every other conditional setter', function () {
    expect(DateTimePicker::make('event_at')->typeable(fn (): bool => false)->acceptsTypedInput())->toBeFalse();
});

// The parser inverts whichever format the box is actually showing, so the two
// have to be resolved from the same place.
test('the typed format is the display format when there is one, and the state shape otherwise', function () {
    expect(DateTimePicker::make('at')->displayFormat('j. n. Y H:i')->getTypedFormat())->toBe('j. n. Y H:i')
        ->and(DateTimePicker::make('at')->getTypedFormat())->toBe('Y-m-d\TH:i')
        ->and(DateTimePicker::make('at')->withSeconds()->getTypedFormat())->toBe('Y-m-d\TH:i:s')
        ->and(DateTimePicker::make('at')->asDate()->getTypedFormat())->toBe('Y-m-d')
        ->and(DateTimePicker::make('at')->asTime()->getTypedFormat())->toBe('H:i');
});

test('the parser is given the format, and the shared module that reads it', function () {
    // The format is the per-instance half and stays in the markup; the parser and
    // the picker's own applyTyped() are `wireDateTimePicker` in the fields bundle,
    // sharing one `typing.js` with TimePicker so the two cannot drift.
    $html = renderPickerView(DateTimePicker::make('at')->displayFormat('d.m.Y H:i'));

    expect($html)->toContain("typedFormat: 'd.m.Y H:i'")
        ->toContain('typeable: true');

    expect(file_get_contents(__DIR__.'/../../../resources/js/fields/typing.js'))
        ->toContain('readTyped(text)');
    expect(file_get_contents(__DIR__.'/../../../resources/js/fields/date-time-picker.js'))
        ->toContain('applyTyped(parts)');
});

// The panel could not be opened from the keyboard at all: the trigger listened
// for clicks only, and the icon was a pointer-events-none div.
test('the panel opens from the keyboard and from a real toggle button', function () {
    $html = renderPickerView(DateTimePicker::make('event_at'));

    expect($html)->toContain('@keydown.down.prevent="open = true"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain(':aria-expanded="open ? \'true\' : \'false\'"')
        ->toContain('data-testid="form-datetime-event_at-toggle"');
});

// A native control's keyboard is the browser's, and the only way to take it
// away is `readonly` — which would disable the browser's own picker too.
test('a native input keeps the browser keyboard whatever typeable says', function () {
    expect(renderPickerView(DateTimePicker::make('at')->native()->typeable(false)))
        ->not->toContain('readonly')
        ->not->toContain('onTyped(');
});

// ─── Native step and native on mobile ────────────────────────────────────────

test('the native step follows the finest configured time unit', function () {
    expect(DateTimePicker::make('d')->asDate()->getNativeStep())->toBeNull()
        ->and(DateTimePicker::make('d')->asMonth()->getNativeStep())->toBeNull()
        ->and(DateTimePicker::make('d')->getNativeStep())->toBeNull()
        ->and(DateTimePicker::make('d')->asTime()->withSeconds()->getNativeStep())->toBe(1)
        ->and(DateTimePicker::make('d')->asTime()->withSeconds()->secondsStep(15)->getNativeStep())->toBe(15)
        ->and(DateTimePicker::make('d')->minutesStep(15)->getNativeStep())->toBe(900)
        ->and(DateTimePicker::make('d')->hoursStep(2)->getNativeStep())->toBe(7200);
});

// Regression: a native time with seconds dropped them — the browser's default
// step is a minute, and the input carried no step at all.
test('a native time with seconds asks the browser for seconds', function () {
    expect(renderPickerView(DateTimePicker::make('d')->asTime()->withSeconds()->native()))
        ->toContain('type="time"')
        ->toContain('step="1"');
});

test('nativeOnMobile() renders the picker and a native twin split at the breakpoint', function () {
    $html = renderPickerView(DateTimePicker::make('d')->label('When')->asDate()->required()->nativeOnMobile());

    expect($html)
        ->toContain('<div class="hidden max-sm:block">')
        ->toContain('max-sm:hidden')
        ->toContain('wireDateTimePicker(')
        ->toContain('type="date"')
        ->toContain('id="d-native"')
        ->toContain('aria-label="When"')
        ->toContain('aria-required="true"')
        // Neither half may block the form while the other is the visible one.
        ->not->toMatch('/<input[^>]*\srequired[\s>\/]/')
        // The calendar never becomes a sheet on a screen the native input owns.
        ->toContain('sheetOnMobile: false');
});

// A phone gets its own date wheel even with disabled dates: the wheel cannot
// grey them out, but the server rule refuses them, and the custom calendar is
// the harder control to use on a phone.
test('a picker with disabled dates still goes native on a phone', function () {
    $field = DateTimePicker::make('d')->asDate()->disabledDates(['2026-07-10'])->nativeOnMobile();

    expect($field->isNativeOnMobile())->toBeTrue()
        ->and(renderPickerView($field))->toContain('type="date"');
});

test('month mode is native everywhere, never split', function () {
    expect(DateTimePicker::make('d')->asMonth()->nativeOnMobile()->getNativeControlMode())
        ->toBe(NativeControlMode::Always);
});

// ─── Server-side bounds ──────────────────────────────────────────────────────

// Regression: minDate(), maxDate() and disabledDates() were drawn by the picker
// only. A typed value, or a phone's own wheel (iOS ignores min/max), saved
// whatever it was given.
test('the picker bounds are held on the server too', function () {
    $rules = DateTimePicker::make('d')->asDate()
        ->minDate('2026-07-10')->maxDate('2026-07-20')->disabledDates(['2026-07-15'])
        ->getValidationRules();

    $messages = fn (mixed $value): array => Validator::make(['d' => $value], ['d' => $rules])->errors()->get('d');

    expect($rules[0])->toBe('nullable')
        ->and($rules[1])->toBeInstanceOf(DateWithinBounds::class)
        ->and($messages('2026-07-12'))->toBe([])
        ->and($messages(null))->toBe([])
        ->and($messages('2026-07-09'))->toBe(['The d must not be earlier than 2026-07-10.'])
        ->and($messages('2026-07-21'))->toBe(['The d must not be later than 2026-07-20.'])
        ->and($messages('2026-07-15'))->toBe(['The selected d is not available.'])
        ->and($messages('not a date'))->toBe(['The d is not a valid date.']);
});

test('a datetime bound compares instants, and names itself as the user reads it', function () {
    $rules = DateTimePicker::make('d')->minDate('2026-07-10 08:30')->displayFormat('j. n. Y H:i')->required()->getValidationRules();
    $messages = fn (mixed $value): array => Validator::make(['d' => $value], ['d' => $rules])->errors()->get('d');

    expect($messages('2026-07-10T08:30'))->toBe([])
        ->and($messages('2026-07-10T08:30:00'))->toBe([])
        ->and($messages('2026-07-10T08:29'))->toBe(['The d must not be earlier than 10. 7. 2026 08:30.']);
});

test('a time field holds its clock bounds', function () {
    $rules = DateTimePicker::make('t')->asTime()->minDate('08:00')->maxDate('17:00')->getValidationRules();
    $passes = fn (mixed $value): bool => Validator::make(['t' => $value], ['t' => $rules])->passes();

    expect($passes('08:00'))->toBeTrue()
        ->and($passes('17:00'))->toBeTrue()
        ->and($passes('07:59'))->toBeFalse()
        ->and($passes('17:01'))->toBeFalse();
});

test('a picker with nothing to hold adds no rule', function () {
    expect(DateTimePicker::make('d')->getValidationRules())->toBe([]);
});

test('every picker goes native on a phone, whatever its step or seconds', function () {
    expect(DateTimePicker::make('d')->minutesStep(15)->nativeOnMobile()->isNativeOnMobile())->toBeTrue()
        ->and(DateTimePicker::make('d')->asTime()->hoursStep(2)->nativeOnMobile()->isNativeOnMobile())->toBeTrue()
        ->and(DateTimePicker::make('d')->asTime()->withSeconds()->nativeOnMobile()->isNativeOnMobile())->toBeTrue();
});

test('a clock step decides the native control: slots, not a time input', function () {
    expect(DateTimePicker::make('d')->getSlotInterval())->toBeNull()
        ->and(DateTimePicker::make('d')->asDate()->minutesStep(15)->getSlotInterval())->toBeNull()
        ->and(DateTimePicker::make('d')->minutesStep(15)->getSlotInterval())->toBe(15)
        ->and(DateTimePicker::make('d')->asTime()->hoursStep(2)->getSlotInterval())->toBe(120)
        ->and(DateTimePicker::make('d')->minutesStep(1)->usesNativeSlots())->toBeFalse();
});

// iOS ignores a time input's step, so a stepped datetime is a native date input
// beside a native <select> of the slots, joined by wireNativeDateTime.
test('a stepped datetime renders a native date and a native slot select', function () {
    $html = renderPickerView(DateTimePicker::make('d')->minutesStep(15)->minDate('2026-07-10 08:30')->native());

    expect($html)
        ->toContain('wireNativeDateTime(')
        ->toContain('type="date"')
        ->toContain('min="2026-07-10"')
        ->toContain('x-model="date"')
        ->toContain('x-model="time"')
        ->toMatch('/<option value="08:15"\s*>08:15/')
        ->not->toContain('type="datetime-local"');
});

test('a stepped time renders a native slot select', function () {
    $html = renderPickerView(DateTimePicker::make('t')->asTime()->minutesStep(20)->minDate('08:00')->maxDate('09:00')->native());

    expect($html)
        ->toContain('<select')
        ->toMatch('/<option value="08:20"\s*>08:20/')
        ->not->toContain('<option value="09:20"')
        ->not->toContain('type="time"');
});

test('the controller joins both halves into the one state, or nothing', function () {
    $controller = file_get_contents(__DIR__.'/../../../resources/js/fields/native-date-time.js');

    expect($controller)
        ->toContain('return this.date && this.time ? `${this.date}T${this.time}` : null')
        ->and(file_get_contents(__DIR__.'/../../../dist/wire-forms-fields.js'))->toContain('wireNativeDateTime');
});
