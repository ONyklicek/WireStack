<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Config\ConfigBuilder;

test('builder accumulates fluent calls and builds config', function () {
    $schema = [TextInput::make('name')];
    $mutation = fn (array $data) => $data;

    $config = (new ConfigBuilder)
        ->schema($schema)
        ->statePath('data')
        ->model('App\\Models\\User')
        ->mutateDataBeforeSave($mutation)
        ->validationMessages(['name.required' => 'Required!'])
        ->disabled()
        ->build();

    expect($config->schema)->toHaveCount(1)
        ->and($config->statePath)->toBe('data')
        ->and($config->model)->toBe('App\\Models\\User')
        ->and($config->mutateDataBeforeSave)->toBe($mutation)
        ->and($config->validationMessages)->toBe(['name.required' => 'Required!'])
        ->and($config->isDisabled)->toBeTrue();
});

test('builder provides getters before building', function () {
    $builder = (new ConfigBuilder)
        ->schema([TextInput::make('email')])
        ->statePath('form')
        ->model('App\\Models\\User');

    expect($builder->getSchema())->toHaveCount(1)
        ->and($builder->getStatePath())->toBe('form')
        ->and($builder->getModel())->toBe('App\\Models\\User')
        ->and($builder->isDisabled())->toBeFalse();
});

test('each build creates fresh config', function () {
    $builder = (new ConfigBuilder)->statePath('a');

    $config1 = $builder->build();
    $builder->statePath('b');
    $config2 = $builder->build();

    expect($config1->statePath)->toBe('a')
        ->and($config2->statePath)->toBe('b');
});
