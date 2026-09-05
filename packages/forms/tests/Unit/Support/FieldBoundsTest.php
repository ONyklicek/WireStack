<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\CodeEditor;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * The numeric configuration guards, and what each one is protecting against.
 *
 * These setters feed HTML attributes and Laravel validation rules, so a wrong
 * value does not fail — it renders. Every case below used to be accepted
 * silently and found by whoever tried to fill the form in.
 */

// ─── A count that cannot be zero ─────────────────────────────────────────────

it('refuses a count or step that renders a field nobody can use', function (Closure $build, string $method) {
    expect($build)->toThrow(FormConfigurationException::class, $method);
})->with([
    'rating with no stars' => [fn () => Rating::make('score')->max(0), 'max'],
    'otp with no boxes' => [fn () => OtpInput::make('code')->length(0), 'length'],
    'otp separator every 0 chars' => [fn () => OtpInput::make('code')->separator(0), 'separator'],
    'textarea with no rows' => [fn () => Textarea::make('bio')->rows(0), 'rows'],
    'textarea with no columns' => [fn () => Textarea::make('bio')->cols(0), 'cols'],
    'slider that cannot move' => [fn () => Slider::make('level')->step(0), 'step'],
    'checkbox list with no columns' => [fn () => CheckboxList::make('tags')->columns(0), 'columns'],
    'no files allowed' => [fn () => FileUpload::make('docs')->maxFiles(0), 'maxFiles'],
    'negative rating' => [fn () => Rating::make('score')->max(-5), 'max'],
]);

it('says what a zero-valued setter would have produced', function () {
    expect(fn () => Rating::make('score')->max(0))
        ->toThrow(FormConfigurationException::class, 'renders and cannot be used');
});

// ─── A bound where zero is fine but negative is not ──────────────────────────

it('refuses a negative bound, which is a rule every value satisfies', function (Closure $build) {
    expect($build)->toThrow(FormConfigurationException::class, 'negative bound');
})->with([
    'min length' => [fn () => TextInput::make('name')->minLength(-1)],
    'max length' => [fn () => TextInput::make('name')->maxLength(-1)],
    'min items' => [fn () => Repeater::make('rows')->minItems(-1)],
    'max items' => [fn () => Repeater::make('rows')->maxItems(-1)],
    'max file size' => [fn () => FileUpload::make('docs')->maxSize(-1)],
    'min file size' => [fn () => FileUpload::make('docs')->minSize(-1)],
    'editor max length' => [fn () => CodeEditor::make('body')->maxLength(-1)],
]);

it('still accepts zero where zero means something', function () {
    // `minItems(0)` says "no floor"; `maxItems(0)` says "empty only". Both are
    // configurations someone may want, and neither is the mistake above.
    expect(Repeater::make('rows')->minItems(0)->getMinItems())->toBe(0)
        ->and(Repeater::make('rows')->maxItems(0)->getMaxItems())->toBe(0)
        ->and(TextInput::make('name')->minLength(0)->getMinLength())->toBe(0);
});

it('still accepts null, which removes the constraint', function () {
    expect(TextInput::make('name')->maxLength(20)->maxLength(null)->getMaxLength())->toBeNull()
        ->and(FileUpload::make('docs')->maxFiles(3)->maxFiles(null)->getMaxFiles())->toBeNull();
});

// ─── A pair that contradicts itself ──────────────────────────────────────────

it('refuses a minimum above its maximum, in either writing order', function () {
    // min:10|max:2 is a field no input can satisfy. Checked from whichever setter
    // runs second, so the author is told regardless of how they wrote it.
    expect(fn () => TextInput::make('name')->maxLength(2)->minLength(10))
        ->toThrow(FormConfigurationException::class, 'cannot exceed')
        ->and(fn () => TextInput::make('name')->minLength(10)->maxLength(2))
        ->toThrow(FormConfigurationException::class, 'cannot exceed');
});

it('refuses contradictory pairs on every field that has one', function (Closure $build) {
    expect($build)->toThrow(FormConfigurationException::class, 'cannot exceed');
})->with([
    'repeater items' => [fn () => Repeater::make('rows')->maxItems(1)->minItems(5)],
    'file sizes' => [fn () => FileUpload::make('docs')->maxSize(100)->minSize(500)],
    'file counts' => [fn () => FileUpload::make('docs')->maxFiles(2)->minFiles(9)],
    'slider range' => [fn () => Slider::make('level')->max(10)->min(50)],
    'text input range' => [fn () => TextInput::make('age')->maxValue(5)->minValue(50)],
]);

it('names both halves of the pair so the fix is visible', function () {
    expect(fn () => Slider::make('level')->min(50)->max(10))
        ->toThrow(FormConfigurationException::class, 'min(50)');
});

it('lets equal bounds through', function () {
    // An exact-length field is a legitimate configuration, not a contradiction.
    expect(TextInput::make('pin')->minLength(4)->maxLength(4)->getMaxLength())->toBe(4);
});

// ─── What it deliberately does not check ─────────────────────────────────────

it('leaves a dynamic bound alone rather than guessing at it', function () {
    // A Closure needs a record to evaluate against, and a string bound may be a
    // date. Neither is comparable here, so both pass and stay the caller's to
    // keep consistent — silence is better than being loudly wrong about a value
    // this cannot see.
    expect(Slider::make('level')->min(50)->max(fn () => 10))->toBeInstanceOf(Slider::class)
        ->and(TextInput::make('due')->minValue('2026-01-01')->maxValue('2025-01-01'))
        ->toBeInstanceOf(TextInput::class);
});

it('accepts the one non-numeric step HTML defines', function () {
    expect(TextInput::make('price')->step('any')->getStep())->toBe('any');
});

// ─── The contract ────────────────────────────────────────────────────────────

it('marks a bounds failure as a wire failure on the SPL base callers already catch', function () {
    try {
        Rating::make('score')->max(0);
        $this->fail('Expected a zero-star rating to be refused.');
    } catch (FormConfigurationException $e) {
        expect($e)->toBeInstanceOf(WireException::class)
            ->and($e)->toBeInstanceOf(InvalidArgumentException::class);
    }
});
