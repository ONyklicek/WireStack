<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

// ─── Tabs: flattening & validation ───────────────────────────────

test('fields inside tabs flatten and validate together', function () {
    $form = Form::make()
        ->schema([
            Tabs::make()->schema([
                Tab::make('Personal')->schema([TextInput::make('name')->required()]),
                Tab::make('Contact')->schema([TextInput::make('email')->rules(['email'])->required()]),
            ]),
        ])
        ->state(['name' => '', 'email' => 'invalid']);

    expect(fn () => $form->validate())->toThrow(ValidationException::class);
});

test('a form with valid data across tabs validates successfully', function () {
    $form = Form::make()
        ->schema([
            Tabs::make()->schema([
                Tab::make('Personal')->schema([TextInput::make('name')->required()]),
                Tab::make('Contact')->schema([TextInput::make('email')->rules(['email'])->required()]),
            ]),
        ])
        ->state(['name' => 'John', 'email' => 'john@example.com']);

    $data = $form->validate();

    expect($data)->toHaveKeys(['name', 'email']);
});

test('tab fields appear in the flat component list', function () {
    $form = Form::make()->schema([
        Tabs::make()->schema([
            Tab::make('One')->schema([TextInput::make('a')]),
            Tab::make('Two')->schema([TextInput::make('b')]),
        ]),
    ]);

    $names = array_map(fn ($c) => $c->getName(), $form->getFlatComponents());

    expect($names)->toContain('a')->toContain('b');
});

// ─── Wizard: flattening & validation ─────────────────────────────

test('fields inside wizard steps flatten and validate together', function () {
    $form = Form::make()
        ->schema([
            Wizard::make()->schema([
                Step::make('Account')->schema([TextInput::make('username')->required()]),
                Step::make('Profile')->schema([TextInput::make('bio')->required()]),
            ]),
        ])
        ->state(['username' => 'jane', 'bio' => '']);

    expect(fn () => $form->validate())->toThrow(ValidationException::class);
});

test('a form with valid data across wizard steps validates successfully', function () {
    $form = Form::make()
        ->schema([
            Wizard::make()->schema([
                Step::make('Account')->schema([TextInput::make('username')->required()]),
                Step::make('Profile')->schema([TextInput::make('bio')->required()]),
            ]),
        ])
        ->state(['username' => 'jane', 'bio' => 'Hello']);

    expect($form->validate())->toHaveKeys(['username', 'bio']);
});

// ─── Rendering ───────────────────────────────────────────────────

beforeEach(fn () => view()->share('errors', new ViewErrorBag));

test('a form renders tab labels and their nested fields', function () {
    $html = Form::make()->schema([
        Tabs::make()->schema([
            Tab::make('Personal')->schema([TextInput::make('name')->label('Full name')]),
            Tab::make('Contact')->schema([TextInput::make('email')->label('Email address')]),
        ]),
    ])->toHtml();

    expect($html)
        ->toContain('role="tablist"')
        ->toContain('Personal')
        ->toContain('Contact')
        ->toContain('Full name')
        ->toContain('Email address');
});

test('a form renders wizard steps, navigation and nested fields', function () {
    $html = Form::make()->schema([
        Wizard::make()->schema([
            Step::make('Account')->schema([TextInput::make('username')->label('Username')]),
            Step::make('Profile')->schema([TextInput::make('bio')->label('Bio')]),
        ]),
    ])->toHtml();

    expect($html)
        ->toContain('step: 0')
        ->toContain('Account')
        ->toContain('Profile')
        ->toContain('Username')
        ->toContain('Bio')
        ->toContain(__('wire-core::actions.wizard_next'));
});
