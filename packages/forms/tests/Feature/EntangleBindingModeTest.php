<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CodeEditor;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Components\MarkdownEditor;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Fields that entangle rather than bind `wire:model`.
 *
 * `@entangle` is not `wire:model` and does not take its modifiers. Livewire's
 * directive only honours `.live`, and its JS defines exactly one property on the
 * returned value:
 *
 *     Object.defineProperty(obj, 'live', { get() { isLive = true; return obj } })
 *
 * So anything past `.live` is `undefined`. These eight views used to append the
 * `wire:model` modifier verbatim, which meant a field declared `liveOnBlur()`
 * rendered `@entangle(...).live.blur` and produced
 * `x-data="{ value: undefined }"` — the field came up with no state at all. It
 * was equally broken before Livewire 4, when the modifier was a bare `blur`.
 *
 * `blur` has no meaning here: there is no input whose focus it could follow, the
 * value syncs through Alpine. So the question collapses to whether the binding is
 * live, and `liveOnBlur()` on an entangled field means live.
 */
function ebmFields(): array
{
    return [
        'toggle' => fn () => Toggle::make('flag'),
        'slider' => fn () => Slider::make('flag'),
        'tags' => fn () => Tags::make('flag'),
        'rating' => fn () => Rating::make('flag'),
        'key-value' => fn () => KeyValue::make('flag'),
        'color-picker' => fn () => ColorPicker::make('flag'),
        'code-editor' => fn () => CodeEditor::make('flag'),
        'markdown-editor' => fn () => MarkdownEditor::make('flag'),
    ];
}

it('never emits a modifier @entangle cannot answer to', function (string $name) {
    $make = ebmFields()[$name];

    expect($make()->getEntangleModifier())->toBe('')
        ->and($make()->live()->getEntangleModifier())->toBe('live')
        // The fix: not 'live.blur', which resolves to undefined in the browser.
        ->and($make()->liveOnBlur()->getEntangleModifier())->toBe('live');
})->with(array_keys(ebmFields()));

it('keeps wire:model fields on the full modifier', function () {
    // The two answers are deliberately different. A real wire:model binding still
    // wants `.live.blur`, because there the blur is a network trigger.
    $field = Toggle::make('flag')->liveOnBlur();

    expect($field->getWireModelModifier())->toBe('live.blur')
        ->and($field->getEntangleModifier())->toBe('live');
});

/**
 * The eight entangling fields on one live host — @entangle compiles to code that
 * reads `$__livewire`, so these cannot render outside a Livewire request.
 */
class EntangleBindingHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Toggle::make('flag')->liveOnBlur(),
            Slider::make('volume')->liveOnBlur(),
            Tags::make('labels')->liveOnBlur(),
            Rating::make('stars')->liveOnBlur(),
            KeyValue::make('pairs')->liveOnBlur(),
            ColorPicker::make('brand')->liveOnBlur(),
            CodeEditor::make('snippet')->liveOnBlur(),
            MarkdownEditor::make('notes')->liveOnBlur(),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('renders entangles a browser can evaluate, for every entangling field', function () {
    // The regression is only visible in the rendered string: `.live.blur` parses
    // as JS and evaluates to undefined, so the field came up with no state.
    $html = Livewire::test(EntangleBindingHost::class)->html();

    expect($html)->toContain('.entangle(')
        ->and(substr_count($html, '.live.blur'))->toBe(0)
        // ...and the live half is still there, so liveOnBlur did not silently
        // degrade to a deferred binding.
        ->and(substr_count($html, '.live'))->toBeGreaterThan(0);
});
