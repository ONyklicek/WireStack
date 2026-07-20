<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Seam test: relationship-repeater create / update / delete driven through the
 * REAL form pipeline (Livewire::test → validate() → persist() →
 * RelationshipSaveHandler). The existing RelationshipSaveHandlerTest calls the
 * handler directly with hand-built item arrays, bypassing the validate() gate —
 * which is exactly where unruled children were being dropped. This exercises the
 * gate too, across a full CRUD cycle, with a ruled child, an unruled child and a
 * DehydratesState (date) child.
 */

class RrpOrder extends Model
{
    protected $table = 'rrp_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function lines(): HasMany
    {
        return $this->hasMany(RrpLine::class, 'order_id');
    }
}

class RrpLine extends Model
{
    protected $table = 'rrp_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class RrpHost extends Component
{
    use WithForms;

    public ?int $orderId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(RrpOrder::find($this->orderId) ?? RrpOrder::class)
            ->statePath('data')
            ->schema([
                Repeater::make('lines')->relationship('lines')->schema([
                    TextInput::make('label')->required(),
                    TextInput::make('note'),                        // unruled
                    DateTimePicker::make('due_on')->asDate()->format('Y-m-d'),
                ]),
            ]);
    }

    public function mount(): void
    {
        if ($this->orderId !== null) {
            $order = RrpOrder::with('lines')->find($this->orderId);
            $this->form->fill([
                'lines' => $order->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'label' => $l->label,
                    'note' => $l->note,
                    'due_on' => $l->due_on,
                ])->all(),
            ]);
        }
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('rrp_orders', function (Blueprint $t) {
        $t->id();
    });
    Schema::create('rrp_lines', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->string('label');
        $t->string('note')->nullable();
        $t->date('due_on')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('rrp_lines');
    Schema::dropIfExists('rrp_orders');
});

it('creates children with ruled, unruled and date fields all persisted', function () {
    Livewire::test(RrpHost::class)
        ->set('data.lines', [
            ['label' => 'First', 'note' => 'n1', 'due_on' => '2026-03-09'],
            ['label' => 'Second', 'note' => null, 'due_on' => '2026-04-01'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $order = RrpOrder::first();
    $lines = $order->lines()->orderBy('label')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->label)->toBe('First')
        ->and($lines[0]->note)->toBe('n1')                    // unruled kept
        ->and((string) $lines[0]->due_on)->toStartWith('2026-03-09'); // date dehydrated to format
});

it('updates an existing child in place without recreating it', function () {
    $order = RrpOrder::create();
    $line = $order->lines()->create(['label' => 'Old', 'note' => 'keep', 'due_on' => '2026-01-01']);

    Livewire::test(RrpHost::class, ['orderId' => $order->id])
        ->set('data.lines.0.label', 'New')
        ->call('save')
        ->assertHasNoErrors();

    $order->refresh();

    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->id)->toBe($line->id)   // same row, not replaced
        ->and($order->lines->first()->label)->toBe('New')
        ->and($order->lines->first()->note)->toBe('keep');   // untouched field preserved
});

it('deletes a child removed from the repeater', function () {
    $order = RrpOrder::create();
    $order->lines()->create(['label' => 'A']);
    $keep = $order->lines()->create(['label' => 'B']);

    // Rebuild the repeater state with only the second line.
    Livewire::test(RrpHost::class, ['orderId' => $order->id])
        ->set('data.lines', [['id' => $keep->id, 'label' => 'B', 'note' => null, 'due_on' => null]])
        ->call('save')
        ->assertHasNoErrors();

    $order->refresh();

    expect($order->lines)->toHaveCount(1)
        ->and($order->lines->first()->label)->toBe('B');
});
