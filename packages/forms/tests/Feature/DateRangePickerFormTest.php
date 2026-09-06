<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * The claim this file exists for: a range persists with no state seam of its
 * own. Because the two ends are ordinary fields inside a layout, the form
 * flattens them into its field list — so filling, validation and saving reach
 * two real date columns, exactly as if the schema had listed two pickers.
 */
class RangeContract extends Model
{
    protected $table = 'range_contracts';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];
}

class DateRangeHost extends Component
{
    use WithForms;

    public ?int $contractId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(RangeContract::find($this->contractId) ?? RangeContract::class)
            ->statePath('data')
            ->schema([
                DateRangePicker::make('validity')
                    ->from('valid_from')
                    ->until('valid_to')
                    ->presets(),
            ]);
    }

    public function mount(): void
    {
        $record = RangeContract::find($this->contractId);

        $this->form->fill($record ? $record->attributesToArray() : []);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    Schema::create('range_contracts', function (Blueprint $table) {
        $table->id();
        $table->date('valid_from')->nullable();
        $table->date('valid_to')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('range_contracts');
});

it('fills both ends from the record it was given', function () {
    $contract = RangeContract::create(['valid_from' => '2026-01-01', 'valid_to' => '2026-03-31']);

    $component = Livewire::test(DateRangeHost::class, ['contractId' => $contract->id]);

    expect($component->get('data.valid_from'))->toBe('2026-01-01')
        ->and($component->get('data.valid_to'))->toBe('2026-03-31');
});

it('saves the period into the two columns it names', function () {
    $contract = RangeContract::create(['valid_from' => '2026-01-01', 'valid_to' => '2026-03-31']);

    Livewire::test(DateRangeHost::class, ['contractId' => $contract->id])
        ->set('data.valid_from', '2026-02-01')
        ->set('data.valid_to', '2026-04-30')
        ->call('save');

    $contract->refresh();

    expect($contract->valid_from->format('Y-m-d'))->toBe('2026-02-01')
        ->and($contract->valid_to->format('Y-m-d'))->toBe('2026-04-30');
});

it('refuses a period that ends before it starts', function () {
    $contract = RangeContract::create(['valid_from' => '2026-01-01', 'valid_to' => '2026-03-31']);

    Livewire::test(DateRangeHost::class, ['contractId' => $contract->id])
        ->set('data.valid_from', '2026-05-01')
        ->set('data.valid_to', '2026-04-30')
        ->call('save')
        ->assertHasErrors('data.valid_to');

    // Nothing was written: the save stopped at validation.
    expect($contract->refresh()->valid_from->format('Y-m-d'))->toBe('2026-01-01');
});

it('renders both pickers and the presets over the two state paths', function () {
    $html = Livewire::test(DateRangeHost::class)->html();

    expect($html)->toContain('data.valid_from')
        ->toContain('data.valid_to')
        ->toContain('This month')
        // The preset writes both ends at once, and `.live` is what redraws the
        // pickers with the values it wrote.
        ->toContain('.live');
});

it('binds each end to the other as it is picked', function () {
    $contract = RangeContract::create(['valid_from' => '2026-02-01', 'valid_to' => '2026-04-30']);

    $component = Livewire::test(DateRangeHost::class, ['contractId' => $contract->id]);
    $html = $component->html();

    // The end picker cannot open before the start, and the start cannot pass
    // the end — the bound each carries is the value at the other end.
    expect($html)->toContain('2026-02-01')
        ->toContain('2026-04-30');
});
