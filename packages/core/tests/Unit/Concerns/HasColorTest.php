<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Concerns\HasColor;

class TestColorClass
{
    use HasColor;
}

it('returns correct badge color classes for primary', function () {
    expect(TestColorClass::getBadgeColorClasses('primary'))->toContain('bg-primary-100');
});

it('returns correct badge color classes for success', function () {
    expect(TestColorClass::getBadgeColorClasses('success'))->toContain('bg-emerald-100');
});

it('returns correct badge color classes for danger', function () {
    expect(TestColorClass::getBadgeColorClasses('danger'))->toContain('bg-red-100');
});

it('returns correct badge color classes for warning', function () {
    expect(TestColorClass::getBadgeColorClasses('warning'))->toContain('bg-amber-100');
});

it('returns correct badge color classes for info', function () {
    expect(TestColorClass::getBadgeColorClasses('info'))->toContain('bg-cyan-100');
});

it('returns correct badge color classes for gray', function () {
    expect(TestColorClass::getBadgeColorClasses('gray'))->toContain('bg-gray-100');
});

it('returns gray badge classes for unknown color', function () {
    expect(TestColorClass::getBadgeColorClasses('nonexistent'))->toContain('bg-gray-100');
});

it('supports color aliases', function () {
    expect(TestColorClass::getBadgeColorClasses('blue'))->toBe(TestColorClass::getBadgeColorClasses('primary'))
        ->and(TestColorClass::getBadgeColorClasses('green'))->toBe(TestColorClass::getBadgeColorClasses('success'))
        ->and(TestColorClass::getBadgeColorClasses('red'))->toBe(TestColorClass::getBadgeColorClasses('danger'))
        ->and(TestColorClass::getBadgeColorClasses('yellow'))->toBe(TestColorClass::getBadgeColorClasses('warning'));
});

it('returns correct soft background classes for the toggle off track', function () {
    expect(TestColorClass::getSoftBgClass('primary'))->toContain('bg-primary-200')
        ->and(TestColorClass::getSoftBgClass('success'))->toContain('bg-emerald-200')
        ->and(TestColorClass::getSoftBgClass('danger'))->toContain('bg-red-200')
        ->and(TestColorClass::getSoftBgClass('warning'))->toContain('bg-amber-200')
        ->and(TestColorClass::getSoftBgClass('info'))->toContain('bg-cyan-200')
        ->and(TestColorClass::getSoftBgClass('purple'))->toContain('bg-purple-200')
        ->and(TestColorClass::getSoftBgClass('pink'))->toContain('bg-pink-200')
        ->and(TestColorClass::getSoftBgClass('gray'))->toContain('bg-gray-200')
        ->and(TestColorClass::getSoftBgClass('nonexistent'))->toContain('bg-gray-200');
});

it('maps soft background color aliases to the same hue', function () {
    expect(TestColorClass::getSoftBgClass('blue'))->toBe(TestColorClass::getSoftBgClass('primary'))
        ->and(TestColorClass::getSoftBgClass('green'))->toBe(TestColorClass::getSoftBgClass('success'))
        ->and(TestColorClass::getSoftBgClass('red'))->toBe(TestColorClass::getSoftBgClass('danger'))
        ->and(TestColorClass::getSoftBgClass('yellow'))->toBe(TestColorClass::getSoftBgClass('warning'))
        ->and(TestColorClass::getSoftBgClass('cyan'))->toBe(TestColorClass::getSoftBgClass('info'))
        ->and(TestColorClass::getSoftBgClass('secondary'))->toBe(TestColorClass::getSoftBgClass('gray'));
});

it('returns correct modal icon bg classes', function () {
    expect(TestColorClass::getModalIconBgClass('danger'))->toContain('bg-red-100')
        ->and(TestColorClass::getModalIconBgClass('warning'))->toContain('bg-amber-100')
        ->and(TestColorClass::getModalIconBgClass('success'))->toContain('bg-emerald-100')
        ->and(TestColorClass::getModalIconBgClass('info'))->toContain('bg-blue-100');
});

it('returns correct modal icon text classes', function () {
    expect(TestColorClass::getModalIconTextClass('danger'))->toContain('text-red-600')
        ->and(TestColorClass::getModalIconTextClass('warning'))->toContain('text-amber-600')
        ->and(TestColorClass::getModalIconTextClass('success'))->toContain('text-emerald-600')
        ->and(TestColorClass::getModalIconTextClass('info'))->toContain('text-blue-600');
});

it('returns correct alert color classes per semantic hue', function () {
    expect(TestColorClass::getAlertColorClasses('success'))->toContain('bg-emerald-50')
        ->and(TestColorClass::getAlertColorClasses('warning'))->toContain('bg-amber-50')
        ->and(TestColorClass::getAlertColorClasses('danger'))->toContain('bg-red-50')
        ->and(TestColorClass::getAlertColorClasses('info'))->toContain('bg-blue-50')
        ->and(TestColorClass::getAlertColorClasses('nonexistent'))->toContain('bg-blue-50');
});

it('maps alert color aliases to the same hue', function () {
    expect(TestColorClass::getAlertColorClasses('green'))->toBe(TestColorClass::getAlertColorClasses('success'))
        ->and(TestColorClass::getAlertColorClasses('red'))->toBe(TestColorClass::getAlertColorClasses('danger'))
        ->and(TestColorClass::getAlertColorClasses('yellow'))->toBe(TestColorClass::getAlertColorClasses('warning'));
});

it('returns correct modal submit button classes per semantic hue', function () {
    expect(TestColorClass::getModalSubmitButtonClasses('primary'))->toContain('bg-primary-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('danger'))->toContain('bg-red-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('success'))->toContain('bg-emerald-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('warning'))->toContain('bg-amber-500')
        ->and(TestColorClass::getModalSubmitButtonClasses('nonexistent'))->toContain('bg-primary-600');
});

it('includes active and focus states on modal submit button classes', function () {
    expect(TestColorClass::getModalSubmitButtonClasses('danger'))
        ->toContain('active:bg-red-800')
        ->toContain('focus:ring-red-500');
});
