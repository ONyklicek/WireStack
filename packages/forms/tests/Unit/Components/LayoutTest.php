<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Layout\Fieldset;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\TextInput;

// ─���─ Grid ──────────────────────────────────────────────────────

test('grid default columns is 2', function () {
    $grid = Grid::make()->schema([]);

    expect($grid->getColumns())->toBe(2);
});

test('grid custom columns', function () {
    $grid = Grid::make()->columns(3)->schema([
        TextInput::make('a'),
        TextInput::make('b'),
    ]);

    expect($grid->getColumns())->toBe(3)
        ->and($grid->getSchema())->toHaveCount(2);
});

// ─── Fieldset ──────────────────────────────────────────────────

test('fieldset with label', function () {
    $fieldset = Fieldset::make('Personal')->schema([
        TextInput::make('name'),
    ]);

    expect($fieldset->getLabel())->toBe('Personal')
        ->and($fieldset->getSchema())->toHaveCount(1);
});

test('fieldset columns', function () {
    $fieldset = Fieldset::make('Info')->columns(2);

    expect($fieldset->getColumns())->toBe(2);
});

// ─── Section ───────────────────────────────────────────────────

test('section with label and description', function () {
    $section = Section::make('Profile')
        ->description('Your personal information')
        ->schema([TextInput::make('name')]);

    expect($section->getLabel())->toBe('Profile')
        ->and($section->getDescription())->toBe('Your personal information')
        ->and($section->getSchema())->toHaveCount(1);
});

test('section collapsible', function () {
    $section = Section::make('Advanced')->collapsible();

    expect($section->isCollapsible())->toBeTrue()
        ->and($section->isCollapsed())->toBeFalse();
});

test('section collapsed implies collapsible', function () {
    $section = Section::make('Advanced')->collapsed();

    expect($section->isCollapsible())->toBeTrue()
        ->and($section->isCollapsed())->toBeTrue();
});

test('section icon', function () {
    $section = Section::make('Settings')->icon('cog');

    expect($section->getIcon())->toBe('cog');
});

test('section compact', function () {
    $section = Section::make('Quick')->compact();

    expect($section->isCompact())->toBeTrue();
});

test('section aside', function () {
    $section = Section::make('Sidebar')->aside();

    expect($section->isAside())->toBeTrue();
});

test('section columns', function () {
    $section = Section::make('Form')->columns(3);

    expect($section->getColumns())->toBe(3);
});
