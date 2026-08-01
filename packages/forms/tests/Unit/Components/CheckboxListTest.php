<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireForms\Components\CheckboxList;

function renderCheckboxList(CheckboxList $field): string
{
    view()->share('errors', new ViewErrorBag);
    // The view's @this directive compiles to $_instance->getId(); a stub is enough
    // to render the markup outside a live Livewire request.
    view()->share('_instance', new class
    {
        public function getId(): string
        {
            return 'test-component';
        }
    });

    return view('wire-forms::components.checkbox-list', ['field' => $field])->render();
}

test('make creates checkbox list with name', function () {
    expect(CheckboxList::make('roles')->getName())->toBe('roles');
});

test('multi-column lists reflow to fewer columns on mobile', function () {
    $field = CheckboxList::make('roles')->options(['a' => 'A', 'b' => 'B', 'c' => 'C']);

    // A 3-wide grid is unusable at phone widths, so it collapses to one column
    // below sm and only expands to the requested count from sm up.
    expect(renderCheckboxList($field->columns(3)))->toContain('grid-cols-1 sm:grid-cols-3')
        ->and(renderCheckboxList($field->columns(4)))->toContain('grid-cols-2 sm:grid-cols-4')
        ->and(renderCheckboxList($field->columns(1)))->toContain('grid-cols-1');
});

test('options can be set and resolved from a closure', function () {
    $field = CheckboxList::make('roles')->options(fn () => ['a' => 'A', 'b' => 'B']);

    expect($field->getOptions())->toBe(['a' => 'A', 'b' => 'B']);
});

test('state type is always array (regression)', function () {
    // A checkbox list holds an array of selected keys; the state definition must
    // reflect that so a stray scalar is normalized rather than left a string.
    expect(CheckboxList::make('roles')->getStateType())->toBe('array');
});

it('renders per-breakpoint grid columns from an array', function () {
    $field = CheckboxList::make('perms')
        ->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])
        ->columns(['default' => 1, 'md' => 2, 'lg' => 3]);

    expect($field->getColumns())->toBe(['default' => 1, 'md' => 2, 'lg' => 3]);

    expect(renderCheckboxList($field))
        ->toContain('grid-cols-1 md:grid-cols-2 lg:grid-cols-3')
        // The int reflow arms must not fire for an array.
        ->not->toContain('grid-cols-2 sm:grid-cols-4');
});

it('keeps the mobile-first int reflow for a plain integer', function () {
    $field = CheckboxList::make('perms')
        ->options(['a' => 'A', 'b' => 'B'])
        ->columns(4);

    expect(renderCheckboxList($field))->toContain('grid-cols-2 sm:grid-cols-4');
});

it('labels the bulk toggles from the translations by default', function () {
    $html = renderCheckboxList(
        CheckboxList::make('perms')->options(['a' => 'A', 'b' => 'B'])->bulkToggleable()
    );

    expect($html)->toContain('Select all')->toContain('Deselect all');
});

it('lets the owner name the bulk toggles', function () {
    // The toggles are the only affordance for a long list, so their wording is
    // part of the public API rather than a fixed string.
    $field = CheckboxList::make('perms')
        ->options(['a' => 'A', 'b' => 'B'])
        ->bulkToggleable()
        ->selectAllLabel('Grant everything')
        ->deselectAllLabel('Revoke everything');

    expect($field->getSelectAllLabel())->toBe('Grant everything')
        ->and($field->getDeselectAllLabel())->toBe('Revoke everything')
        ->and(renderCheckboxList($field))
        ->toContain('Grant everything')
        ->toContain('Revoke everything')
        ->not->toContain('Select all');
});

it('renders no bulk toggles unless they are asked for', function () {
    expect(renderCheckboxList(CheckboxList::make('perms')->options(['a' => 'A'])))
        ->not->toContain('Select all');
});

// ─── Choice variants (shared with Radio) ────────────────────────────────────

test('a checkbox list is a plain list until a variant is chosen', function () {
    $field = CheckboxList::make('roles')->options(['a' => 'A']);

    expect($field->getVariant())->toBe('default')
        ->and($field->isSegmented())->toBeFalse()
        ->and($field->isButtons())->toBeFalse()
        ->and(renderCheckboxList($field))->toContain('max-h-60');
});

test('segmented renders the shared track and drops the list chrome', function () {
    $html = renderCheckboxList(
        CheckboxList::make('roles')->options(['a' => 'A', 'b' => 'B'])->segmented()->searchable()->bulkToggleable()
    );

    expect($html)->toContain('role="group"')
        ->and($html)->toContain('peer-checked:bg-white')
        // Still checkboxes: several options can be picked at once.
        ->and(substr_count($html, 'type="checkbox"'))->toBe(2)
        // Search / bulk toggle are list chrome and do not apply here.
        ->and($html)->not->toContain('max-h-60')
        ->and($html)->not->toContain('-select-all');
});

test('buttons stack until made inline', function () {
    $stacked = renderCheckboxList(CheckboxList::make('roles')->options(['a' => 'A'])->buttons());
    $inline = renderCheckboxList(CheckboxList::make('roles')->options(['a' => 'A'])->buttons()->inline());

    expect($stacked)->toContain('flex-col')
        ->and($inline)->toContain('flex-row')
        ->and($inline)->not->toContain('flex-col');
});

test('a variant carries per-option icons and colors', function () {
    $html = renderCheckboxList(
        CheckboxList::make('roles')
            ->options(['a' => 'A', 'b' => 'B'])
            ->buttons()
            ->icons(['a' => 'check'])
            ->colors(['a' => 'danger'])
    );

    expect($html)->toContain('<svg')
        ->and($html)->toContain('peer-checked:bg-red-600');
});

test('variants keep the field disabled state', function () {
    $html = renderCheckboxList(CheckboxList::make('roles')->options(['a' => 'A'])->segmented()->disabled());

    expect($html)->toContain('disabled')->toContain('cursor-not-allowed');
});

test('choosing a variant is reversible', function () {
    $field = CheckboxList::make('roles')->segmented();

    expect($field->isSegmented())->toBeTrue()
        ->and($field->segmented(false)->getVariant())->toBe('default')
        ->and($field->buttons()->isButtons())->toBeTrue()
        ->and($field->buttons(false)->getVariant())->toBe('default');
});
