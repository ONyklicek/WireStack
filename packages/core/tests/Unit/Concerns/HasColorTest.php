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
