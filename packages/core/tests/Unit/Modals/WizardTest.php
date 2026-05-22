<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Modals\Wizard;
use NyonCode\WireForms\Components\TextInput;

// ─── Factory ───────────────────────────────────────────────────────────

it('can be created with make', function () {
    expect(Wizard::make())->toBeInstanceOf(Wizard::class);
});

// ─── Basic Properties ──────────────────────────────────────────────────

it('can set heading and description', function () {
    $wizard = Wizard::make()
        ->heading('Create User')
        ->description('Follow the steps.');

    expect($wizard->getHeading())->toBe('Create User')
        ->and($wizard->getDescription())->toBe('Follow the steps.');
});

// ─── Steps ─────────────────────────────────────────────────────────────

it('has no steps by default', function () {
    expect(Wizard::make()->getSteps())->toBeEmpty()
        ->and(Wizard::make()->getTotalSteps())->toBe(0);
});

it('can set steps as arrays', function () {
    $wizard = Wizard::make()->steps([
        ['label' => 'Step 1', 'schema' => []],
        ['label' => 'Step 2', 'schema' => []],
    ]);

    expect($wizard->getSteps())->toHaveCount(2)
        ->and($wizard->getTotalSteps())->toBe(2);
});

it('can set steps as ModalStep objects', function () {
    $wizard = Wizard::make()->steps([
        ModalStep::make('Basic Info')
            ->description('Enter basic details')
            ->icon('user')
            ->schema([TextInput::make('name')]),
        ModalStep::make('Settings')
            ->schema([TextInput::make('role')]),
    ]);

    expect($wizard->getSteps())->toHaveCount(2)
        ->and($wizard->getTotalSteps())->toBe(2);
});

it('serializes ModalStep objects in steps config', function () {
    $wizard = Wizard::make()->steps([
        ModalStep::make('Step 1')
            ->description('First step')
            ->icon('user')
            ->schema([TextInput::make('name')]),
    ]);

    $config = $wizard->getStepsConfig();

    expect($config)->toHaveCount(1)
        ->and($config[0]['label'])->toBe('Step 1')
        ->and($config[0]['description'])->toBe('First step')
        ->and($config[0]['icon'])->toBe('user')
        ->and($config[0]['schema'])->toHaveCount(1);
});

it('passes through array steps as-is in steps config', function () {
    $step = ['label' => 'Raw Step', 'schema' => [['name' => 'test']]];
    $wizard = Wizard::make()->steps([$step]);

    $config = $wizard->getStepsConfig();

    expect($config[0])->toBe($step);
});

// ─── Skippable ─────────────────────────────────────────────────────────

it('is not skippable by default', function () {
    expect(Wizard::make()->isSkippable())->toBeFalse();
});

it('can be set to skippable', function () {
    expect(Wizard::make()->skippable()->isSkippable())->toBeTrue();
});

// ─── Icon ──────────────────────────────────────────────────────────────

it('can set icon', function () {
    $wizard = Wizard::make()->icon('wizard', 'primary');

    expect($wizard->getIcon())->toBe('wizard')
        ->and($wizard->getIconColor())->toBe('primary');
});

// ─── Serialization ─────────────────────────────────────────────────────

it('serializes to array', function () {
    $wizard = Wizard::make()
        ->heading('Wizard')
        ->description('Multi-step')
        ->width('lg')
        ->icon('wizard', 'info')
        ->steps([
            ModalStep::make('Step 1')->schema([]),
            ModalStep::make('Step 2')->schema([]),
        ])
        ->skippable()
        ->stickyFooter()
        ->id('my-wizard');

    $array = $wizard->toArray();

    expect($array['heading'])->toBe('Wizard')
        ->and($array['description'])->toBe('Multi-step')
        ->and($array['width'])->toBe('lg')
        ->and($array['icon'])->toBe('wizard')
        ->and($array['iconColor'])->toBe('info')
        ->and($array['totalSteps'])->toBe(2)
        ->and($array['steps'])->toHaveCount(2)
        ->and($array['skippable'])->toBeTrue()
        ->and($array['stickyFooter'])->toBeTrue()
        ->and($array['id'])->toBe('my-wizard');
});

// ─── Fluent API ────────────────────────────────────────────────────────

it('supports fluent chaining', function () {
    $wizard = Wizard::make()
        ->heading('Test')
        ->description('Desc')
        ->width('xl')
        ->steps([])
        ->skippable()
        ->color('primary')
        ->stickyFooter()
        ->stickyHeader();

    expect($wizard)->toBeInstanceOf(Wizard::class);
});
