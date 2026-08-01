<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;

/**
 * The hint vocabulary is `hint()` + `hintIcon()` + `hintColor()`, but the shared
 * field wrapper rendered only the text: both other setters stored their value
 * and nothing ever read it, so calling them did nothing at all.
 *
 * The wrapper is shared by every field, so these render through it rather than
 * through any one field's own view.
 */
function renderField(Field $field): string
{
    view()->share('errors', new ViewErrorBag);
    view()->share('_instance', new class
    {
        public function getId(): string
        {
            return 'test-component';
        }
    });

    return $field->render()->render();
}

it('renders the hint icon it was given', function () {
    $html = renderField(TextInput::make('price')->hint('Excluding VAT')->hintIcon('information-circle'));

    expect($html)->toContain('Excluding VAT')
        ->and($html)->toContain('<svg');
});

it('colors the hint through the canonical palette', function () {
    $html = renderField(TextInput::make('price')->hint('Excluding VAT')->hintColor('danger'));

    expect($html)->toContain('text-red-600');
});

it('accepts a Color enum for the hint color', function () {
    expect(renderField(TextInput::make('price')->hint('x')->hintColor(Color::Red)))
        ->toContain('text-red-600');
});

it('resolves a closure hint icon and color', function () {
    $html = renderField(
        TextInput::make('price')
            ->hint('Excluding VAT')
            ->hintIcon(fn () => 'information-circle')
            ->hintColor(fn () => 'warning')
    );

    expect($html)->toContain('<svg')->toContain('text-amber-600');
});

it('falls back to the muted hint color when none is set', function () {
    $html = renderField(TextInput::make('price')->hint('Excluding VAT'));

    expect($html)->toContain('text-gray-500')
        ->and($html)->not->toContain('text-red-600');
});

it('renders no hint row at all when there is no hint', function () {
    expect(renderField(TextInput::make('price')))->not->toContain('Excluding VAT');
});

// The wrapper is shared, so the vocabulary must reach every field, not just the
// one it was tried on.
it('reaches a different field type through the same wrapper', function () {
    $html = renderField(
        Select::make('role')->options(['a' => 'A'])->hint('Pick one')->hintColor('danger')
    );

    expect($html)->toContain('Pick one')->toContain('text-red-600');
});

// ─── Outer extra attributes ──────────────────────────────────────────────────

// extraAttributes() is declared by every field but was rendered by nothing.
it('renders extra attributes on the field wrapper', function () {
    $html = renderField(TextInput::make('price')->extraAttributes([
        'data-role' => 'price-field',
        'data-analytics' => 'checkout',
    ]));

    expect($html)->toContain('data-role="price-field"')
        ->and($html)->toContain('data-analytics="checkout"');
});

it('renders no stray attributes when none were set', function () {
    expect(renderField(TextInput::make('price')))->not->toContain('data-role=');
});

it('reaches every field type, since the wrapper is shared', function () {
    expect(renderField(Select::make('role')->options(['a' => 'A'])->extraAttributes(['data-role' => 'select'])))
        ->toContain('data-role="select"');
});
