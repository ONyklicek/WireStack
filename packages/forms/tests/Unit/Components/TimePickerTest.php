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

test('renders a native time input when asked for one', function () {
    expect(renderTimePickerView(TimePicker::make('opens_at')->native()->maxDate('17:00')))
        ->toContain('type="time"')
        ->toContain('max="17:00"')
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
