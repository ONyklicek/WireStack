<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;

test('make creates repeater with name', function () {
    $repeater = Repeater::make('contacts');

    expect($repeater->getName())->toBe('contacts');
});

test('relationship can be set', function () {
    $repeater = Repeater::make('contacts')
        ->relationship('contacts');

    expect($repeater->getRelationship())->toBe('contacts');
});

test('schema can be set', function () {
    $repeater = Repeater::make('contacts')
        ->schema([
            TextInput::make('name'),
            TextInput::make('email'),
        ]);

    expect($repeater->getSchema())->toHaveCount(2);
});

test('addable defaults to true', function () {
    $repeater = Repeater::make('items');

    expect($repeater->isAddable())->toBeTrue();
});

test('addable can be disabled', function () {
    $repeater = Repeater::make('items')->addable(false);

    expect($repeater->isAddable())->toBeFalse();
});

test('deletable defaults to true', function () {
    $repeater = Repeater::make('items');

    expect($repeater->isDeletable())->toBeTrue();
});

test('deletable can be disabled', function () {
    $repeater = Repeater::make('items')->deletable(false);

    expect($repeater->isDeletable())->toBeFalse();
});

test('reorderable defaults to false', function () {
    $repeater = Repeater::make('items');

    expect($repeater->isReorderable())->toBeFalse();
});

test('reorderable can be enabled', function () {
    $repeater = Repeater::make('items')->reorderable();

    expect($repeater->isReorderable())->toBeTrue();
});

test('collapsible defaults to false', function () {
    $repeater = Repeater::make('items');

    expect($repeater->isCollapsible())->toBeFalse();
});

test('collapsible can be enabled', function () {
    $repeater = Repeater::make('items')->collapsible();

    expect($repeater->isCollapsible())->toBeTrue();
});

test('collapsed implies collapsible', function () {
    $repeater = Repeater::make('items')->collapsed();

    expect($repeater->isCollapsed())->toBeTrue()
        ->and($repeater->isCollapsible())->toBeTrue();
});

test('min items can be set', function () {
    $repeater = Repeater::make('items')->minItems(1);

    expect($repeater->getMinItems())->toBe(1);
});

test('max items can be set', function () {
    $repeater = Repeater::make('items')->maxItems(10);

    expect($repeater->getMaxItems())->toBe(10);
});

test('add button label can be customized', function () {
    $repeater = Repeater::make('items')->addButtonLabel('Add contact');

    expect($repeater->getAddButtonLabel())->toBe('Add contact');
});

test('disabled repeater prevents add and delete', function () {
    $repeater = Repeater::make('items')->disabled();

    expect($repeater->isDisabled())->toBeTrue()
        ->and($repeater->isAddable())->toBeFalse()
        ->and($repeater->isDeletable())->toBeFalse()
        ->and($repeater->isReorderable())->toBeFalse();
});

test('state path is correct', function () {
    $repeater = Repeater::make('contacts');

    expect($repeater->getStatePath())->toBe('contacts');

    $repeater->statePath('data');

    expect($repeater->getStatePath())->toBe('data.contacts');
});

test('state path is prefixed through prepareChildren when nested in a layout (regression)', function () {
    // Regression: a Repeater nested in a layout never had its own statePath set,
    // so getStatePath() dropped the form base prefix (returned 'contacts' instead
    // of 'data.contacts'), and add/remove/wire bindings hit the wrong location.
    $repeater = Repeater::make('contacts')->schema([TextInput::make('name')]);

    $layout = Grid::make()->schema([$repeater]);
    $layout->prepareChildren('data');

    expect($repeater->getStatePath())->toBe('data.contacts')
        ->and($repeater->getItemStatePath(0))->toBe('data.contacts.0');
});

test('state path is prefixed for a top-level repeater prepared with a base path (regression)', function () {
    $repeater = Repeater::make('contacts');

    // Mirrors the FormRuntime::prepare() call for a root-level layout component.
    $repeater->prepareChildren('data');

    expect($repeater->getStatePath())->toBe('data.contacts');
});

test('prepareChildren is idempotent and never double-applies the base path (regression)', function () {
    $repeater = Repeater::make('contacts');

    $repeater->prepareChildren('data');
    $repeater->prepareChildren('data');

    expect($repeater->getStatePath())->toBe('data.contacts');
});

test('item state path includes index', function () {
    $repeater = Repeater::make('contacts')->statePath('data');

    expect($repeater->getItemStatePath(0))->toBe('data.contacts.0')
        ->and($repeater->getItemStatePath(2))->toBe('data.contacts.2');
});

test('get item schema returns cloned components with index path', function () {
    $repeater = Repeater::make('contacts')
        ->statePath('data')
        ->schema([
            TextInput::make('name'),
            TextInput::make('email'),
        ]);

    $schema0 = $repeater->getItemSchema(0);
    $schema1 = $repeater->getItemSchema(1);

    expect($schema0)->toHaveCount(2)
        ->and($schema1)->toHaveCount(2)
        ->and($schema0[0]->getStatePath())->toBe('data.contacts.0.name')
        ->and($schema1[0]->getStatePath())->toBe('data.contacts.1.name');
});

test('validation rules include array with min and max', function () {
    $repeater = Repeater::make('items')
        ->minItems(1)
        ->maxItems(5);

    $rules = $repeater->getRules();

    expect($rules)->toContain('array')
        ->and($rules)->toContain('min:1')
        ->and($rules)->toContain('max:5');
});

test('validation rules are empty without constraints', function () {
    $repeater = Repeater::make('items');

    expect($repeater->getRules())->toBe([]);
});

test('container validation rules add required+array when required (regression)', function () {
    $repeater = Repeater::make('items')->minItems(2)->required();

    $rules = $repeater->getContainerValidationRules();

    expect($rules)->toContain('required')
        ->and($rules)->toContain('array')
        ->and($rules)->toContain('min:2');
});

test('item validation rules are collected per child field (regression)', function () {
    $repeater = Repeater::make('contacts')->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->rules(['email']),
        TextInput::make('note'), // no rules → omitted
    ]);

    expect($repeater->getItemValidationRules())->toBe([
        'name' => ['required'],
        'email' => ['email'],
    ]);
});

test('item validation rules descend into nested layout components', function () {
    $repeater = Repeater::make('contacts')->schema([
        Grid::make()->schema([
            TextInput::make('name')->required(),
            Grid::make()->schema([
                TextInput::make('email')->rules(['email']),
            ]),
        ]),
        TextInput::make('phone')->required(),
    ]);

    expect($repeater->getItemValidationRules())->toBe([
        'name' => ['required'],
        'email' => ['email'],
        'phone' => ['required'],
    ]);
});

test('mutate relationship data callback can be set', function () {
    $callback = fn ($data) => array_merge($data, ['sort_order' => 0]);
    $repeater = Repeater::make('items')
        ->mutateRelationshipDataBeforeSaveUsing($callback);

    expect($repeater->getMutateRelationshipDataBeforeSaveUsing())->toBe($callback);
});

test('validation rules with only min', function () {
    $repeater = Repeater::make('items')->minItems(2);

    $rules = $repeater->getRules();

    expect($rules)->toBe(['array', 'min:2']);
});

test('validation rules with only max', function () {
    $repeater = Repeater::make('items')->maxItems(5);

    $rules = $repeater->getRules();

    expect($rules)->toBe(['array', 'max:5']);
});

test('disabled with closure evaluates dynamically', function () {
    $repeater = Repeater::make('items')->disabled(fn () => true);

    expect($repeater->isDisabled())->toBeTrue()
        ->and($repeater->isAddable())->toBeFalse()
        ->and($repeater->isDeletable())->toBeFalse();
});

test('disabled with false closure allows operations', function () {
    $repeater = Repeater::make('items')->disabled(fn () => false);

    expect($repeater->isDisabled())->toBeFalse()
        ->and($repeater->isAddable())->toBeTrue()
        ->and($repeater->isDeletable())->toBeTrue();
});

test('relationship defaults to null', function () {
    $repeater = Repeater::make('items');

    expect($repeater->getRelationship())->toBeNull();
});

test('min and max items default to null', function () {
    $repeater = Repeater::make('items');

    expect($repeater->getMinItems())->toBeNull()
        ->and($repeater->getMaxItems())->toBeNull();
});

test('mutate relationship data callback defaults to null', function () {
    $repeater = Repeater::make('items');

    expect($repeater->getMutateRelationshipDataBeforeSaveUsing())->toBeNull();
});

test('collapsed without collapsible does not enable collapsible when false', function () {
    $repeater = Repeater::make('items')->collapsed(false);

    expect($repeater->isCollapsed())->toBeFalse()
        ->and($repeater->isCollapsible())->toBeFalse();
});

test('item schema clones are independent from each other', function () {
    $repeater = Repeater::make('contacts')
        ->statePath('data')
        ->schema([
            TextInput::make('name'),
        ]);

    $schema0 = $repeater->getItemSchema(0);
    $schema1 = $repeater->getItemSchema(1);

    // Verify they are different instances
    expect($schema0[0])->not->toBe($schema1[0])
        ->and($schema0[0]->getStatePath())->toBe('data.contacts.0.name')
        ->and($schema1[0]->getStatePath())->toBe('data.contacts.1.name');
});

test('item schema propagates the item path into layout-wrapped fields (regression: Grid children kept an unprefixed statePath)', function () {
    $repeater = Repeater::make('addresses')
        ->schema([
            Grid::make()->schema([
                TextInput::make('street'),
            ]),
        ]);

    // Simulate the form-runtime prepare, which also stamps a resolved state path
    // onto the Grid — getItemSchema() must recompute it per item, not reuse it.
    $repeater->prepareChildren('data');

    $grid0 = $repeater->getItemSchema(0)[0];
    $grid1 = $repeater->getItemSchema(1)[0];

    expect($grid0->getSchema()[0]->getStatePath())->toBe('data.addresses.0.street')
        ->and($grid1->getSchema()[0]->getStatePath())->toBe('data.addresses.1.street')
        ->and($grid0->getResolvedStatePath())->toBe('data.addresses.0');
});

test('item schema deep-clones layout children per item (regression: shallow clone shared field instances across rows)', function () {
    $repeater = Repeater::make('addresses')
        ->statePath('data')
        ->schema([
            Grid::make()->schema([
                TextInput::make('street'),
            ]),
        ]);

    $grid0 = $repeater->getItemSchema(0)[0];
    $grid1 = $repeater->getItemSchema(1)[0];

    // Distinct instances per item — and the original schema is left untouched.
    expect($grid0->getSchema()[0])->not->toBe($grid1->getSchema()[0])
        ->and($grid0->getSchema()[0])->not->toBe($repeater->getSchema()[0]->getSchema()[0])
        ->and($repeater->getSchema()[0]->getSchema()[0]->getStatePath())->toBe('street');
});

test('item schema propagates the item path through nested layouts', function () {
    $repeater = Repeater::make('addresses')
        ->statePath('data')
        ->schema([
            Grid::make()->schema([
                Grid::make()->schema([
                    TextInput::make('street'),
                ]),
            ]),
        ]);

    $outer = $repeater->getItemSchema(3)[0];

    expect($outer->getSchema()[0]->getSchema()[0]->getStatePath())->toBe('data.addresses.3.street');
});

test('validation attribute uses label when available', function () {
    $repeater = Repeater::make('contacts');

    // Label is auto-generated from name as 'Contacts'
    expect($repeater->getValidationAttribute())->toBe('Contacts');
});

test('validation attribute uses name when no label', function () {
    $repeater = Repeater::make('contacts');

    // getValidationAttribute returns getLabel() ?? getName()
    // Since LayoutComponent auto-generates label, it returns 'Contacts'
    $result = $repeater->getValidationAttribute();
    expect($result)->toBeString()->not->toBeEmpty();
});

test('wire model attribute equals state path', function () {
    $repeater = Repeater::make('contacts')->statePath('form');

    expect($repeater->getWireModelAttribute())->toBe('form.contacts');
});
