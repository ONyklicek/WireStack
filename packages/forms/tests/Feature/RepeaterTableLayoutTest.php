<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * The table layout is the same repeater — same state paths, same add/remove
 * wiring — arranged as rows under one header instead of a card per item.
 */
class RepeaterTableComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'lines' => [
            ['description' => 'Consulting', 'amount' => '1200'],
            ['description' => 'Hosting', 'amount' => '300'],
        ],
    ];

    public bool $asTable = true;

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Repeater::make('lines')
                    ->table($this->asTable)
                    ->reorderable()
                    ->schema([
                        TextInput::make('description')->label('What'),
                        TextInput::make('amount')->label('How much'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('heads each schema field once and repeats only the inputs', function () {
    $html = Livewire::test(RepeaterTableComponent::class)->html();

    expect($html)->toContain('<table')
        // One heading per field, not one label per cell.
        ->and(substr_count($html, 'What'))->toBe(1)
        ->and(substr_count($html, 'How much'))->toBe(1)
        // Two items × two fields, each still bound to its own item path.
        ->and($html)->toContain('data.lines.0.description')
        ->and($html)->toContain('data.lines.1.amount');
});

it('keeps the card layout labelling every field per item', function () {
    $html = Livewire::test(RepeaterTableComponent::class, ['asTable' => false])->html();

    expect($html)->not->toContain('<table')
        // A card repeats the label on every item.
        ->and(substr_count($html, 'What'))->toBe(2);
});

it('adds and removes rows through the same repeater methods', function () {
    Livewire::test(RepeaterTableComponent::class)
        ->assertSet('data.lines.0.description', 'Consulting')
        ->call('addRepeaterItem', 'data.lines')
        ->assertCount('data.lines', 3)
        ->call('removeRepeaterItem', 'data.lines', 0)
        ->assertCount('data.lines', 2)
        // Removing the first row shifts the rest up rather than leaving a hole.
        ->assertSet('data.lines.0.description', 'Hosting');
});

it('reorders rows through the same repeater method', function () {
    Livewire::test(RepeaterTableComponent::class)
        ->call('reorderRepeaterItems', 'data.lines', [1, 0])
        ->assertSet('data.lines.0.description', 'Hosting');
});

it('shows an empty-state row when there is nothing to repeat', function () {
    $html = Livewire::test(RepeaterTableComponent::class, ['data' => ['lines' => []]])->html();

    expect($html)->toContain('No items yet')
        // The header still stands, so the columns stay legible.
        ->and($html)->toContain('What');
});
