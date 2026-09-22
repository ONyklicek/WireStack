<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Schema\Fieldset;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * What a field's `columnSpan()` actually draws.
 *
 * The field wrapper carried its own span map — a third one, beside the canonical
 * owner and the grids' own — and it stopped at two columns: `columnSpan(3)` and
 * `columnSpan(4)` emitted **no class at all**, so a field declared three columns
 * wide rendered one column wide, and `columnSpanFull()` was drawn as two columns
 * rather than the row. All three are declarations the API accepts and the docs
 * describe, and nothing here covered any of them.
 *
 * Measured in a browser before this landed (`/previews/forms-grid-spans`, 1400px):
 * in a three-column grid the `columnSpan(3)` field was 369px — the same width as
 * its one-column neighbour — and in a four-column grid the `columnSpan(4)` field
 * was 265px.
 */
class ColumnSpanFormComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Grid::make()->columns(3)->schema([
                    TextInput::make('one'),
                    TextInput::make('wide')->columnSpan(3),
                    TextInput::make('banner')->columnSpanFull(),
                ]),
                Fieldset::make('Address')->columns(4)->schema([
                    TextInput::make('street')->columnSpan(4),
                ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('draws a span wider than two columns, which used to render as one', function () {
    $html = Livewire::test(ColumnSpanFormComponent::class)->html();

    // `cols(3)` is one column until `md`, so three columns start there.
    expect($html)->toContain('md:col-span-3');
});

it('spans the row for columnSpanFull(), not two columns', function () {
    $html = Livewire::test(ColumnSpanFormComponent::class)->html();

    expect($html)->toContain('col-span-full');
});

it('steps a span with the ladder the surrounding grid climbs', function () {
    // A fieldset ramps at `sm`: two columns there, three at `md`, four at `lg` —
    // and a field spanning four has to say all three, or it asks for a column
    // the grid does not have and CSS Grid adds it.
    $html = Livewire::test(ColumnSpanFormComponent::class)->html();

    expect($html)->toContain('sm:col-span-2 md:col-span-3 lg:col-span-4');
});

it('never emits a span the grid it sits in cannot honour', function () {
    $html = Livewire::test(ColumnSpanFormComponent::class)->html();

    // Nothing anywhere on this form asks for a column at `sm` from a grid that
    // has one column until `md` — the shape that silently re-flows a layout.
    expect($html)->not->toContain('sm:col-span-3')
        ->and($html)->not->toContain('sm:col-span-4');
});
