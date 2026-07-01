<?php

declare(strict_types=1);

use Livewire\Component as LivewireComponent;
use NyonCode\WireForms\Components\Layout\Fieldset;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\TextInput;

/**
 * Minimal Livewire host exposing a `data` state bag, mirroring how a form binds
 * its schema under a statePath.
 */
function layoutStateHost(string $type = 'business'): LivewireComponent
{
    return new class($type) extends LivewireComponent
    {
        /** @var array<string, mixed> */
        public array $data;

        public function __construct(string $type)
        {
            $this->data = ['type' => $type, 'name' => 'Acme'];
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };
}

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

// ─── Form-specific view names ───────────────────────────────────

test('layout subclasses swap in the form blade views', function () {
    expect(Grid::make()->render()->name())->toBe('wire-forms::layouts.grid')
        ->and(Section::make('S')->render()->name())->toBe('wire-forms::layouts.section')
        ->and(Fieldset::make('F')->render()->name())->toBe('wire-forms::layouts.fieldset');
});

// ─── Reactive state accessors ($get / $set) ─────────────────────

test('layout exposes $get/$set accessors bound to the livewire host', function () {
    $host = layoutStateHost();

    $section = Section::make('Billing')->schema([TextInput::make('vat_id')]);
    $section->prepareChildren('data', false, $host);

    $accessors = $section->getStateAccessors();

    expect($accessors['get']('type'))->toBe('business');

    $accessors['set']('type', 'individual');

    expect($host->data['type'])->toBe('individual');
});

test('layout visible() closure resolves sibling state via $get', function () {
    $grid = Grid::make()->schema([TextInput::make('vat_id')])
        ->visible(fn ($get) => $get('type') === 'business');
    $grid->prepareChildren('data', false, layoutStateHost('business'));

    expect($grid->isVisible())->toBeTrue();
});

test('layout visible() closure hides when sibling state differs', function () {
    $grid = Grid::make()->schema([TextInput::make('vat_id')])
        ->visible(fn ($get) => $get('type') === 'business');
    $grid->prepareChildren('data', false, layoutStateHost('individual'));

    expect($grid->isVisible())->toBeFalse();
});

test('nested layout inherits the livewire binding from prepareChildren', function () {
    $host = layoutStateHost();
    $inner = Grid::make()->schema([TextInput::make('vat_id')]);
    $outer = Section::make('Billing')->schema([$inner]);

    $outer->prepareChildren('data', false, $host);

    expect($inner->getStateAccessors()['get']('type'))->toBe('business');
});

test('a bound layout $get() without a path returns null (layouts have no own value)', function () {
    $grid = Grid::make()->schema([TextInput::make('vat_id')]);
    $grid->prepareChildren('data', false, layoutStateHost());

    // livewire is bound, but the layout has no name, so the own-value read
    // resolves to null rather than the bag root.
    expect($grid->getStateAccessors()['get']())->toBeNull();
});

test('layout state accessors degrade gracefully without a bound livewire', function () {
    $grid = Grid::make()->schema([]);

    $accessors = $grid->getStateAccessors();

    expect($accessors['get']('type', 'fallback'))->toBe('fallback')
        ->and($accessors['state'])->toBeNull()
        ->and($accessors['get']())->toBeNull();
});
