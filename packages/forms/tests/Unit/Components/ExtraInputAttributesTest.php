<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Widgets\Widget;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\Radio;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;

/**
 * extraInputAttributes() sat on the concern every component shares, so 49 types
 * offered it and not one view implemented it. It now lives on the fields where a
 * single element carries the value — and only those.
 */
function renderInputField(Field $field): string
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

// Fields binding through @entangle cannot render outside a Livewire request;
// they are covered by ExtraInputAttributesRenderTest instead.
it('renders the attributes on the value-carrying element', function (Field $field) {
    $html = renderInputField($field->extraInputAttributes(['data-probe' => 'yes', 'autocomplete' => 'off']));

    expect($html)->toContain('data-probe="yes"')
        ->and($html)->toContain('autocomplete="off"');
})->with([
    'text input' => fn () => TextInput::make('name'),
    'textarea' => fn () => Textarea::make('bio'),
    'checkbox' => fn () => Checkbox::make('agree'),
    'hidden' => fn () => Hidden::make('token'),
    'select' => fn () => Select::make('role')->options(['a' => 'A']),
]);

it('renders nothing extra when none were set', function () {
    expect(renderInputField(TextInput::make('name')))->not->toContain('data-probe');
});

it('escapes attribute names and values', function () {
    $html = renderInputField(TextInput::make('name')->extraInputAttributes([
        'data-x' => 'a" onmouseover="alert(1)',
    ]));

    expect($html)->toContain('&quot;')
        ->and($html)->not->toContain('onmouseover="alert(1)"');
});

it('renders a true value as a bare boolean attribute and drops false/null', function () {
    $html = renderInputField(TextInput::make('name')->extraInputAttributes([
        'readonly' => true,
        'disabled' => false,
        'hidden' => null,
    ]));

    expect($html)->toContain('readonly')
        ->and($html)->not->toContain('disabled="')
        ->and($html)->not->toContain('hidden="');
});

it('resolves a closure', function () {
    expect(renderInputField(TextInput::make('name')->extraInputAttributes(fn () => ['data-probe' => 'closure'])))
        ->toContain('data-probe="closure"');
});

// The point of the move: a component with no single input no longer pretends to
// take the setter at all.
it('is not offered where there is no one input to put it on', function (string $class) {
    expect(method_exists($class, 'extraInputAttributes'))->toBeFalse();
})->with([
    'placeholder (no input)' => Placeholder::class,
    'radio (one input per option)' => Radio::class,
    'infolist entry' => TextEntry::class,
    'widget' => Widget::class,
]);

it('still offers the outer extraAttributes everywhere', function () {
    expect(method_exists(Placeholder::class, 'extraAttributes'))->toBeTrue()
        ->and(method_exists(Widget::class, 'extraAttributes'))->toBeTrue();
});
