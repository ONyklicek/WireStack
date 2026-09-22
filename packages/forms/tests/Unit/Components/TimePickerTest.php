<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\TimePicker;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

function renderTimePickerView(TimePicker $field): string
{
    return view($field->render()->name(), ['field' => $field])
        ->withErrors(new MessageBag)
        ->render();
}

test('is a time picker without being told', function () {
    $field = TimePicker::make('opens_at');

    expect($field->getMode())->toBe('time')
        ->and($field->getNativeInputType())->toBe('time')
        ->and($field->getStateType())->toBe('date:H:i');
});

test('is a DateTimePicker, so everything keyed off that type still sees it', function () {
    expect(TimePicker::make('opens_at'))->toBeInstanceOf(DateTimePicker::class);
});

test('carries the inherited time API rather than a parallel one', function () {
    $field = TimePicker::make('opens_at')
        ->withSeconds()
        ->minutesStep(15);

    expect($field->hasSeconds())->toBeTrue()
        ->and($field->getStateType())->toBe('date:H:i:s')
        ->and($field->getFormat())->toBe('H:i:s')
        ->and($field->getMinutesStep())->toBe(15);
});

test('the slot interval is minutesStep, defaulted to 30 rather than to every minute', function () {
    expect(TimePicker::make('opens_at')->getMinutesStep())->toBe(TimePicker::DEFAULT_INTERVAL_MINUTES)
        ->and(TimePicker::make('opens_at')->getMinutesStep())->toBe(30)
        ->and(TimePicker::make('opens_at')->minutesStep(15)->getMinutesStep())->toBe(15);
});

test('an interval below a minute is refused — the view walks the day in these strides', function () {
    // Not cosmetic: a 0 would not render an empty list, it would not terminate.
    expect(TimePicker::make('opens_at')->minutesStep(0)->getMinutesStep())->toBe(1)
        ->and(TimePicker::make('opens_at')->minutesStep(-5)->getMinutesStep())->toBe(1);
});

test('reads bounds as times, dropping the day half like time mode does', function () {
    expect(TimePicker::make('opens_at')->minDate('2026-07-10 08:30')->getMinDate())->toBe('08:30')
        ->and(TimePicker::make('opens_at')->maxDate('17:00')->getMaxDate())->toBe('17:00');
});

test('renders its own slot list, not the stepper picker', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at'));

    expect(TimePicker::make('opens_at')->isNative())->toBeFalse()
        ->and($html)->toContain('form-time-opens_at-list')
        ->and($html)->toContain('interval: 30')
        // None of the stepper picker's chrome comes along.
        ->and($html)->not->toContain('-hours-up')
        ->and($html)->not->toContain('-minutes-up')
        ->and($html)->not->toContain('-prev-month');
});

test('asTime() keeps the steppers — the two pickers are separate on purpose', function () {
    $html = view(DateTimePicker::make('opens_at')->asTime()->render()->name(), [
        'field' => DateTimePicker::make('opens_at')->asTime(),
    ])->withErrors(new MessageBag)->render();

    expect($html)->toContain('-hours-up')
        ->and($html)->not->toContain('-list');
});

// Not <input type="time">: its step never reaches a phone's wheel (iOS offers
// every minute), so a 30-minute field took 09:17 and then refused it.
test('renders a native select of the slots when asked for one', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at')->native()->minDate('08:00')->maxDate('17:00'));

    expect($html)
        ->toContain('<select')
        ->toContain('<option value="08:00"')
        ->toContain('<option value="17:00"')
        ->not->toContain('<option value="07:30"')
        ->not->toContain('<option value="17:30"')
        ->not->toContain('type="time"')
        ->not->toContain('form-time-opens_at-list');
});

test('bounds reach the list as seconds-padded times, the shape a slot compares against', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at')->minDate('08:00')->maxDate('17:00'));

    expect($html)->toContain("minTime: '08:00:00'")
        ->and($html)->toContain("maxTime: '17:00:00'");
});

test('accepts the mode it already is', function () {
    expect(TimePicker::make('opens_at')->mode('time')->getMode())->toBe('time')
        ->and(TimePicker::make('opens_at')->asTime()->getMode())->toBe('time');
});

test('refuses to become another mode instead of quietly rendering a calendar', function (string $method) {
    expect(fn () => TimePicker::make('opens_at')->{$method}())
        ->toThrow(FormConfigurationException::class);
})->with(['asDate', 'asMonth', 'asDateTime']);

test('refuses an explicit foreign mode, naming the class and both modes', function () {
    expect(fn () => TimePicker::make('opens_at')->mode('date'))
        ->toThrow(
            FormConfigurationException::class,
            '['.TimePicker::class.'] is locked to the [time] picker mode and cannot be switched to [date].',
        );
});

test('a subclass inherits the lock under its own name', function () {
    $subclass = new class('opens_at') extends TimePicker {};

    expect(fn () => $subclass->asDate())
        ->toThrow(FormConfigurationException::class, '['.$subclass::class.']');
});

// ─── Typing into the trigger ─────────────────────────────────────────

// The slot list is a convenience, not the vocabulary: at a 30-minute interval
// there was no way at all to express 08:07, because the trigger was readonly.
test('the trigger accepts a typed time, inheriting the shared parser', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at'));

    expect($html)->not->toContain('readonly')
        ->toContain('@input="onTyped($event.target.value)"')
        ->toContain('typeable: true')
        // No displayFormat, so the box shows the state and the parser reads it.
        ->toContain("typedFormat: 'H:i'");

    // The parser is the shared module; applyTyped() is this picker's own reading
    // of it. Both moved into the bundle with the rest of the controller.
    expect(file_get_contents(__DIR__.'/../../../resources/js/fields/typing.js'))
        ->toContain('readTyped(text)');
    expect(file_get_contents(__DIR__.'/../../../resources/js/fields/time-picker.js'))
        ->toContain('applyTyped(parts)');
});

test('seconds widen the format the parser reads', function () {
    expect(TimePicker::make('opens_at')->withSeconds()->getTypedFormat())->toBe('H:i:s')
        ->and(TimePicker::make('opens_at')->displayFormat('G.i')->getTypedFormat())->toBe('G.i');
});

test('typeable(false) leaves only the slot list', function () {
    expect(renderTimePickerView(TimePicker::make('opens_at')->typeable(false)))
        ->toContain('readonly')
        ->not->toContain('onTyped(');
});

test('the list opens from the keyboard and from a real toggle button', function () {
    expect(renderTimePickerView(TimePicker::make('opens_at')))
        ->toContain('@keydown.down.prevent="open = true"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('data-testid="form-time-opens_at-toggle"');
});

test('the slot options walk the day at the interval, inside the bounds', function () {
    $field = TimePicker::make('opens_at')->minDate('08:00')->maxDate('09:30');

    expect($field->getSlotOptions())->toBe(['08:00' => '08:00', '08:30' => '08:30', '09:00' => '09:00', '09:30' => '09:30'])
        ->and(TimePicker::make('t')->minutesStep(15)->minDate('10:00')->maxDate('10:30')->getSlotOptions())
        ->toBe(['10:00' => '10:00', '10:15' => '10:15', '10:30' => '10:30'])
        ->and(count(TimePicker::make('t')->getSlotOptions()))->toBe(48);
});

test('the slot options keep a current value that sits between slots, in order', function () {
    expect(TimePicker::make('t')->minDate('08:00')->maxDate('09:00')->getSlotOptions('08:17'))
        ->toBe(['08:00' => '08:00', '08:17' => '08:17', '08:30' => '08:30', '09:00' => '09:00']);
});

test('the slot options carry seconds and the display format', function () {
    expect(TimePicker::make('t')->withSeconds()->minDate('08:00')->maxDate('08:30')->getSlotOptions())
        ->toBe(['08:00:00' => '08:00:00', '08:30:00' => '08:30:00'])
        ->and(TimePicker::make('t')->displayFormat('g:i A')->minDate('13:00')->maxDate('13:00')->getSlotOptions())
        ->toBe(['13:00' => '1:00 PM']);
});

test('nativeOnMobile() renders the slot list and a native twin', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at')->nativeOnMobile());

    expect($html)
        ->toContain('wireTimePicker(')
        ->toContain('<select')
        ->not->toContain('type="time"')
        ->toContain('<div class="hidden max-sm:block">')
        ->toContain('id="opens_at-native"');
});

test('a slot list stays native on a phone whatever its interval or seconds', function () {
    // Its native control is a <select> of the slots, which carries both.
    expect(TimePicker::make('t')->minutesStep(15)->withSeconds()->nativeOnMobile()->isNativeOnMobile())->toBeTrue();
});

// ─── The mobile time wheel ───────────────────────────────────────────────────

test('touchOnMobile() renders the desktop list and a phone wheel split at the breakpoint', function () {
    $html = renderTimePickerView(TimePicker::make('opens_at')->label('Opens at')->minDate('08:00')->maxDate('09:00')->touchOnMobile());

    expect($html)
        ->toContain('wireWheelPicker(')
        ->toContain('wireTimePicker(')
        ->toContain('class="hidden max-sm:block"')
        ->toContain('relative max-sm:hidden')
        // The slots the wheel builds its columns from, bounds already applied.
        ->toContain("slots: JSON.parse('[\\u002208:00\\u0022,\\u002208:30\\u0022,\\u002209:00\\u0022]')")
        ->toContain("kind: 'time'")
        ->toContain('role="spinbutton"')
        ->toContain('x-trap.noscroll.noautofocus="open"')
        // Neither a native twin nor a sheet for the desktop panel: the wheel owns the phone.
        ->not->toContain('-native"')
        ->toContain('sheetOnMobile: false');
});

test('the wheel wins over nativeOnMobile, and native() wins over the wheel', function () {
    $wheel = TimePicker::make('t')->nativeOnMobile()->touchOnMobile();

    expect($wheel->usesTouchOnMobile())->toBeTrue()
        ->and($wheel->isNativeOnMobile())->toBeFalse()
        ->and(TimePicker::make('t')->touchOnMobile()->native()->usesTouchOnMobile())->toBeFalse()
        ->and(TimePicker::make('t')->touchOnMobile(false)->usesTouchOnMobile())->toBeFalse();
});

test('a time, a date and a datetime get the wheel; a month does not', function () {
    expect(DateTimePicker::make('d')->asTime()->touchOnMobile()->usesTouchOnMobile())->toBeTrue()
        ->and(DateTimePicker::make('d')->touchOnMobile()->usesTouchOnMobile())->toBeTrue()
        ->and(DateTimePicker::make('d')->asDate()->touchOnMobile()->usesTouchOnMobile())->toBeTrue()
        ->and(DateTimePicker::make('d')->asMonth()->touchOnMobile()->usesTouchOnMobile())->toBeFalse();
});

test('a date wheel carries its bounds and disabled days to the browser', function () {
    $field = DateTimePicker::make('due')->asDate()->minDate('2026-01-10')->maxDate('2027-12-31')->disabledDates(['2026-03-01'])->touchOnMobile();
    $html = view($field->render()->name(), ['field' => $field])->withErrors(new MessageBag)->render();

    expect($html)
        ->toContain("kind: 'date'")
        ->toContain("min: '2026-01-10'")
        ->toContain("max: '2027-12-31'")
        ->toContain("disabledDates: JSON.parse('[\\u00222026-03-01\\u0022]')")
        ->toContain('relative max-sm:hidden')
        ->not->toContain('-native"');
});

test('the wheel controller ships in the fields bundle', function () {
    expect(file_get_contents(__DIR__.'/../../../dist/wire-forms-fields.js'))->toContain('wireWheelPicker');
});
