<?php

declare(strict_types=1);

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use NyonCode\WireCore\Core\Validation\Contracts\Validatable;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Core\Validation\ValidationResult;

function createValidationPipeline(): ValidationPipeline
{
    $translator = new Translator(new ArrayLoader, 'en');
    $factory = new ValidatorFactory($translator);

    return new ValidationPipeline($factory);
}

// --- ValidationResult ---

it('creates a success result', function () {
    $result = ValidationResult::success(['name' => 'John']);

    expect($result->passed())->toBeTrue()
        ->and($result->failed())->toBeFalse()
        ->and($result->errors())->toBeEmpty()
        ->and($result->validatedData())->toBe(['name' => 'John']);
});

it('creates a failure result', function () {
    $errors = ['email' => ['The email field is required.']];
    $result = ValidationResult::failure($errors);

    expect($result->passed())->toBeFalse()
        ->and($result->failed())->toBeTrue()
        ->and($result->errors())->toBe($errors)
        ->and($result->hasError('email'))->toBeTrue()
        ->and($result->getError('email'))->toBe(['The email field is required.'])
        ->and($result->hasError('name'))->toBeFalse()
        ->and($result->getError('name'))->toBeNull();
});

it('merges two successful results', function () {
    $a = ValidationResult::success(['name' => 'John']);
    $b = ValidationResult::success(['email' => 'john@example.com']);

    $merged = $a->merge($b);

    expect($merged->passed())->toBeTrue()
        ->and($merged->validatedData())->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('merges two failed results', function () {
    $a = ValidationResult::failure(['name' => ['Name is required.']]);
    $b = ValidationResult::failure(['email' => ['Email is required.']]);

    $merged = $a->merge($b);

    expect($merged->failed())->toBeTrue()
        ->and($merged->errors())->toBe([
            'name' => ['Name is required.'],
            'email' => ['Email is required.'],
        ]);
});

it('merges a success and a failure result', function () {
    $a = ValidationResult::success(['name' => 'John']);
    $b = ValidationResult::failure(['email' => ['Email is invalid.']]);

    $merged = $a->merge($b);

    expect($merged->failed())->toBeTrue()
        ->and($merged->hasError('email'))->toBeTrue();
});

// --- ValidationPipeline ---

it('validates data that passes rules', function () {
    $pipeline = createValidationPipeline();

    $result = $pipeline->validate(
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'required|string', 'email' => 'required|email'],
    );

    expect($result->passed())->toBeTrue()
        ->and($result->validatedData())->toHaveKeys(['name', 'email']);
});

it('validates data that fails rules', function () {
    $pipeline = createValidationPipeline();

    $result = $pipeline->validate(
        ['name' => '', 'email' => 'not-an-email'],
        ['name' => 'required', 'email' => 'required|email'],
    );

    expect($result->failed())->toBeTrue()
        ->and($result->hasError('name'))->toBeTrue()
        ->and($result->hasError('email'))->toBeTrue();
});

it('validates with custom messages', function () {
    $pipeline = createValidationPipeline();

    $result = $pipeline->validate(
        ['name' => ''],
        ['name' => 'required'],
        ['name.required' => 'Please provide your name.'],
    );

    expect($result->failed())->toBeTrue()
        ->and($result->getError('name'))->toContain('Please provide your name.');
});

it('validates a component implementing Validatable', function () {
    $component = new class implements Validatable
    {
        public function getValidationRules(): array
        {
            return ['title' => 'required|string|min:3'];
        }

        public function getValidationMessages(): array
        {
            return [];
        }

        public function getValidationAttributes(): array
        {
            return ['title' => 'Title'];
        }
    };

    $pipeline = createValidationPipeline();

    $result = $pipeline->validateComponent($component, ['title' => 'Hello']);

    expect($result->passed())->toBeTrue();
});

it('validates a component that fails', function () {
    $component = new class implements Validatable
    {
        public function getValidationRules(): array
        {
            return ['title' => 'required|min:5'];
        }

        public function getValidationMessages(): array
        {
            return [];
        }

        public function getValidationAttributes(): array
        {
            return [];
        }
    };

    $pipeline = createValidationPipeline();

    $result = $pipeline->validateComponent($component, ['title' => 'Hi']);

    expect($result->failed())->toBeTrue()
        ->and($result->hasError('title'))->toBeTrue();
});

it('validates many components and merges results', function () {
    $componentA = new class implements Validatable
    {
        public function getValidationRules(): array
        {
            return ['name' => 'required'];
        }

        public function getValidationMessages(): array
        {
            return [];
        }

        public function getValidationAttributes(): array
        {
            return [];
        }
    };

    $componentB = new class implements Validatable
    {
        public function getValidationRules(): array
        {
            return ['email' => 'required|email'];
        }

        public function getValidationMessages(): array
        {
            return [];
        }

        public function getValidationAttributes(): array
        {
            return [];
        }
    };

    $pipeline = createValidationPipeline();

    $result = $pipeline->validateMany([$componentA, $componentB], ['name' => '', 'email' => 'bad']);

    expect($result->failed())->toBeTrue()
        ->and($result->hasError('name'))->toBeTrue()
        ->and($result->hasError('email'))->toBeTrue();
});
