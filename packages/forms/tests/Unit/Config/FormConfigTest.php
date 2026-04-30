<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Config\FormConfig;

test('config is immutable with defaults', function () {
    $config = new FormConfig;

    expect($config->schema)->toBe([])
        ->and($config->statePath)->toBeNull()
        ->and($config->model)->toBeNull()
        ->and($config->mutateDataBeforeSave)->toBeNull()
        ->and($config->beforeSave)->toBeNull()
        ->and($config->afterSave)->toBeNull()
        ->and($config->using)->toBeNull()
        ->and($config->successMessage)->toBe('__default__')
        ->and($config->validationMessages)->toBe([])
        ->and($config->isDisabled)->toBeFalse();
});

test('isCreating returns true for class-string model', function () {
    $config = new FormConfig(model: 'App\\Models\\User');

    expect($config->isCreating())->toBeTrue()
        ->and($config->isEditing())->toBeFalse()
        ->and($config->hasModel())->toBeTrue();
});

test('isEditing returns true for model instance', function () {
    // Use a mock to simulate an Eloquent model
    $model = Mockery::mock(Model::class);
    $config = new FormConfig(model: $model);

    expect($config->isEditing())->toBeTrue()
        ->and($config->isCreating())->toBeFalse()
        ->and($config->hasModel())->toBeTrue();
});

test('hasModel returns false when null', function () {
    $config = new FormConfig(model: null);

    expect($config->hasModel())->toBeFalse();
});
