<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Enums\IconPosition;
use NyonCode\WireCore\Foundation\Enums\Placement;

it('can be created with actions', function () {
    $group = ActionGroup::make([
        Action::make('edit'),
        Action::make('delete'),
    ]);

    expect($group->getActions())->toHaveCount(2);
});

// ─── Fluent API ─────────────────────────────────────────────────────────────

it('supports fluent configuration', function () {
    $group = ActionGroup::make([])
        ->label('Akce')
        ->icon('dots-horizontal')
        ->color('primary')
        ->size('md')
        ->tooltip('Více akcí')
        ->dropdownPosition('bottom-start')
        ->dropdownWidth('w-56')
        ->divided();

    expect($group->getLabel())->toBe('Akce')
        ->and($group->getIcon())->toBe('dots-horizontal')
        ->and($group->getColor())->toBe('primary')
        ->and($group->getSize())->toBe('md')
        ->and($group->getTooltip())->toBe('Více akcí')
        ->and($group->getDropdownPositionValue())->toBe('bottom-start')
        ->and($group->getDropdownWidth())->toBe('w-56')
        ->and($group->isDivided())->toBeTrue();
});

// ─── Defaults ───────���───────────────────────────────────────────────────────

it('has correct defaults', function () {
    $group = ActionGroup::make([]);

    expect($group->getLabel())->toBeNull()
        ->and($group->getIcon())->toBe('dots-vertical')
        ->and($group->getColor())->toBe('gray')
        ->and($group->getSize())->toBe('sm')
        ->and($group->getTooltip())->toBeNull()
        ->and($group->isDivided())->toBeFalse()
        ->and($group->getDropdownPositionValue())->toBe('bottom-end')
        ->and($group->getDropdownWidth())->toBe('w-48');
});

// ─── Badge ──────────────────────────────────────────────────────────────────

it('supports static badge count', function () {
    $group = ActionGroup::make([])->badge(5);

    expect($group->hasBadge())->toBeTrue()
        ->and($group->getBadgeCount())->toBe(5);
});

it('supports dynamic badge count via closure', function () {
    $group = ActionGroup::make([])->badge(fn () => 10);

    expect($group->getBadgeCount())->toBe(10);
});

it('has no badge by default', function () {
    expect(ActionGroup::make([])->hasBadge())->toBeFalse();
});

it('has default danger badge color', function () {
    expect(ActionGroup::make([])->getBadgeColor())->toBe('danger');
});

it('can set badge color', function () {
    $group = ActionGroup::make([])->badge(1)->badgeColor('success');

    expect($group->getBadgeColor())->toBe('success');
});

it('uses canonical icon button colors in trigger classes', function () {
    $group = ActionGroup::make([])->color('success');

    expect($group->getTriggerClasses())
        ->toContain('text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/20')
        ->and($group->getTriggerClasses())->toContain('p-1.5');
});

it('uses the shared button size scale when the trigger has a label', function () {
    $group = ActionGroup::make([])
        ->label('More')
        ->size('md');

    expect($group->getTriggerClasses())->toContain('px-3 py-2 text-sm gap-2');
});

// ─── Dropdown Position Classes ──────────────────────────────────────────────

it('generates correct dropdown position classes', function () {
    expect(ActionGroup::make([])->dropdownPosition('bottom-start')->getDropdownPositionClasses())
        ->toBe('left-0 origin-top-left')
        ->and(ActionGroup::make([])->dropdownPosition('bottom-end')->getDropdownPositionClasses())
        ->toBe('right-0 origin-top-right')
        ->and(ActionGroup::make([])->dropdownPosition('top-start')->getDropdownPositionClasses())
        ->toBe('left-0 bottom-full origin-bottom-left')
        ->and(ActionGroup::make([])->dropdownPosition('top-end')->getDropdownPositionClasses())
        ->toBe('right-0 bottom-full origin-bottom-right');
});

it('generates a transform-origin class for the teleported panel', function () {
    expect(ActionGroup::make([])->dropdownPosition('bottom-start')->getDropdownOriginClass())
        ->toBe('origin-top-left')
        ->and(ActionGroup::make([])->dropdownPosition('bottom-end')->getDropdownOriginClass())
        ->toBe('origin-top-right')
        ->and(ActionGroup::make([])->dropdownPosition('top-start')->getDropdownOriginClass())
        ->toBe('origin-bottom-left')
        ->and(ActionGroup::make([])->dropdownPosition('top-end')->getDropdownOriginClass())
        ->toBe('origin-bottom-right')
        ->and(ActionGroup::make([])->dropdownPosition('unknown')->getDropdownOriginClass())
        ->toBe('origin-top-right');
});

it('exposes a Floating UI config mapping placement 1:1 with a safe fallback', function () {
    // The menu opts into the mobile bottom-sheet presentation (Floating UI is
    // skipped below the breakpoint; the view supplies the max-sm: sheet classes).
    expect(ActionGroup::make([])->dropdownPosition('bottom-start')->getDropdownConfig())
        ->toBe(['placement' => 'bottom-start', 'offset' => 6, 'sheetOnMobile' => true, 'sheetBreakpoint' => 639.98])
        ->and(ActionGroup::make([])->dropdownPosition('top-end')->getDropdownConfig())
        ->toBe(['placement' => 'top-end', 'offset' => 6, 'sheetOnMobile' => true, 'sheetBreakpoint' => 639.98])
        ->and(ActionGroup::make([])->dropdownPosition('unknown')->getDropdownConfig())
        ->toBe(['placement' => 'bottom-end', 'offset' => 6, 'sheetOnMobile' => true, 'sheetBreakpoint' => 639.98]);
});

it('accepts a Breakpoint enum on the sheet mobileBreakpoint setter (HasSheetOnMobile)', function () {
    expect(ActionGroup::make([])->mobileBreakpoint(Breakpoint::Md)->getMobileBreakpoint())->toBe('md')
        ->and(ActionGroup::make([])->mobileBreakpoint('lg')->getMobileBreakpoint())->toBe('lg');
});

it('accepts a Breakpoint enum on the modal mobileBreakpoint setter (HasModal)', function () {
    expect(Action::make('edit')->mobileBreakpoint(Breakpoint::Lg)->getMobileBreakpoint())->toBe('lg')
        ->and(Action::make('edit')->mobileBreakpoint(null)->getMobileBreakpoint())->toBeNull();
});

it('accepts a Placement enum on dropdownPosition and resolves the same classes as the string', function () {
    $enum = ActionGroup::make([])->dropdownPosition(Placement::TopStart);
    $string = ActionGroup::make([])->dropdownPosition('top-start');

    expect($enum->getDropdownPositionClasses())->toBe($string->getDropdownPositionClasses())
        ->and($enum->getDropdownOriginClass())->toBe('origin-bottom-left')
        ->and($enum->getDropdownConfig()['placement'])->toBe('top-start')
        // An unknown position falls back to the canonical bottom-end default.
        ->and(ActionGroup::make([])->dropdownPosition('weird')->getDropdownConfig()['placement'])->toBe('bottom-end');
});

it('accepts an IconPosition enum on the action icon setter (HasDynamicProperties)', function () {
    expect(Action::make('edit')->icon('pencil', IconPosition::After)->getIconPosition())->toBe('after')
        ->and(Action::make('edit')->icon('pencil', 'before')->getIconPosition())->toBe('before');
});
