<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;

// Using Action as a concrete class that uses HasModal trait

// ─── Confirmation ───────────────────────────────────────────────────────────

it('does not require confirmation by default', function () {
    expect(Action::make('test')->hasModal())->toBeFalse();
});

it('can require confirmation', function () {
    expect(Action::make('test')->requiresConfirmation()->hasModal())->toBeTrue();
});

it('can check if it requires confirmation', function () {
    $action = Action::make('delete')->requiresConfirmation();

    expect($action->doesRequireConfirmation())->toBeTrue();
});

it('does not require confirmation when form is set', function () {
    $action = Action::make('edit')
        ->requiresConfirmation()
        ->form([TextInput::make('reason')]);

    expect($action->doesRequireConfirmation())->toBeFalse()
        ->and($action->hasFormModal())->toBeTrue();
});

// ─── Modal Heading ──────────────────────────────────────────────────────────

it('has default heading from translation', function () {
    expect(Action::make('test')->getModalHeading())->toBe('Confirm action');
});

it('can set custom heading', function () {
    $action = Action::make('delete')->modalHeading('Smazat záznam');

    expect($action->getModalHeading())->toBe('Smazat záznam')
        ->and($action->hasModal())->toBeTrue();
});

it('supports dynamic heading via closure', function () {
    $action = Action::make('test')
        ->modalHeading(fn ($record) => "Smazat {$record->name}?");

    $record = (object) ['name' => 'Test'];

    expect($action->getModalHeading($record))->toBe('Smazat Test?');
});

// ─── Modal Description ──────────────────────────────────────────────────────

it('has default description when confirmation is required', function () {
    $action = Action::make('test')->requiresConfirmation();

    expect($action->getModalDescription())->toBe('Are you sure you want to perform this action?');
});

it('can set custom description', function () {
    $action = Action::make('delete')->modalDescription('Tato akce je nevratná.');

    expect($action->getModalDescription())->toBe('Tato akce je nevratná.');
});

// ─── Modal Icon ─────────────────────────────────────────────────────────────

it('has no modal icon by default', function () {
    expect(Action::make('test')->getModalIcon())->toBeNull();
});

it('can set modal icon with color', function () {
    $action = Action::make('delete')->modalIcon('trash', 'danger');

    expect($action->getModalIcon())->toBe('trash')
        ->and($action->getModalIconColor())->toBe('danger');
});

it('has default warning icon color', function () {
    expect(Action::make('test')->getModalIconColor())->toBe('warning');
});

// ─── Modal Actions Labels ───────────────────────────────────────────────────

it('has default submit label from translation', function () {
    expect(Action::make('test')->getModalSubmitActionLabel())->toBe('Confirm');
});

it('has default cancel label from translation', function () {
    expect(Action::make('test')->getModalCancelActionLabel())->toBe('Cancel');
});

it('can set custom submit label', function () {
    expect(Action::make('delete')->modalSubmitActionLabel('Smazat')->getModalSubmitActionLabel())
        ->toBe('Smazat');
});

it('can set custom cancel label', function () {
    expect(Action::make('delete')->modalCancelActionLabel('Ne')->getModalCancelActionLabel())
        ->toBe('Ne');
});

// ─── Modal Width ────────────────────────────────────────────────────────────

it('has default md width', function () {
    expect(Action::make('test')->getModalWidth())->toBe('md');
});

it('can set modal width', function () {
    expect(Action::make('test')->modalWidth('xl')->getModalWidth())->toBe('xl');
});

// ─── Close Behavior ─────────────────────────────────────────────────────────

it('closes on click away by default', function () {
    expect(Action::make('test')->shouldCloseModalOnClickAway())->toBeTrue();
});

it('closes on escape by default', function () {
    expect(Action::make('test')->shouldCloseModalOnEscape())->toBeTrue();
});

it('can disable close on click away', function () {
    expect(Action::make('test')->closeModalOnClickAway(false)->shouldCloseModalOnClickAway())->toBeFalse();
});

it('can disable close on escape', function () {
    expect(Action::make('test')->closeModalOnEscape(false)->shouldCloseModalOnEscape())->toBeFalse();
});

// ─── Slide Over ─────────────────────────────────────────────────────────────

it('is not slide over by default', function () {
    expect(Action::make('test')->isSlideOver())->toBeFalse();
});

it('can be set to slide over', function () {
    $action = Action::make('test')->slideOver();

    expect($action->isSlideOver())->toBeTrue()
        ->and($action->hasModal())->toBeTrue();
});

it('can slide over on mobile only', function () {
    $action = Action::make('test')->slideOverOnMobile();

    expect($action->isSlideOverOnMobile())->toBeTrue()
        ->and($action->hasModal())->toBeTrue();
});

// ─── Full Screen on Mobile ──────────────────────────────────────────────────

it('is not full screen on mobile by default', function () {
    expect(Action::make('test')->isFullScreenOnMobile())->toBeFalse();
});

it('can be full screen on mobile', function () {
    expect(Action::make('test')->fullScreenOnMobile()->isFullScreenOnMobile())->toBeTrue();
});

// ─── Form ───────────────────────────────────────────────────────────────────

it('can set form with component array', function () {
    $action = Action::make('edit')
        ->form([TextInput::make('title')]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasModal())->toBeTrue();
});

it('can set form via closure', function () {
    $action = Action::make('edit')
        ->form(fn ($record) => [TextInput::make('title')]);

    expect($action->hasFormModal())->toBeTrue();
});

it('can set validation rules', function () {
    $action = Action::make('edit')
        ->form([TextInput::make('title')])
        ->formValidation(['title' => 'required']);

    expect($action->getRawFormValidation())->toBe(['title' => 'required']);
});

it('prefixes validation rules with actionModalFormData', function () {
    $action = Action::make('edit')
        ->form([TextInput::make('title')])
        ->formValidation(['title' => 'required']);

    $rules = $action->getFormValidation();

    expect($rules)->toHaveKey('actionModalFormData.title');
});

it('can set validation messages', function () {
    $action = Action::make('edit')
        ->validationMessages(['title.required' => 'Povinné pole']);

    expect($action->getRawValidationMessages())->toBe(['title.required' => 'Povinné pole']);
});

it('can set validation attributes', function () {
    $action = Action::make('edit')
        ->validationAttributes(['title' => 'Název']);

    expect($action->getRawValidationAttributes())->toBe(['title' => 'Název']);
});

it('seeds each field blank without fillFormUsing', function () {
    // Without fillFormUsing the schema still seeds a key per field so array
    // inputs never start missing/null; a plain text field blanks to null.
    $action = Action::make('edit')
        ->form([
            TextInput::make('title'),
        ]);

    expect($action->getFormDefaults())->toBe(['title' => null]);
});

it('seeds array fields as an empty array and applies field defaults', function () {
    $action = Action::make('edit')
        ->form([
            TextInput::make('title')->default('Untitled'),
            CheckboxList::make('permissions')->options(['a' => 'A']),
            Toggle::make('active'),
        ]);

    expect($action->getFormDefaults())->toBe([
        'title' => 'Untitled',
        'permissions' => [],
        'active' => false,
    ]);
});

it('lets fillFormUsing override the schema seed', function () {
    $action = Action::make('edit')
        ->form([
            TextInput::make('title')->default('Untitled'),
            CheckboxList::make('permissions')->options(['a' => 'A', 'b' => 'B']),
        ])
        ->fillFormUsing(fn () => ['permissions' => ['a']]);

    expect($action->getFormDefaults())->toBe([
        'permissions' => ['a'],
        'title' => 'Untitled',
    ]);
});

it('can use fillFormUsing to provide defaults', function () {
    $action = Action::make('edit')
        ->form([TextInput::make('title')])
        ->fillFormUsing(fn ($record) => ['title' => $record->title]);

    $record = (object) ['title' => 'Hello World'];

    expect($action->getFormDefaults($record))->toBe(['title' => 'Hello World']);
});

it('seeds fillFormUsing defaults without a context (header actions)', function () {
    // Header actions have no record/context. Their zero-argument fillFormUsing
    // closure must still run so array fields (e.g. CheckboxList) are initialised
    // as arrays instead of being left undefined.
    $action = Action::make('create')
        ->form([TextInput::make('name')])
        ->fillFormUsing(fn () => ['name' => '', 'permissions' => []]);

    expect($action->getFormDefaults())->toBe(['name' => '', 'permissions' => []]);
});

it('seeds a schema provided via a form closure', function () {
    $action = Action::make('edit')
        ->form(fn () => Form::make()->schema([
            CheckboxList::make('permissions')->options(['a' => 'A']),
        ]));

    expect($action->getFormDefaults())->toBe(['permissions' => []]);
});

it('seeds a schema from a closure that returns an array', function () {
    $action = Action::make('edit')
        ->form(fn () => [TextInput::make('title')->default('X')]);

    expect($action->getFormDefaults())->toBe(['title' => 'X']);
});

it('falls back to fillFormUsing when a form closure yields no schema', function () {
    $action = Action::make('edit')
        ->form(fn () => null)
        ->fillFormUsing(fn () => ['flag' => true]);

    expect($action->getFormDefaults())->toBe(['flag' => true]);
});

it('returns fillFormUsing defaults when the action has no form', function () {
    $action = Action::make('delete')
        ->fillFormUsing(fn () => ['confirm' => true]);

    expect($action->getFormDefaults())->toBe(['confirm' => true]);
});

it('returns no defaults when the action has neither form nor fillFormUsing', function () {
    expect(Action::make('delete')->getFormDefaults())->toBe([]);
});

// ─── Multi-step Modal ───────────────────────────────────────────────────────

it('has no steps by default', function () {
    expect(Action::make('test')->hasMultipleSteps())->toBeFalse();
});

it('can set steps', function () {
    $action = Action::make('wizard')
        ->steps([
            ['label' => 'Step 1', 'schema' => []],
            ['label' => 'Step 2', 'schema' => []],
        ]);

    expect($action->hasMultipleSteps())->toBeTrue()
        ->and($action->getModalSteps())->toHaveCount(2)
        ->and($action->hasModal())->toBeTrue();
});

// ─── Sticky Footer/Header ───────────────────────────────────────────────────

it('can set sticky footer', function () {
    $action = Action::make('test')->stickyFooter();
    $config = $action->getModalConfig();

    expect($config['stickyFooter'])->toBeTrue();
});

it('can set sticky header', function () {
    $action = Action::make('test')->stickyHeader();
    $config = $action->getModalConfig();

    expect($config['stickyHeader'])->toBeTrue();
});

it('can set max height', function () {
    $action = Action::make('test')->modalMaxHeight('60vh');
    $config = $action->getModalConfig();

    expect($config['maxHeight'])->toBe('60vh');
});

// ─── Modal Config ───────────────────────────────────────────────────────────

it('generates complete modal config', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalHeading('Smazat?')
        ->modalDescription('Nevratná akce')
        ->modalIcon('trash', 'danger')
        ->modalSubmitActionLabel('Smazat')
        ->modalCancelActionLabel('Zrušit')
        ->modalWidth('lg')
        ->color('danger');

    $config = $action->getModalConfig();

    expect($config['heading'])->toBe('Smazat?')
        ->and($config['description'])->toBe('Nevratná akce')
        ->and($config['icon'])->toBe('trash')
        ->and($config['iconColor'])->toBe('danger')
        ->and($config['submitLabel'])->toBe('Smazat')
        ->and($config['cancelLabel'])->toBe('Zrušit')
        ->and($config['width'])->toBe('lg')
        ->and($config['isConfirmation'])->toBeTrue()
        ->and($config['hasForm'])->toBeFalse();
});
