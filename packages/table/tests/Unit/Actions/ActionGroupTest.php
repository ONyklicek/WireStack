<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;

function actionGroupRecord(): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1]);

    return $record;
}

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

// ─── Dropdown body rendering (dividers) ─────────────────────────────────────

it('renders a manual divider between dropdown items', function () {
    $html = ActionGroup::make([
        Action::make('edit')->label('Edit'),
        Action::divider(),
        Action::make('delete')->label('Delete'),
    ])->getDropdownItemsHtml(actionGroupRecord());

    expect($html)->toContain('Edit')
        ->and($html)->toContain('Delete')
        ->and($html)->toContain('role="separator"');
});

it('auto-inserts dividers when divided() is set', function () {
    $html = ActionGroup::make([
        Action::make('edit')->label('Edit'),
        Action::make('delete')->label('Delete'),
    ])->divided()->getDropdownItemsHtml(actionGroupRecord());

    expect($html)->toContain('role="separator"');
});

it('renders the full group dropdown with a divider through its view', function () {
    $html = ActionGroup::make([
        Action::make('edit')->label('Edit'),
        Action::divider(),
        Action::make('delete')->label('Delete'),
    ])->render(actionGroupRecord());

    expect($html)->toContain('role="menu"')
        ->and($html)->toContain('role="separator"')
        ->and($html)->toContain('Edit')
        ->and($html)->toContain('Delete');
});

it('collapses to a single inline action when only one is visible', function () {
    $html = ActionGroup::make([
        Action::make('edit')->label('Edit'),
        Action::divider(),
    ])->render(actionGroupRecord());

    expect($html)->not->toContain('role="menu"')
        ->and($html)->toContain('Edit');
});
