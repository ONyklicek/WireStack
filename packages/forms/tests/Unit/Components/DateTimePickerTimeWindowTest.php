<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\TimePicker;

/**
 * The daily window — `minTime()` / `maxTime()` — and the desktop panel it is
 * drawn in. A time carried by `minDate('… 08:30')` binds on its own day only;
 * the window binds on every day, and where both apply the stricter wins.
 */
function renderWindowPicker(DateTimePicker $field): string
{
    return view($field->render()->name(), ['field' => $field])
        ->withErrors(new MessageBag)
        ->render();
}

test('a picker with a clock reads its daily window padded to seconds', function () {
    $field = DateTimePicker::make('slot_at')->minTime('6:00')->maxTime('18:00');

    expect($field->getMinTime())->toBe('06:00:00')
        ->and($field->getMaxTime())->toBe('18:00:00');
});

test('a date has no time of day, so it has no window', function () {
    $field = DateTimePicker::make('due_on')->asDate()->minTime('06:00')->maxTime('18:00');

    expect($field->getMinTime())->toBeNull()
        ->and($field->getMaxTime())->toBeNull()
        ->and($field->getValidationRules())->toBe([]);
});

test('the window is evaluated, so it may follow other state', function () {
    $field = DateTimePicker::make('slot_at')->minTime(fn () => '07:30');

    expect($field->getMinTime())->toBe('07:30:00');
});

test('on a bound day the stricter of the bound time and the window wins', function () {
    $field = DateTimePicker::make('slot_at')
        ->minDate('2026-10-01 08:30')
        ->maxDate('2026-12-31 16:00')
        ->minTime('06:00')
        ->maxTime('18:00');

    expect($field->getTimeBoundsFor('2026-10-01'))->toBe(['08:30:00', '18:00:00'])
        ->and($field->getTimeBoundsFor('2026-11-15'))->toBe(['06:00:00', '18:00:00'])
        ->and($field->getTimeBoundsFor('2026-12-31'))->toBe(['06:00:00', '16:00:00']);
});

test('a time picker has one day, so both its bounds and the window always apply', function () {
    $field = TimePicker::make('opens_at')->minDate('05:00')->maxDate('20:00')->minTime('06:00')->maxTime('18:00');

    expect($field->getTimeBoundsFor())->toBe(['06:00:00', '18:00:00'])
        ->and(array_key_first($field->getSlotOptions()))->toBe('06:00')
        ->and(array_key_last($field->getSlotOptions()))->toBe('18:00');
});

test('a datetime\'s native slots stay inside the window', function () {
    $slots = DateTimePicker::make('slot_at')->minutesStep(30)->minTime('06:00')->maxTime('18:00')->getSlotOptions();

    expect(array_key_first($slots))->toBe('06:00')
        ->and(array_key_last($slots))->toBe('18:00');
});

test('the server holds the window on every day, not only on a bound one', function () {
    $rules = DateTimePicker::make('d')
        ->minDate('2026-10-01')
        ->maxDate('2026-12-31')
        ->minTime('06:00')
        ->maxTime('18:00')
        ->getValidationRules();
    $messages = fn (mixed $value): array => Validator::make(['d' => $value], ['d' => $rules])->errors()->get('d');

    expect($messages('2026-11-03 06:00'))->toBe([])
        ->and($messages('2026-11-03 18:00'))->toBe([])
        ->and($messages(null))->toBe([])
        ->and($messages('2026-11-03 05:45'))->toBe(['The time in d must not be before 06:00.'])
        ->and($messages('2026-11-03 18:15'))->toBe(['The time in d must not be after 18:00.'])
        ->and($messages('2026-09-30 12:00'))->toBe(['The d must not be earlier than 01.10.2026 00:00.']);
});

test('a window alone is enough for a rule', function () {
    $rules = DateTimePicker::make('t')->asTime()->minTime('06:00')->getValidationRules();

    expect(Validator::make(['t' => '05:59'], ['t' => $rules])->passes())->toBeFalse()
        ->and(Validator::make(['t' => '06:00'], ['t' => $rules])->passes())->toBeTrue();
});

test('the browser receives the window beside the bound times', function () {
    $html = renderWindowPicker(DateTimePicker::make('slot_at')->minTime('06:00')->maxTime('18:00'));

    expect($html)->toContain("windowMin: '06:00:00'")
        ->and($html)->toContain("windowMax: '18:00:00'");
});

test('the time picker list is drawn inside the window', function () {
    $html = renderWindowPicker(TimePicker::make('opens_at')->minTime('06:00')->maxTime('18:00'));

    expect($html)->toContain("minTime: '06:00:00'")
        ->and($html)->toContain("maxTime: '18:00:00'");
});

test('the desktop panel draws a month and year header and a clock dial, the phone keeps its own', function () {
    $html = renderWindowPicker(DateTimePicker::make('event_at'));

    expect($html)->toContain('data-testid="form-datetime-event_at-month-toggle"')
        ->and($html)->toContain('data-testid="form-datetime-event_at-year-toggle"')
        ->and($html)->toContain('data-testid="form-datetime-event_at-dial"')
        // The picker's own footer (Now / Clear / Done) stands in for the dial's.
        ->and($html)->not->toContain('data-testid="form-datetime-event_at-ok"')
        ->and($html)->toContain('data-testid="form-datetime-event_at-now"')
        ->and($html)->toContain('<template x-if="! wide">')
        ->and($html)->toContain('data-testid="form-datetime-event_at-hours-up"');
});

test('a picker shown below the breakpoint only does not hold a hidden required', function () {
    $twin = renderWindowPicker(DateTimePicker::make('valid_from')->asDate()->required()->belowBreakpointOnly());
    $plain = renderWindowPicker(DateTimePicker::make('valid_from')->asDate()->required());

    expect($twin)->toContain('aria-required="true"')
        ->and($twin)->not->toMatch('/\srequired(\s|>)/')
        ->and($plain)->toMatch('/\srequired(\s|>)/');
});

test('a stepped native clock shown below the breakpoint only does not hold a hidden required', function () {
    // The native datetime with a step is a date input and a slot <select>;
    // neither may stop a desktop form the user cannot see it on.
    $twin = renderWindowPicker(DateTimePicker::make('valid_from')->native()->minutesStep(15)->required()->belowBreakpointOnly());
    $plain = renderWindowPicker(DateTimePicker::make('valid_from')->native()->minutesStep(15)->required());

    expect($twin)->toContain('data-testid="form-datetime-valid_from-native-time"')
        ->and($twin)->toContain('aria-required="true"')
        ->and($twin)->not->toMatch('/\srequired(\s|>)/')
        ->and($plain)->toMatch('/\srequired(\s|>)/');
});

test('a range hands the window to both ends and marks them as the phone\'s', function () {
    $range = DateRangePicker::make('shift')->withTime()->minTime('06:00')->maxTime('18:00');

    foreach ([$range->getFromPicker(), $range->getUntilPicker()] as $picker) {
        expect($picker->getMinTime())->toBe('06:00:00')
            ->and($picker->getMaxTime())->toBe('18:00:00')
            ->and($picker->isBelowBreakpointOnly())->toBeTrue();
    }

    expect($range->hasTime())->toBeTrue()
        ->and(DateRangePicker::make('p')->required()->isRequired())->toBeTrue();
});

test('a picker is keyed on its bounds, so a changed bound is a new element', function () {
    // Alpine reconciles a changed x-data onto the old object, which wrote the
    // fresh $wire.entangle placeholder to the server as the value. A key that
    // changes with the bounds replaces the element instead.
    $key = function (string $min): string {
        preg_match('/wire:key="picker-slot_at-([0-9a-f]{32})"/', renderWindowPicker(DateTimePicker::make('slot_at')->minDate($min)), $m);

        return $m[1] ?? '';
    };

    expect($key('2026-10-01'))->not->toBe('')
        ->and($key('2026-10-01'))->toBe($key('2026-10-01'))
        ->and($key('2026-10-02'))->not->toBe($key('2026-10-01'));
});

test('the touch wheel is keyed on its bounds as well', function () {
    $html = renderWindowPicker(DateTimePicker::make('slot_at')->asDate()->minDate('2026-10-01')->touchOnMobile());

    expect($html)->toMatch('/wire:key="wheel-slot_at-[0-9a-f]{32}"/');
});
